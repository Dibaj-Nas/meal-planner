<?php

use app\core\database;
use App\Core\Database as CoreDatabase;
use app\core\security;
use PDO;

/**
 * meal.php modèle recette + menus hebdomadires
 * 
 * tables concernées :
 *  - recipes : recettes (nom, type, temps, régime, coût, calories)
 *  - recipe_ingredients : liaisons entre recettes et ingrédient
 *  - weekly_menus : menus sauvegardés (budget, personnes, régime)
 *  - menu_meals : liaison menu <-> recette (jour + type de repas)
 */
class meal
{
    private PDO $db;

    public function __construct()
    {
        $this->db = database::getInstance()->getConnection();
    }

    /**
     * recettes 
     * 
     * retourne toutes les recettes d'un utilisateur.
     */
    public function allRecipesByUser(int $userId, string $mealType = '', string $dietary = ''): array
    {
        $sql    = 'SELECT * FROM recipes WHERE user_id = :user_id';
        $params = [':user_id' => $userId];

        if ($mealType) {
            $sql .= ' AND meal_type = :meal_type';
            $params[':meal_type'] = $mealType;
        }

        if ($dietary && $dietary !== 'all') {
            $sql .= ' AND (dietary = :dietary OR dietary = "all")';
            $params[':dietary'] = $dietary;
        }

        $sql .= ' ORDER BY meal_type, name';

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    // trouver une recette pat Id et utilisateur
    public function findRecipeByIdAndUser(int $id, int $userId): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT r.*, GROUP_CONCAT(i.name SEPARATOR ", ") AS ingredients_list
             FROM recipes r
             LEFT JOIN recipe_ingredients ri ON ri.recipe_id = r.id
             LEFT JOIN ingredients i ON i.id = ri.ingredient_id
             WHERE r.id = ? AND r.user_id = ?
             GROUP BY r.id
             LIMIT 1'
        );
        $stmt->execute([$id, $userId]);
        return $stmt->fetch() ?: null;
    }

    // créer une nouvelle recette 
    public function createRecipe(int $userId, array $data): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO recipes
               (user_id, name, meal_type, prep_time, dietary, estimated_cost,
                calories, protein, ingredients_list, created_at)
             VALUES
               (:user_id, :name, :meal_type, :prep_time, :dietary, :cost,
                :calories, :protein, :ingredients_list, NOW())'
        );

        $stmt->execute([
            ':user_id'          => $userId,
            ':name'             => Security::sanitize($data['name']),
            ':meal_type'        => $data['meal_type'] ?? 'dinner',
            ':prep_time'        => (int)   ($data['prep_time']       ?? 30),
            ':dietary'          => $data['dietary']   ?? 'all',
            ':cost'             => (float) ($data['estimated_cost']  ?? 1.50),
            ':calories'         => (float) ($data['calories']        ?? 300),
            ':protein'          => (float) ($data['protein']         ?? 10),
            ':ingredients_list' => Security::sanitize($data['ingredients_list'] ?? ''),
        ]);

        return (int) $this->db->lastInsertId();
    }

    // supprimer une recette appartenant à un utilisateur
    public function deleteRecipe(int $id, int $userId): bool
    {
        $stmt = $this->db->prepare(
            'DELETE FROM recipes WHERE id = ? AND user_id = ?'
        );
        return $stmt->execute([$id, $userId]);
    }

    // menus hebdomadaires
    // retourne les menus d'un utilisateur (résumé)
    public function allMenusByUser(int $userId): array
    {
        $stmt = $this->db->prepare(
            'SELECT id, budget, persons, dietary, total_cost, week_start, created_at
             FROM weekly_menus WHERE user_id = ? ORDER BY created_at DESC'
        );
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }

    // charger un menu complet avec ses repas (7 jours x 3 créneaux)
    public function findMenuWithMeals(int $menuId, int $userId): ?array
    {
        /* En-tête du menu */
        $stmt = $this->db->prepare(
            'SELECT * FROM weekly_menus WHERE id = ? AND user_id = ? LIMIT 1'
        );
        $stmt->execute([$menuId, $userId]);
        $menu = $stmt->fetch();

        if (!$menu) {
            return null;
        }

        /* Repas associés */
        $stmt = $this->db->prepare(
            'SELECT mm.day_index, mm.meal_type, r.*
             FROM menu_meals mm
             JOIN recipes r ON r.id = mm.recipe_id
             WHERE mm.menu_id = ?
             ORDER BY mm.day_index, mm.meal_type'
        );
        $stmt->execute([$menuId]);
        $rows = $stmt->fetchAll();

        /* Organisation en tableau [jour][type] */
        $days = [];
        foreach ($rows as $row) {
            $days[$row['day_index']][$row['meal_type']] = $row;
        }
        $menu['days'] = $days;

        return $menu;
    }

    // sauvegarder un menu généré en base de donnée
    public function saveMenu(int $userId, array $menuData): int
    {
        /* Insertion de l'en-tête */
        $stmt = $this->db->prepare(
            'INSERT INTO weekly_menus
               (user_id, budget, persons, dietary, total_cost, week_start, created_at)
             VALUES
               (:user_id, :budget, :persons, :dietary, :total_cost, :week_start, NOW())'
        );

        $stmt->execute([
            ':user_id'    => $userId,
            ':budget'     => (float) ($menuData['budget']     ?? 0),
            ':persons'    => (int)   ($menuData['persons']    ?? 2),
            ':dietary'    => $menuData['dietary']   ?? 'all',
            ':total_cost' => (float) ($menuData['total_cost'] ?? 0),
            ':week_start' => $menuData['week_start'] ?? date('Y-m-d'),
        ]);

        $menuId = (int) $this->db->lastInsertId();

        /* Insertion des repas */
        $stmtMeal = $this->db->prepare(
            'INSERT INTO menu_meals (menu_id, recipe_id, day_index, meal_type)
             VALUES (:menu_id, :recipe_id, :day_index, :meal_type)'
        );

        foreach ($menuData['days'] as $dayIndex => $day) {
            foreach (['breakfast', 'lunch', 'dinner'] as $type) {
                $recipe = $day['meals'][$type] ?? $day[$type] ?? null;
                if ($recipe && isset($recipe['id'])) {
                    $stmtMeal->execute([
                        ':menu_id'   => $menuId,
                        ':recipe_id' => (int) $recipe['id'],
                        ':day_index' => (int) $dayIndex,
                        ':meal_type' => $type,
                    ]);
                }
            }
        }

        return $menuId;
    }

    // supprimer un menu appartenant à un utilisateur
    public function deleteMenu(int $menuId, int $userId): bool
    {
        $stmt = $this->db->prepare(
            'DELETE FROM weekly_menus WHERE id = ? AND user_id = ?'
        );
        return $stmt->execute([$menuId, $userId]);
    }
}
