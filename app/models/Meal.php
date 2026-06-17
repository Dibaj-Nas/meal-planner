<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use App\Core\Security;
use PDO;

/**
 * Meal — Modèle des recettes et des menus
 * =======================================
 *
 * Gère deux tables liées :
 *   - recipes / recipe_ingredients : les recettes et leurs ingrédients
 *   - weekly_menus                 : les menus hebdomadaires sauvegardés
 *
 * Astuce récurrente : les requêtes incluent SYSTEM_USER_ID (compte "système",
 * id = 1) en plus de l'utilisateur courant, pour que chacun voie aussi les
 * recettes pré-chargées par défaut.
 */
class Meal
{
    /** Connexion PDO partagée. */
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
    }

    /**
     * Liste les recettes de l'utilisateur (+ celles du compte système),
     * avec filtres optionnels par type de repas et régime alimentaire.
     *
     * @param int    $userId   Utilisateur courant.
     * @param string $mealType Filtre : 'breakfast' | 'lunch' | 'dinner' (ou vide).
     * @param string $dietary  Filtre régime : 'vegetarian', 'vegan'… (ou 'all'/vide).
     * @return array Recettes, chacune avec sa liste d'ingrédients concaténée.
     */
    public function allRecipesByUser(int $userId, string $mealType = '', string $dietary = ''): array
    {
        // GROUP_CONCAT regroupe les ingrédients d'une recette en une seule chaîne
        // "tomate, riz, …" grâce au LEFT JOIN + GROUP BY r.id plus bas.
        $sql = 'SELECT r.*, GROUP_CONCAT(ri.ingredient_name ORDER BY ri.ingredient_name SEPARATOR \', \') AS ingredients_list
                FROM recipes r
                LEFT JOIN recipe_ingredients ri ON ri.recipe_id = r.id
                WHERE r.user_id IN (:uid, :sys)';
        // On construit la requête morceau par morceau selon les filtres reçus.
        $params = [':uid' => $userId, ':sys' => (int) SYSTEM_USER_ID];

        if ($mealType !== '') {
            $sql .= ' AND r.meal_type = :meal_type';
            $params[':meal_type'] = $mealType;
        }
        if ($dietary !== '' && $dietary !== 'all') {
            // On garde aussi les recettes 'all' (compatibles avec tous les régimes).
            $sql .= ' AND (r.dietary = :dietary_f OR r.dietary = \'all\')';
            $params[':dietary_f'] = $dietary;
        }

        $sql .= ' GROUP BY r.id ORDER BY r.meal_type, r.name';
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Récupère une recette précise (de l'utilisateur ou du compte système). */
    public function findRecipeByIdAndUser(int $id, int $userId): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT r.*, GROUP_CONCAT(ri.ingredient_name SEPARATOR \', \') AS ingredients_list
             FROM recipes r
             LEFT JOIN recipe_ingredients ri ON ri.recipe_id = r.id
             WHERE r.id = ? AND r.user_id IN (?, ?)
             GROUP BY r.id LIMIT 1'
        );
        $stmt->execute([$id, $userId, (int) SYSTEM_USER_ID]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Crée une recette ET ses ingrédients en une seule opération atomique.
     *
     * On utilise une TRANSACTION : soit tout réussit (recette + ingrédients),
     * soit rien n'est enregistré (rollBack). Cela évite une recette "orpheline"
     * sans ses ingrédients si une erreur survient au milieu.
     *
     * @return int Identifiant de la recette créée.
     */
    public function createRecipe(int $userId, array $data): int
    {
        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare(
                'INSERT INTO recipes (user_id, name, meal_type, prep_time, dietary, estimated_cost, calories, protein)
                 VALUES (:user_id, :name, :meal_type, :prep_time, :dietary, :cost, :calories, :protein)'
            );
            $stmt->execute([
                ':user_id'   => $userId,
                ':name'      => Security::sanitize($data['name']),
                ':meal_type' => $data['meal_type'] ?? 'dinner',
                ':prep_time' => (int) ($data['prep_time'] ?? 30),
                ':dietary'   => $data['dietary'] ?? 'all',
                ':cost'      => (float) ($data['estimated_cost'] ?? 1.50),
                ':calories'  => (float) ($data['calories'] ?? 300),
                ':protein'   => (float) ($data['protein'] ?? 10),
            ]);
            $recipeId = (int) $this->db->lastInsertId();

            // Les ingrédients peuvent arriver en tableau ou en texte "a, b, c".
            $ingredients = $data['ingredients'] ?? [];
            if (is_string($ingredients)) {
                $ingredients = array_filter(array_map('trim', explode(',', $ingredients)));
            }
            if (!empty($ingredients)) {
                // On insère chaque ingrédient lié à la recette qu'on vient de créer.
                $ins = $this->db->prepare(
                    'INSERT INTO recipe_ingredients (recipe_id, ingredient_name, quantity, unit) VALUES (?, ?, 1, \'piece\')'
                );
                foreach ($ingredients as $name) {
                    $ins->execute([$recipeId, Security::sanitize($name)]);
                }
            }

            $this->db->commit();   // Tout s'est bien passé → on valide la transaction.
            return $recipeId;
        } catch (\Throwable $e) {
            $this->db->rollBack();  // Une erreur → on annule TOUT.
            throw $e;
        }
    }

    /** Supprime une recette (seulement si elle appartient à l'utilisateur). */
    public function deleteRecipe(int $id, int $userId): bool
    {
        $stmt = $this->db->prepare('DELETE FROM recipes WHERE id = ? AND user_id = ?');
        return $stmt->execute([$id, $userId]);
    }

    /** Liste les menus hebdomadaires sauvegardés par l'utilisateur (récents d'abord). */
    public function allMenusByUser(int $userId): array
    {
        $stmt = $this->db->prepare(
            'SELECT id, budget, persons, dietary, total_cost, week_start, created_at
             FROM weekly_menus WHERE user_id = ? ORDER BY created_at DESC'
        );
        $stmt->execute([$userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Supprime un menu hebdomadaire (seulement s'il appartient à l'utilisateur). */
    public function deleteMenu(int $menuId, int $userId): bool
    {
        $stmt = $this->db->prepare('DELETE FROM weekly_menus WHERE id = ? AND user_id = ?');
        return $stmt->execute([$menuId, $userId]);
    }
}
