<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Api;
use App\Core\Database;
use App\Middleware\AuthMiddleware;
use App\Services\NutritionService;

/**
 * MenuController — API des menus hebdomadaires
 * ============================================
 *
 * C'est le contrôleur le plus riche : il gère les menus de la semaine
 * (7 jours × 3 repas = 21 créneaux). Il sait :
 *   - lister/afficher les menus enregistrés (index, show)
 *   - GÉNÉRER automatiquement un menu (generateDefault) en piochant des recettes
 *   - sauvegarder un menu modifié à la main (store)
 *   - supprimer un menu (destroy)
 *
 * Particularité : ce contrôleur travaille directement avec PDO (pas via un
 * modèle dédié) car les requêtes (jointures menu_meals/recipes) sont spécifiques.
 */
class MenuController
{
    /** Connexion PDO directe (requêtes sur mesure pour les menus). */
    private \PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getInstance()->getConnection();
    }

    /**
     * Aiguillage principal selon la méthode HTTP et l'action.
     *
     * @param string      $method GET, POST, DELETE.
     * @param string|null $action 'generate' pour la génération automatique.
     * @param int|null    $id     Identifiant de menu (pour show / destroy).
     */
    public function handle(string $method, ?string $action, ?int $id): void
    {
        AuthMiddleware::requireAuth(true);
        $body = Api::body();

        match (true) {
            $method === 'GET'  && $id !== null              => $this->show($id),        // un menu précis
            $method === 'GET'                               => $this->index(),          // liste des menus
            $method === 'POST' && $action === 'generate'    => $this->generateDefault($body), // génération auto
            $method === 'POST'                              => $this->store($body),     // sauvegarde manuelle
            $method === 'DELETE' && $id !== null            => $this->destroy($id),     // suppression
            default => Api::json(['success' => false, 'error' => 'Route non trouvée'], 404),
        };
    }

    /** Liste les 20 derniers menus de l'utilisateur (sans le détail des repas). */
    private function index(): void
    {
        $userId = (int) AuthMiddleware::currentUserId();
        $stmt = $this->pdo->prepare(
            'SELECT id, week_start, budget, persons, dietary, total_cost, created_at
             FROM weekly_menus WHERE user_id = :uid ORDER BY week_start DESC LIMIT 20'
        );
        $stmt->execute([':uid' => $userId]);
        Api::json(['success' => true, 'data' => $stmt->fetchAll()]);
    }

    /**
     * Affiche UN menu complet : l'en-tête + la grille des 7 jours + le bilan
     * nutritionnel. On joint menu_meals → recipes pour récupérer chaque plat.
     */
    private function show(int $id): void
    {
        $userId = (int) AuthMiddleware::currentUserId();
        $stmt = $this->pdo->prepare(
            'SELECT id, week_start, budget, persons, dietary, total_cost, created_at
             FROM weekly_menus WHERE id = :id AND user_id = :uid'
        );
        $stmt->execute([':id' => $id, ':uid' => $userId]);
        $menu = $stmt->fetch();

        if (!$menu) {
            Api::json(['success' => false, 'error' => 'Menu introuvable'], 404);
        }

        $stmt = $this->pdo->prepare(
            'SELECT mm.day_index, mm.meal_type, r.id AS recipe_id, r.name AS recipe_name,
                    r.prep_time, r.estimated_cost, r.calories, r.protein, r.dietary,
                    rs.ingredients_list
             FROM menu_meals mm
             LEFT JOIN recipes r ON mm.recipe_id = r.id
             LEFT JOIN recipe_summary rs ON r.id = rs.id
             WHERE mm.menu_id = :mid
             ORDER BY mm.day_index, mm.meal_type'
        );
        $stmt->execute([':mid' => $id]);
        $slots = $stmt->fetchAll();
        $days  = $this->buildWeekGrid($slots);
        $nutrition = $this->computeNutrition($slots);

        Api::json([
            'success' => true,
            'data'    => [
                'menu'      => $menu,
                'days'      => $days,
                'nutrition' => $nutrition,
            ],
        ]);
    }

    /**
     * Génère automatiquement un menu pour la semaine en cours.
     *
     * Étapes : on lit le budget/régime demandés → on pioche des recettes
     * (pickRecipesForWeek) → on vérifie qu'il y en a → on enregistre le menu
     * et ses 21 créneaux dans une transaction → on renvoie le menu via show().
     */
    private function generateDefault(array $body): void
    {
        $userId  = (int) AuthMiddleware::currentUserId();
        $budget  = (float) ($body['budget'] ?? 100);
        $persons = (int) ($body['persons'] ?? 2);
        $dietary = $this->sanitizeDietary((string) ($body['dietary'] ?? 'all'));
        $weekStart = $this->currentWeekMonday();
        $slots = $this->pickRecipesForWeek($userId, $dietary);

        if ($this->countSlots($slots) === 0) {
            Api::json(['success' => false, 'error' => 'Aucune recette disponible pour générer un menu.'], 422);
        }

        $totalCost = $this->computeTotalCost($slots);
        // Transaction : on insère l'en-tête PUIS les 21 repas. Tout ou rien.
        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare(
                'INSERT INTO weekly_menus (user_id, week_start, budget, persons, dietary, total_cost)
                 VALUES (:uid, :ws, :budget, :persons, :dietary, :cost)'
            );
            $stmt->execute([
                ':uid' => $userId, ':ws' => $weekStart, ':budget' => $budget,
                ':persons' => $persons, ':dietary' => $dietary, ':cost' => $totalCost,
            ]);
            $menuId = (int) $this->pdo->lastInsertId();

            $ins = $this->pdo->prepare(
                'INSERT INTO menu_meals (menu_id, day_index, meal_type, recipe_id) VALUES (:mid, :day, :type, :rid)'
            );
            foreach ($slots as $dayIndex => $meals) {
                foreach ($meals as $mealType => $recipe) {
                    if ($recipe === null) continue;
                    $ins->execute([':mid' => $menuId, ':day' => $dayIndex, ':type' => $mealType, ':rid' => $recipe['id']]);
                }
            }
            $this->pdo->commit();
        } catch (\Throwable) {
            $this->pdo->rollBack();
            Api::json(['success' => false, 'error' => 'Erreur lors de la génération du menu.'], 500);
        }

        $this->show($menuId);
    }

    /**
     * Sauvegarde un menu construit/modifié à la main (côté client).
     * Si un menu_id est fourni → on met à jour ; sinon → on crée un nouveau menu.
     * Le "upsert" (INSERT … ON DUPLICATE KEY UPDATE) écrase le repas d'un créneau
     * existant ou en insère un nouveau.
     */
    private function store(array $body): void
    {
        $userId = (int) AuthMiddleware::currentUserId();
        $menuId = isset($body['menu_id']) ? (int) $body['menu_id'] : null;
        $budget = (float) ($body['budget'] ?? 0);
        $persons = (int) ($body['persons'] ?? 2);
        $dietary = $this->sanitizeDietary((string) ($body['dietary'] ?? 'all'));
        $slots = $body['slots'] ?? [];

        if ($menuId) {
            $chk = $this->pdo->prepare('SELECT id FROM weekly_menus WHERE id = :id AND user_id = :uid');
            $chk->execute([':id' => $menuId, ':uid' => $userId]);
            if (!$chk->fetch()) {
                Api::json(['success' => false, 'error' => 'Menu introuvable'], 404);
            }
        } else {
            $stmt = $this->pdo->prepare(
                'INSERT INTO weekly_menus (user_id, week_start, budget, persons, dietary) VALUES (:uid, :ws, :budget, :persons, :dietary)'
            );
            $stmt->execute([
                ':uid' => $userId, ':ws' => $this->currentWeekMonday(),
                ':budget' => $budget, ':persons' => $persons, ':dietary' => $dietary,
            ]);
            $menuId = (int) $this->pdo->lastInsertId();
        }

        $upsert = $this->pdo->prepare(
            'INSERT INTO menu_meals (menu_id, day_index, meal_type, recipe_id)
             VALUES (:mid, :day, :type, :rid)
             ON DUPLICATE KEY UPDATE recipe_id = VALUES(recipe_id)'
        );
        $totalCost = 0.0;
        foreach ($slots as $slot) {
            $recipeId = isset($slot['recipe_id']) ? (int) $slot['recipe_id'] : null;
            $upsert->execute([
                ':mid' => $menuId, ':day' => (int) $slot['day_index'],
                ':type' => $slot['meal_type'], ':rid' => $recipeId,
            ]);
            if ($recipeId) {
                $totalCost += $this->getRecipeCost($recipeId);
            }
        }

        $this->pdo->prepare('UPDATE weekly_menus SET total_cost = :cost WHERE id = :id')
            ->execute([':cost' => $totalCost, ':id' => $menuId]);

        Api::json(['success' => true, 'message' => 'Menu sauvegardé', 'data' => ['menu_id' => $menuId]], 201);
    }

    private function destroy(int $id): void
    {
        $userId = (int) AuthMiddleware::currentUserId();
        $stmt = $this->pdo->prepare('DELETE FROM weekly_menus WHERE id = :id AND user_id = :uid');
        $stmt->execute([':id' => $id, ':uid' => $userId]);
        if ($stmt->rowCount() === 0) {
            Api::json(['success' => false, 'error' => 'Menu introuvable'], 404);
        }
        Api::json(['success' => true, 'message' => 'Menu supprimé']);
    }

    /**
     * Sélectionne les recettes des 21 créneaux de la semaine.
     *
     * Logique : on récupère toutes les recettes compatibles avec le régime
     * (en priorisant celles de l'utilisateur via ORDER BY user_id, puis RAND()).
     * On les range par type de repas, puis on remplit chaque jour en tournant
     * dans le "pool" (modulo) pour varier les plats sur la semaine.
     *
     * @return array Tableau [jour => ['breakfast'=>recette, 'lunch'=>…, 'dinner'=>…]]
     */
    private function pickRecipesForWeek(int $userId, string $dietary): array
    {
        $week = [];
        $types = ['breakfast', 'lunch', 'dinner'];
        // On prend les recettes de l'utilisateur ET du compte système, filtrées
        // par régime. RAND() = ordre aléatoire → menus différents à chaque fois.
        $stmt = $this->pdo->prepare(
            'SELECT id, name, meal_type, estimated_cost, calories, protein, dietary, user_id
             FROM recipes
             WHERE user_id IN (:uid, :sys)
               AND (:dietary = \'all\' OR dietary = :dietary_f OR dietary = \'all\')
             ORDER BY (user_id = :uid2) DESC, RAND()'
        );
        $stmt->execute([
            ':uid' => $userId, ':uid2' => $userId,
            ':sys' => (int) SYSTEM_USER_ID,
            ':dietary' => $dietary, ':dietary_f' => $dietary,
        ]);
        $all = $stmt->fetchAll();

        // On regroupe les recettes par créneau (petit-déj / déjeuner / dîner).
        $byType = ['breakfast' => [], 'lunch' => [], 'dinner' => []];
        foreach ($all as $r) {
            $byType[$r['meal_type']][] = $r;
        }

        // Pour chaque jour, on choisit une recette de chaque type. Le modulo (%)
        // fait tourner l'index dans le pool → on évite de répéter le même plat.
        for ($day = 0; $day < 7; $day++) {
            $week[$day] = [];
            foreach ($types as $type) {
                $pool = $byType[$type];
                $index = $day % max(1, count($pool));   // max(1,…) évite la division par 0
                $week[$day][$type] = $pool[$index] ?? null;
            }
        }
        return $week;
    }

    /** Compte le nombre de créneaux réellement remplis (recette non nulle). */
    private function countSlots(array $week): int
    {
        $n = 0;
        foreach ($week as $meals) {
            foreach ($meals as $r) {
                if ($r !== null) $n++;
            }
        }
        return $n;
    }

    /** Additionne le coût estimé de tous les repas de la semaine. */
    private function computeTotalCost(array $week): float
    {
        $total = 0.0;
        foreach ($week as $meals) {
            foreach ($meals as $r) {
                if ($r !== null) $total += (float) $r['estimated_cost'];
            }
        }
        return round($total, 2);
    }

    /** Récupère le coût d'une recette par son id (0 si introuvable). */
    private function getRecipeCost(int $recipeId): float
    {
        $stmt = $this->pdo->prepare('SELECT estimated_cost FROM recipes WHERE id = :id');
        $stmt->execute([':id' => $recipeId]);
        $row = $stmt->fetch();
        return $row ? (float) $row['estimated_cost'] : 0.0;
    }

    private const DAY_LABELS = ['Lundi','Mardi','Mercredi','Jeudi','Vendredi','Samedi','Dimanche'];

    /**
     * Transforme la liste plate des repas (issue de la BDD) en une grille de
     * 7 jours, chaque jour ayant ses 3 créneaux. Format pratique pour l'affichage.
     */
    private function buildWeekGrid(array $slots): array
    {
        $days = [];
        for ($i = 0; $i < 7; $i++) {
            $days[$i] = [
                'day_index' => $i, 'day_label' => self::DAY_LABELS[$i],
                'breakfast' => null, 'lunch' => null, 'dinner' => null,
            ];
        }
        foreach ($slots as $slot) {
            $d = (int) $slot['day_index'];
            $type = (string) $slot['meal_type'];
            if (!isset($days[$d])) continue;
            $days[$d][$type] = [
                'recipe_id'      => $slot['recipe_id'],
                'name'           => $slot['recipe_name'],
                'prep_time'      => $slot['prep_time'],
                'estimated_cost' => $slot['estimated_cost'],
                'calories'       => $slot['calories'],
                'protein'        => $slot['protein'],
                'dietary'        => $slot['dietary'],
                'ingredients'    => $slot['ingredients_list'],
            ];
        }
        return array_values($days);
    }

    /** Prépare les données puis délègue le bilan nutritionnel à NutritionService. */
    private function computeNutrition(array $slots): array
    {
        $days = [];
        foreach ($slots as $slot) {
            if ($slot['recipe_id'] === null) continue;
            $d = (int) $slot['day_index'];
            $type = (string) $slot['meal_type'];
            $days[$d]['meals'][$type] = [
                'estimated_cost' => $slot['estimated_cost'],
                'calories'       => $slot['calories'],
                'protein'        => $slot['protein'],
            ];
        }
        return NutritionService::weeklyReport(array_values($days));
    }

    /** Renvoie la date (YYYY-MM-DD) du lundi de la semaine en cours. */
    private function currentWeekMonday(): string
    {
        $dow = (int) date('N');   // N = jour de la semaine, 1 (lundi) à 7 (dimanche)
        return date('Y-m-d', strtotime('-' . ($dow - 1) . ' days'));
    }

    /**
     * Sécurise la valeur de régime : on n'accepte qu'une valeur de la liste
     * blanche, sinon on retombe sur 'all'. Évite d'injecter n'importe quoi en BDD.
     */
    private function sanitizeDietary(string $value): string
    {
        $allowed = ['all', 'vegetarian', 'vegan', 'no-pork'];
        return in_array($value, $allowed, true) ? $value : 'all';
    }
}
