<?php
namespace App\Models;

use App\Core\Database;
use App\Core\Security;
use PDO;

/** ingredient.php -> modèle ingredient 
 * 
 * CRUD complet sur la table 'ingredients'.
 * chaque ingrédient appartient à un utilisateur (user_id)
*/
class Ingredient
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
    }

    /**
     * lecture 
     * 
     * retourne tous les ingrédients d'un utilisateur
     */
    public function allByUser(int $userId, string $search = ''): array
    {
        if ($search) {
            $stmt = $this->db->prepare(
                'SELECT * FROM ingredients
                 WHERE user_id = ? AND name LIKE ?
                 ORDER BY category, name'
            );
            $stmt->execute([$userId, '%' . $search . '%']);
        } else {
            $stmt = $this->db->prepare(
                'SELECT * FROM ingredients WHERE user_id = ? ORDER BY category, name'
            );
            $stmt->execute([$userId]);
        }
        return $stmt->fetchAll();
    }

    // trouver un ingrédient par ID, en verifiant l'appartenance.
    public function findByIdAndUser(int $id, int $userId): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM ingredients WHERE id = ? AND user_id = ? LIMIT 1'
        );
        $stmt->execute([$id, $userId]);
        return $stmt->fetch() ?: null;
    }

    /**
     * création 
     * 
     * créer un nouvel ingrédient
     */

    public function create(int $userId, array $data): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO ingredients
               (user_id, name, price_per_unit, unit, calories_per_100g, protein_per_100g, category, created_at)
             VALUES
               (:user_id, :name, :price, :unit, :calories, :protein, :category, NOW())'
        );

        $stmt->execute([
            ':user_id'  => $userId,
            ':name'     => Security::sanitize($data['name']),
            ':price'    => (float) ($data['price']    ?? 0),
            ':unit'     => Security::sanitize($data['unit'] ?? 'piece'),
            ':calories' => (float) ($data['calories'] ?? 0),
            ':protein'  => (float) ($data['protein']  ?? 0),
            ':category' => Security::sanitize($data['category'] ?? 'other'),
        ]);

        return (int) $this->db->lastInsertId();
    }

    /**
     * mise à jour 
     * met à jour un ingrédient (appartenant à l'utilisateur)
     */
    public function update(int $id, int $userId, array $data): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE ingredients
             SET name = :name, price_per_unit = :price, unit = :unit,
                 calories_per_100g = :calories, protein_per_100g = :protein, category = :category
             WHERE id = :id AND user_id = :user_id'
        );

        return $stmt->execute([
            ':name'     => Security::sanitize($data['name']),
            ':price'    => (float) ($data['price']    ?? 0),
            ':unit'     => Security::sanitize($data['unit'] ?? 'piece'),
            ':calories' => (float) ($data['calories'] ?? 0),
            ':protein'  => (float) ($data['protein']  ?? 0),
            ':category' => Security::sanitize($data['category'] ?? 'other'),
            ':id'       => $id,
            ':user_id'  => $userId,
        ]);
    }

    // suppression
    // supprimer un ingrédient (appartenant à l'utilisateur).
    public function delete(int $id, int $userId): bool
    {
        $stmt = $this->db->prepare(
            'DELETE FROM ingredients WHERE id = ? AND user_id = ?'
        );
        return $stmt-execute([$id, $userId]);
    }
}
