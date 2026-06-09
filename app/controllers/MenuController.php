<?php
/**
 * app\controllers\menuController
 * 
 * la génération par défaut generate
 *   1. Pioche dans les recettes de l'utilisateur connecté.
 *   2. Complète avec les recettes du compte système (SYSTEM_USER_ID = 1)
 *      pour les créneaux sans recette correspondante.
 *   3. Respecte le filtre dietary et le budget.
 *   4. Retourne un tableau JSON structuré (7 jours × 3 créneaux).
 */

declare(strict_types=1);
namespace app\controllers;

use app\core\Database;

class MenuController
{
    private \PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getInstance()->getConnection();
    }


    // Routage interne

    /**
     * Point d'entrée appelé par le front controller (index.php).
     */
    public function handle(string $method, array $segments, array $body): void
    {
        $id = isset($segments[0]) && is_numeric($segments[0])
            ? (int) $segments[0]
            : null;

        $action = $segments[0] ?? null;

        match (true) {
            $method === 'GET'  && $id !== null           => $this->show($id),
            $method === 'GET'                            => $this->index(),
            $method === 'POST' && $action === 'generate' => $this->generateDefault($body),
            $method === 'POST'                           => $this->store($body),
            $method === 'DELETE' && $id !== null         => $this->destroy($id),
            default => $this->respond(['error' => 'Route non trouvée'], 404),
        };
    }

    
    // GET /api/menus
    
    private function index(): void
    {
        $userId = $this->requireAuth();

        $stmt = $this->pdo->prepare(
            'SELECT id, week_start, budget, persons, dietary, total_cost, created_at
             FROM   weekly_menus
             WHERE  user_id = :uid
             ORDER  BY week_start DESC
             LIMIT  20'
        );
        $stmt->execute([':uid' => $userId]);

        $this->respond(['menus' => $stmt->fetchAll()]);
    }

    // GET /api/menus/{id}

    private function show(int $id): void
    {
        $userId = $this->requireAuth();

        // En-tête du menu
        $stmt = $this->pdo->prepare(
            'SELECT id, week_start, budget, persons, dietary, total_cost, created_at
             FROM   weekly_menus
             WHERE  id = :id AND user_id = :uid'
        );
        $stmt->execute([':id' => $id, ':uid' => $userId]);
        $menu = $stmt->fetch();

        if (!$menu) {
            $this->respond(['error' => 'Menu introuvable'], 404);
            return;
        }

        // Slots : 7 jours × 3 créneaux avec détail recette
        $stmt = $this->pdo->prepare(
            'SELECT
               mm.day_index,
               mm.meal_type,
               r.id            AS recipe_id,
               r.name          AS recipe_name,
               r.prep_time,
               r.estimated_cost,
               r.calories,
               r.protein,
               r.dietary,
               rs.ingredients_list
             FROM   menu_meals mm
             LEFT JOIN recipes       r  ON mm.recipe_id = r.id
             LEFT JOIN recipe_summary rs ON r.id         = rs.id
             WHERE  mm.menu_id = :mid
             ORDER  BY mm.day_index, mm.meal_type'
        );
        $stmt->execute([':mid' => $id]);
        $slots = $stmt->fetchAll();

        // Mise en forme : tableau indexé [day][meal_type]
        $days = $this->buildWeekGrid($slots);

        // Résumé nutritionnel hebdomadaire
        $nutrition = $this->computeNutrition($slots);

        $this->respond([
            'menu'      => $menu,
            'days'      => $days,
            'nutrition' => $nutrition,
        ]);
    }

    
    // POST /api/menus/generate  — Génération par défaut
    

    /**
     * Corps attendu (JSON) :
     * {
     *   "budget":   100.00,   // optionnel, défaut 100
     *   "persons":  2,        // optionnel, défaut 2
     *   "dietary":  "all"     // optionnel : all | vegetarian | vegan | no-pork
     * }
     *
     * La fonction pioche directement dans la base sans procédure stockée,
     * afin de rester compatible avec une exécution via PDO (pas de CLI mysql).
     */
    private function generateDefault(array $body): void
    {
        $userId  = $this->requireAuth();
        $budget  = isset($body['budget'])  ? (float)  $body['budget']  : 100.00;
        $persons = isset($body['persons']) ? (int)    $body['persons'] : 2;
        $dietary = $this->sanitizeDietary($body['dietary'] ?? 'all');

        // Lundi de la semaine courante
        $weekStart = $this->currentWeekMonday();

        // Récupère toutes les recettes disponibles (user + système),
        // groupées par créneau, en privilégiant les recettes propres à l'user.
        $slots = $this->pickRecipesForWeek($userId, $dietary);

        if ($this->countSlots($slots) === 0) {
            $this->respond([
                'error' => 'Aucune recette disponible pour générer un menu. '
                         . 'Ajoutez des recettes ou vérifiez vos préférences alimentaires.',
            ], 422);
            return;
        }

        // Calcul du coût estimé
        $totalCost = $this->computeTotalCost($slots);

        // Persistance en base
        $this->pdo->beginTransaction();
        try {
            // En-tête weekly_menus
            $stmt = $this->pdo->prepare(
                'INSERT INTO weekly_menus
                   (user_id, week_start, budget, persons, dietary, total_cost)
                 VALUES (:uid, :ws, :budget, :persons, :dietary, :cost)'
            );
            $stmt->execute([
                ':uid'     => $userId,
                ':ws'      => $weekStart,
                ':budget'  => $budget,
                ':persons' => $persons,
                ':dietary' => $dietary,
                ':cost'    => $totalCost,
            ]);
            $menuId = (int) $this->pdo->lastInsertId();

            // Insertion des 21 slots
            $insStmt = $this->pdo->prepare(
                'INSERT INTO menu_meals (menu_id, day_index, meal_type, recipe_id)
                 VALUES (:mid, :day, :type, :rid)'
            );
            foreach ($slots as $dayIndex => $meals) {
                foreach ($meals as $mealType => $recipe) {
                    if ($recipe === null) continue;
                    $insStmt->execute([
                        ':mid'  => $menuId,
                        ':day'  => $dayIndex,
                        ':type' => $mealType,
                        ':rid'  => $recipe['id'],
                    ]);
                }
            }

            $this->pdo->commit();
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            $this->respond(['error' => 'Erreur lors de la génération du menu.'], 500);
            return;
        }

        // Retourne le menu complet
        $this->show($menuId);
    }


    // POST /api/menus  — Sauvegarde / mise à jour manuelle


    private function store(array $body): void
    {
        $userId = $this->requireAuth();

        $menuId  = isset($body['menu_id'])  ? (int)   $body['menu_id']  : null;
        $budget  = isset($body['budget'])   ? (float) $body['budget']   : 0.0;
        $persons = isset($body['persons'])  ? (int)   $body['persons']  : 2;
        $dietary = $this->sanitizeDietary($body['dietary'] ?? 'all');
        $slots   = $body['slots'] ?? [];

        if ($menuId) {
            // Vérifie que le menu appartient à l'user
            $stmt = $this->pdo->prepare(
                'SELECT id FROM weekly_menus WHERE id = :id AND user_id = :uid'
            );
            $stmt->execute([':id' => $menuId, ':uid' => $userId]);
            if (!$stmt->fetch()) {
                $this->respond(['error' => 'Menu introuvable'], 404);
                return;
            }
        } else {
            // Nouveau menu
            $stmt = $this->pdo->prepare(
                'INSERT INTO weekly_menus (user_id, week_start, budget, persons, dietary)
                 VALUES (:uid, :ws, :budget, :persons, :dietary)'
            );
            $stmt->execute([
                ':uid'     => $userId,
                ':ws'      => $this->currentWeekMonday(),
                ':budget'  => $budget,
                ':persons' => $persons,
                ':dietary' => $dietary,
            ]);
            $menuId = (int) $this->pdo->lastInsertId();
        }

        // Upsert des slots
        $upsert = $this->pdo->prepare(
            'INSERT INTO menu_meals (menu_id, day_index, meal_type, recipe_id)
             VALUES (:mid, :day, :type, :rid)
             ON DUPLICATE KEY UPDATE recipe_id = VALUES(recipe_id)'
        );
        $totalCost = 0.0;
        foreach ($slots as $slot) {
            $recipeId = isset($slot['recipe_id']) ? (int) $slot['recipe_id'] : null;
            $upsert->execute([
                ':mid'  => $menuId,
                ':day'  => (int) $slot['day_index'],
                ':type' => $slot['meal_type'],
                ':rid'  => $recipeId,
            ]);
            if ($recipeId) {
                $cost = $this->getRecipeCost($recipeId);
                $totalCost += $cost;
            }
        }

        // Met à jour le coût total
        $this->pdo->prepare(
            'UPDATE weekly_menus SET total_cost = :cost WHERE id = :id'
        )->execute([':cost' => $totalCost, ':id' => $menuId]);

        $this->respond(['message' => 'Menu sauvegardé', 'menu_id' => $menuId], 201);
    }

    
    // DELETE /api/menus/{id}
    

    private function destroy(int $id): void
    {
        $userId = $this->requireAuth();

        $stmt = $this->pdo->prepare(
            'DELETE FROM weekly_menus WHERE id = :id AND user_id = :uid'
        );
        $stmt->execute([':id' => $id, ':uid' => $userId]);

        if ($stmt->rowCount() === 0) {
            $this->respond(['error' => 'Menu introuvable'], 404);
            return;
        }

        $this->respond(['message' => 'Menu supprimé']);
    }

    
    // Helpers — Génération
    

    /**
     * Sélectionne aléatoirement une recette par créneau pour 7 jours.
     *
     * Priorité :
     *  1. Recettes propres à l'utilisateur (user_id = $userId)
     *  2. Recettes du compte système (user_id = SYSTEM_USER_ID)
     *
     * Retourne un tableau [0..6][breakfast|lunch|dinner] => recette|null
     */
    private function pickRecipesForWeek(int $userId, string $dietary): array
    {
        $week  = [];
        $types = ['breakfast', 'lunch', 'dinner'];

        // Charge toutes les recettes éligibles une seule fois (évite N×3×7 requêtes)
        $stmt = $this->pdo->prepare(
            'SELECT id, name, meal_type, estimated_cost, calories, protein, dietary, user_id
             FROM   recipes
             WHERE  user_id IN (:uid, :sys)
               AND  (:dietary = \'all\' OR dietary = :dietary OR dietary = \'all\')
             ORDER  BY (user_id = :uid) DESC, RAND()'
        );
        $stmt->execute([
            ':uid'     => $userId,
            ':sys'     => SYSTEM_USER_ID,
            ':dietary' => $dietary,
        ]);
        $all = $stmt->fetchAll();

        // Indexe par meal_type pour un accès rapide
        $byType = ['breakfast' => [], 'lunch' => [], 'dinner' => []];
        foreach ($all as $r) {
            $byType[$r['meal_type']][] = $r;
        }

        // Attribue une recette par créneau pour chacun des 7 jours
        for ($day = 0; $day < 7; $day++) {
            $week[$day] = [];
            foreach ($types as $type) {
                $pool = $byType[$type];
                // Rotation simple : on dépile depuis la tête (déjà mélangé par RAND())
                // pour éviter de servir la même recette chaque jour.
                $index = $day % max(1, count($pool));
                $week[$day][$type] = $pool[$index] ?? null;
            }
        }

        return $week;
    }

    /** Compte le nombre de slots non nuls. */
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

    /** Somme les coûts estimés de toutes les recettes. */
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

    /** Retourne le coût d'une recette depuis la DB. */
    private function getRecipeCost(int $recipeId): float
    {
        $stmt = $this->pdo->prepare(
            'SELECT estimated_cost FROM recipes WHERE id = :id'
        );
        $stmt->execute([':id' => $recipeId]);
        $row = $stmt->fetch();
        return $row ? (float) $row['estimated_cost'] : 0.0;
    }

    
    // Helpers — Mise en forme

    private const DAY_LABELS = [
        'Lundi','Mardi','Mercredi','Jeudi','Vendredi','Samedi','Dimanche'
    ];

    /**
     * Construit un tableau [0..6] avec label du jour et recettes par créneau.
     *
     * @param  array $slots  Lignes issues de la jointure menu_meals × recipes
     * @return array
     */
    private function buildWeekGrid(array $slots): array
    {
        $days = [];
        for ($i = 0; $i < 7; $i++) {
            $days[$i] = [
                'day_index' => $i,
                'day_label' => self::DAY_LABELS[$i],
                'breakfast' => null,
                'lunch'     => null,
                'dinner'    => null,
            ];
        }

        foreach ($slots as $slot) {
            $d    = (int)    $slot['day_index'];
            $type = (string) $slot['meal_type'];

            if (isset($days[$d])) {
                $days[$d][$type] = [
                    'recipe_id'       => $slot['recipe_id'],
                    'name'            => $slot['recipe_name'],
                    'prep_time'       => $slot['prep_time'],
                    'estimated_cost'  => $slot['estimated_cost'],
                    'calories'        => $slot['calories'],
                    'protein'         => $slot['protein'],
                    'dietary'         => $slot['dietary'],
                    'ingredients'     => $slot['ingredients_list'],
                ];
            }
        }

        return array_values($days);
    }

    /**
     * Calcule les totaux nutritionnels hebdomadaires.
     */
    private function computeNutrition(array $slots): array
    {
        $totalCalories = 0.0;
        $totalProtein  = 0.0;
        $totalCost     = 0.0;
        $mealCount     = 0;

        foreach ($slots as $slot) {
            if ($slot['recipe_id'] === null) continue;
            $totalCalories += (float) $slot['calories'];
            $totalProtein  += (float) $slot['protein'];
            $totalCost     += (float) $slot['estimated_cost'];
            $mealCount++;
        }

        return [
            'total_calories'   => round($totalCalories, 1),
            'total_protein'    => round($totalProtein, 1),
            'total_cost'       => round($totalCost, 2),
            'calories_per_day' => $mealCount > 0 ? round($totalCalories / 7, 1) : 0,
            'cost_per_meal'    => $mealCount > 0 ? round($totalCost / $mealCount, 2) : 0,
            'meal_count'       => $mealCount,
        ];
    }

    
    // Helpers — Utilitaires
    

    /** Retourne le lundi de la semaine courante au format Y-m-d. */
    private function currentWeekMonday(): string
    {
        $dow = (int) date('N');   // 1 = lundi, 7 = dimanche
        return date('Y-m-d', strtotime('-' . ($dow - 1) . ' days'));
    }

    /** Valide et normalise la préférence alimentaire. */
    private function sanitizeDietary(string $value): string
    {
        $allowed = ['all', 'vegetarian', 'vegan', 'no-pork'];
        return in_array($value, $allowed, true) ? $value : 'all';
    }

    /**
     * Vérifie la session et retourne l'user_id connecté.
     * Arrête l'exécution avec 401 si non authentifié.
     */
    private function requireAuth(): int
    {
        if (empty($_SESSION['user_id'])) {
            $this->respond(['error' => 'Non authentifié'], 401);
            exit;
        }
        return (int) $_SESSION['user_id'];
    }

    /**
     * Envoie une réponse JSON et arrête l'exécution.
     */
    private function respond(array $data, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
}
