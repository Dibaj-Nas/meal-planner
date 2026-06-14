<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use App\Core\Security;
use PDO;

class User
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT id, firstname, lastname, email, created_at
             FROM users WHERE id = ? LIMIT 1'
        );
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function findByEmail(string $email): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT id, firstname, lastname, email, password, created_at
             FROM users WHERE email = ? LIMIT 1'
        );
        $stmt->execute([strtolower(trim($email))]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function create(array $data): int|false
    {
        if ($this->findByEmail($data['email'])) {
            return false;
        }

        $stmt = $this->db->prepare(
            'INSERT INTO users (firstname, lastname, email, password)
             VALUES (:firstname, :lastname, :email, :password)'
        );
        $stmt->execute([
            ':firstname' => Security::sanitize($data['firstname']),
            ':lastname'  => Security::sanitize($data['lastname']),
            ':email'     => strtolower(trim($data['email'])),
            ':password'  => Security::hashPassword($data['password']),
        ]);

        $userId = (int) $this->db->lastInsertId();

        if ($userId) {
            try {
                $this->initDefaultSettings($userId);
                $this->copySystemSeedData($userId);
            } catch (\Throwable $e) {
                error_log('[User::create] Seed copy failed for user ' . $userId . ': ' . $e->getMessage());
            }
        }

        return $userId ?: false;
    }

    public function getProfile(int $id): ?array
    {
        return $this->findById($id);
    }

    public function updateProfile(int $id, array $data): array
    {
        $existing = $this->findByEmail($data['email']);
        if ($existing && (int) $existing['id'] !== $id) {
            return ['success' => false, 'error' => 'Cette adresse e-mail est déjà utilisée.', 'message' => 'Cette adresse e-mail est déjà utilisée.'];
        }

        $ok = $this->db->prepare(
            'UPDATE users SET firstname = :firstname, lastname = :lastname, email = :email WHERE id = :id'
        )->execute([
            ':firstname' => Security::sanitize($data['firstname']),
            ':lastname'  => Security::sanitize($data['lastname']),
            ':email'     => strtolower(trim($data['email'])),
            ':id'        => $id,
        ]);

        return ['success' => $ok, 'message' => $ok ? 'Profil mis à jour.' : 'Erreur lors de la mise à jour.'];
    }

    public function changePassword(int $id, string $current, string $new): array
    {
        $stmt = $this->db->prepare('SELECT password FROM users WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row || !Security::verifyPassword($current, $row['password'])) {
            return ['success' => false, 'message' => 'Mot de passe actuel incorrect.'];
        }

        $ok = $this->db->prepare('UPDATE users SET password = :hash WHERE id = :id')->execute([
            ':hash' => Security::hashPassword($new),
            ':id'   => $id,
        ]);

        return ['success' => $ok, 'message' => $ok ? 'Mot de passe modifié.' : 'Erreur.'];
    }

    public function getUserSettings(int $userId): array
    {
        $stmt = $this->db->prepare(
            'SELECT default_persons, default_dietary, default_budget FROM user_settings WHERE user_id = ? LIMIT 1'
        );
        $stmt->execute([$userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        $labels = [
            'all' => 'Tous', 'vegetarian' => 'Végétarien',
            'vegan' => 'Vegan', 'no-pork' => 'Sans Porc',
        ];

        if (!$row) {
            return [
                'default_persons' => 2,
                'default_dietary' => 'all',
                'default_budget'  => 100.00,
                'dietary_pref'    => 'Tous',
            ];
        }

        $row['dietary_pref'] = $labels[$row['default_dietary'] ?? 'all'] ?? 'Tous';
        return $row;
    }

    public function saveUserSettings(int $userId, array $settings): array
    {
        $dietaryMap = [
            'Tous' => 'all', 'Végétarien' => 'vegetarian', 'Vegan' => 'vegan',
            'vegetarian' => 'vegetarian', 'vegan' => 'vegan', 'no-pork' => 'no-pork', 'all' => 'all',
        ];
        $dietary = $settings['dietary_pref'] ?? $settings['default_dietary'] ?? 'all';
        $dietary = $dietaryMap[$dietary] ?? $dietary;

        $ok = $this->db->prepare(
            'INSERT INTO user_settings (user_id, default_persons, default_dietary, default_budget)
             VALUES (:uid, :persons, :dietary, :budget)
             ON DUPLICATE KEY UPDATE
               default_persons = VALUES(default_persons),
               default_dietary = VALUES(default_dietary),
               default_budget  = VALUES(default_budget)'
        )->execute([
            ':uid'     => $userId,
            ':persons' => (int) ($settings['default_persons'] ?? 2),
            ':dietary' => $dietary,
            ':budget'  => (float) ($settings['default_budget'] ?? 100),
        ]);

        return ['success' => $ok, 'message' => $ok ? 'Paramètres enregistrés.' : 'Erreur.'];
    }

    public function getSavedMenus(int $userId): array
    {
        $stmt = $this->db->prepare(
            'SELECT id, week_start, budget, persons, dietary, total_cost, created_at
             FROM weekly_menus WHERE user_id = ? ORDER BY created_at DESC'
        );
        $stmt->execute([$userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function deleteWeeklyMenu(int $userId, int $menuId): array
    {
        $stmt = $this->db->prepare('DELETE FROM weekly_menus WHERE id = ? AND user_id = ?');
        $stmt->execute([$menuId, $userId]);
        $ok = $stmt->rowCount() > 0;
        return ['success' => $ok, 'message' => $ok ? 'Menu supprimé.' : 'Menu introuvable.'];
    }

    public function getFavorites(int $userId): array
    {
        $stmt = $this->db->prepare(
            'SELECT r.id, r.name, r.meal_type, r.prep_time, r.estimated_cost, r.calories, r.dietary, f.created_at
             FROM favorites f
             JOIN recipes r ON r.id = f.recipe_id
             WHERE f.user_id = ?
             ORDER BY f.created_at DESC'
        );
        $stmt->execute([$userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function removeFavorite(int $userId, int $recipeId): array
    {
        $ok = $this->db->prepare(
            'DELETE FROM favorites WHERE user_id = ? AND recipe_id = ?'
        )->execute([$userId, $recipeId]);
        return ['success' => $ok, 'message' => $ok ? 'Favori retiré.' : 'Erreur.'];
    }

    public function saveResetToken(int $userId, string $token): void
    {
        $this->db->prepare('DELETE FROM password_resets WHERE user_id = ?')->execute([$userId]);
        $this->db->prepare(
            'INSERT INTO password_resets (user_id, token, expires_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 1 HOUR))'
        )->execute([$userId, hash('sha256', $token)]);
    }

    public function findByResetToken(string $token): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT u.id, u.firstname, u.lastname, u.email
             FROM password_resets pr
             JOIN users u ON u.id = pr.user_id
             WHERE pr.token = ? AND pr.expires_at > NOW() AND pr.used = 0
             LIMIT 1'
        );
        $stmt->execute([hash('sha256', $token)]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    private function initDefaultSettings(int $userId): void
    {
        $this->db->prepare(
            'INSERT IGNORE INTO user_settings (user_id) VALUES (?)'
        )->execute([$userId]);
    }

    /** Copie ingrédients et recettes du compte système vers le nouvel utilisateur. */
    private function copySystemSeedData(int $userId): void
    {
        if (!defined('SYSTEM_USER_ID')) {
            return;
        }
        $sysId = (int) SYSTEM_USER_ID;
        if ($sysId === $userId) {
            return;
        }

        $this->db->prepare(
            'INSERT INTO ingredients (user_id, name, price, unit, calories, protein, category)
             SELECT ?, name, price, unit, calories, protein, category FROM ingredients WHERE user_id = ?'
        )->execute([$userId, $sysId]);

        $recipes = $this->db->prepare('SELECT id, name, meal_type, prep_time, dietary, estimated_cost, calories, protein FROM recipes WHERE user_id = ?');
        $recipes->execute([$sysId]);
        foreach ($recipes->fetchAll(PDO::FETCH_ASSOC) as $recipe) {
            $ins = $this->db->prepare(
                'INSERT INTO recipes (user_id, name, meal_type, prep_time, dietary, estimated_cost, calories, protein)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $ins->execute([
                $userId, $recipe['name'], $recipe['meal_type'], $recipe['prep_time'],
                $recipe['dietary'], $recipe['estimated_cost'], $recipe['calories'], $recipe['protein'],
            ]);
            $newId = (int) $this->db->lastInsertId();
            $ri = $this->db->prepare(
                'INSERT INTO recipe_ingredients (recipe_id, ingredient_name, quantity, unit)
                 SELECT ?, ingredient_name, quantity, unit FROM recipe_ingredients WHERE recipe_id = ?'
            );
            $ri->execute([$newId, $recipe['id']]);
        }
    }
}
