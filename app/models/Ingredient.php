<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use App\Core\Security;
use PDO;

/**
 * Ingredient — Modèle de la table `ingredients`
 * =============================================
 *
 * Un "modèle" est la couche qui parle à la base de données pour UNE entité.
 * Ici, on gère les ingrédients d'un utilisateur (le CRUD complet) :
 *   - C reate  → create()
 *   - R ead    → allByUser(), findByIdAndUser()
 *   - U pdate  → update()
 *   - D elete  → delete()
 *
 * Bonnes pratiques appliquées :
 *   - Requêtes PRÉPARÉES (placeholders ? ou :nom) → protègent des injections SQL.
 *   - Chaque requête filtre sur user_id → un utilisateur ne voit que SES données.
 */
class Ingredient
{
    /** Connexion PDO partagée (récupérée depuis le singleton Database). */
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
    }

    /**
     * Liste les ingrédients d'un utilisateur, avec recherche optionnelle par nom.
     *
     * @param int    $userId Identifiant du propriétaire.
     * @param string $search Texte recherché dans le nom (vide = tout lister).
     * @return array Tableau d'ingrédients (triés par catégorie puis nom).
     */
    public function allByUser(int $userId, string $search = ''): array
    {
        if ($search !== '') {
            // LIKE '%texte%' = recherche "contient". Le % est un joker SQL.
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

    /**
     * Récupère un ingrédient précis, à condition qu'il appartienne à l'utilisateur.
     *
     * @return array|null L'ingrédient, ou null s'il n'existe pas / pas le bon propriétaire.
     */
    public function findByIdAndUser(int $id, int $userId): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM ingredients WHERE id = ? AND user_id = ? LIMIT 1'
        );
        $stmt->execute([$id, $userId]);
        // fetch() renvoie false si rien trouvé → on convertit en null.
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Crée un nouvel ingrédient pour l'utilisateur.
     *
     * @return int L'identifiant auto-généré du nouvel ingrédient.
     */
    public function create(int $userId, array $data): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO ingredients (user_id, name, price, unit, calories, protein, category)
             VALUES (:user_id, :name, :price, :unit, :calories, :protein, :category)'
        );
        $stmt->execute([
            ':user_id'  => $userId,
            // sanitize() nettoie le texte (espaces + échappement HTML anti-XSS).
            ':name'     => Security::sanitize($data['name']),
            ':price'    => (float) ($data['price'] ?? 0),   // cast en nombre décimal
            ':unit'     => Security::sanitize($data['unit'] ?? 'piece'),
            ':calories' => (float) ($data['calories'] ?? 0),
            ':protein'  => (float) ($data['protein'] ?? 0),
            ':category' => Security::sanitize($data['category'] ?? 'other'),
        ]);
        // lastInsertId() = l'id que MySQL vient d'attribuer à la ligne insérée.
        return (int) $this->db->lastInsertId();
    }

    /**
     * Met à jour un ingrédient existant (uniquement si c'est le bon propriétaire).
     *
     * @return bool true si la requête s'est exécutée sans erreur.
     */
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
            ':user_id'  => $userId,   // sécurité : on ne modifie que SES ingrédients
        ]);
    }

    /**
     * Supprime un ingrédient (uniquement si c'est le bon propriétaire).
     *
     * @return bool true si la requête s'est exécutée sans erreur.
     */
    public function delete(int $id, int $userId): bool
    {
        $stmt = $this->db->prepare('DELETE FROM ingredients WHERE id = ? AND user_id = ?');
        return $stmt->execute([$id, $userId]);
    }
}
