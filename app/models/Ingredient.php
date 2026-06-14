<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use App\Core\Security;
use PDO;

class Ingredient
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
    }

    public function allByUser(int $userId, string $search = ''): array
    {
        if ($search !== '') {
            $stmt = $this->db->prepare(
                'SELECT * FROM ingredients WHERE user_id = ? AND name LIKE ? ORDER BY category, name'
            );
            $stmt->execute([$userId, '%' . $search . '%']);
        } else {
            $stmt = $this->db->prepare(
                'SELECT * FROM ingredients WHERE user_id = ? ORDER BY category, name'
            );
            $stmt->execute([$userId]);
        }
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findByIdAndUser(int $id, int $userId): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM ingredients WHERE id = ? AND user_id = ? LIMIT 1'
        );
        $stmt->execute([$id, $userId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function create(int $userId, array $data): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO ingredients (user_id, name, price, unit, calories, protein, category)
             VALUES (:user_id, :name, :price, :unit, :calories, :protein, :category)'
        );
        $stmt->execute([
            ':user_id'  => $userId,
            ':name'     => Security::sanitize($data['name']),
            ':price'    => (float) ($data['price'] ?? 0),
            ':unit'     => Security::sanitize($data['unit'] ?? 'piece'),
            ':calories' => (float) ($data['calories'] ?? 0),
            ':protein'  => (float) ($data['protein'] ?? 0),
            ':category' => Security::sanitize($data['category'] ?? 'other'),
        ]);
        return (int) $this->db->lastInsertId();
    }

    public function update(int $id, int $userId, array $data): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE ingredients
             SET name = :name, price = :price, unit = :unit,
                 calories = :calories, protein = :protein, category = :category
             WHERE id = :id AND user_id = :user_id'
        );
        return $stmt->execute([
            ':name'     => Security::sanitize($data['name']),
            ':price'    => (float) ($data['price'] ?? 0),
            ':unit'     => Security::sanitize($data['unit'] ?? 'piece'),
            ':calories' => (float) ($data['calories'] ?? 0),
            ':protein'  => (float) ($data['protein'] ?? 0),
            ':category' => Security::sanitize($data['category'] ?? 'other'),
            ':id'       => $id,
            ':user_id'  => $userId,
        ]);
    }

    public function delete(int $id, int $userId): bool
    {
        $stmt = $this->db->prepare('DELETE FROM ingredients WHERE id = ? AND user_id = ?');
        return $stmt->execute([$id, $userId]);
    }
}
