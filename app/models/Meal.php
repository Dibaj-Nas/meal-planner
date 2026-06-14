<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use App\Core\Security;
use PDO;

class Meal
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
    }

    public function allRecipesByUser(int $userId, string $mealType = '', string $dietary = ''): array
    {
        $sql = 'SELECT r.*, GROUP_CONCAT(ri.ingredient_name ORDER BY ri.ingredient_name SEPARATOR \', \') AS ingredients_list
                FROM recipes r
                LEFT JOIN recipe_ingredients ri ON ri.recipe_id = r.id
                WHERE r.user_id IN (:uid, :sys)';
        $params = [':uid' => $userId, ':sys' => (int) SYSTEM_USER_ID];

        if ($mealType !== '') {
            $sql .= ' AND r.meal_type = :meal_type';
            $params[':meal_type'] = $mealType;
        }
        if ($dietary !== '' && $dietary !== 'all') {
            $sql .= ' AND (r.dietary = :dietary_f OR r.dietary = \'all\')';
            $params[':dietary_f'] = $dietary;
        }

        $sql .= ' GROUP BY r.id ORDER BY r.meal_type, r.name';
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

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

            $ingredients = $data['ingredients'] ?? [];
            if (is_string($ingredients)) {
                $ingredients = array_filter(array_map('trim', explode(',', $ingredients)));
            }
            if (!empty($ingredients)) {
                $ins = $this->db->prepare(
                    'INSERT INTO recipe_ingredients (recipe_id, ingredient_name, quantity, unit) VALUES (?, ?, 1, \'piece\')'
                );
                foreach ($ingredients as $name) {
                    $ins->execute([$recipeId, Security::sanitize($name)]);
                }
            }

            $this->db->commit();
            return $recipeId;
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function deleteRecipe(int $id, int $userId): bool
    {
        $stmt = $this->db->prepare('DELETE FROM recipes WHERE id = ? AND user_id = ?');
        return $stmt->execute([$id, $userId]);
    }

    public function allMenusByUser(int $userId): array
    {
        $stmt = $this->db->prepare(
            'SELECT id, budget, persons, dietary, total_cost, week_start, created_at
             FROM weekly_menus WHERE user_id = ? ORDER BY created_at DESC'
        );
        $stmt->execute([$userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function deleteMenu(int $menuId, int $userId): bool
    {
        $stmt = $this->db->prepare('DELETE FROM weekly_menus WHERE id = ? AND user_id = ?');
        return $stmt->execute([$menuId, $userId]);
    }
}
