<?php
namespace app\models;

use app\core\Database;
use app\core\Security;
use PDO;

/**
 * user.php - modèle utilisateur
 * couvre toute les interactions avec la table `users` : 
 *  - création (inscription)
 *  - lecture (connexion, profil)
 *  - mise à jour (profile, mot de passe utilisateurs)
 *  - tokens de rèinitialisation de mot de passe
 *  - menus sauvegardés -> option "Mes menus sauvegardés"
 *  - favoris -> option "Mes favoris"
 *  - paramètres -> option "Mes paramètres"
 *  - déconnexion -> option "Se déconnecter"
 */

class User 
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
    }

    // recherche
    /** 
     * on trouve un utilisateur par son ID et on ne renvois jamais le hash du mot de passe. 
     */
    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT id, firstname, lastname, email, role, created_at 
                FROM users
                WHERE id = ?
                LIMIT 1'
        );
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * on trouve un utilisateur par son email, inclut le hash du mot de passe (pour la vérification à la connexion).
     */
    public function findByEmail(string $email): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT id, firstname, lastname, email, role, created_at, password_hash
               FROM users
              WHERE email = ?
              LIMIT 1'
        );
        $stmt->execute([strtolower(trim($email))]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    // création de l'inscription 
    /**
     * on va créer un nouvel utilisateur en base de données
     * 
     * TODO
     * @param array $data 'firstname', 'lastname', 'email', 'password'
     * @return int|false ID inséré ou false si email est déjà utilisé
     */
    public function create(array $data): int|false
    {
        if ($this->findByEmail($data['email'])) {
            return false; //email déjà utilisé
        }

        $stmt = $this->db->prepare(
            'INSERT INTO users (firstname, lastname, email, password_hash,
            role, created_at)
            VALUES (:firstname, :lastname, :email, :password_hash, :role,
            NOW())'
        );

        $stmt->execute([
            ':firstname' => Security::sanitize($data['firstname']),
            ':lastname' => Security::sanitize($data['lastname']),
            ':email' => strtolower(trim($data['email'])),
            ':password_hash' => Security::hashPassword($data['password']),
            ':role' => 'user'
        ]);

        return (int) $this->db->lastInsertId() ?: false;
    }

    // mon profil - lecture & mise à jour
    /**
     * renvois les informations publiques du profil d'un utilisateur et utilisé par la page "mon profil" du dropdown
    */
    public function getProfile(int $id): ?array
    {
        return $this->findById($id);
    }

    // met à jour le prénom, le nom, et l'e-mail d'un utilisateur, utilisé par le firmulaire de mise à jour du "Mon profil"
    public function updateProfile(int $id, array $data): bool
    {
        // Vérifie que le nouvel e-mail n'est pas pris par un autre compte
        $existing = $this->findByEmail($data['email']);
        if ($existing && (int)$existing['id'] !== $id) {
            return false;
        }

        $stmt = $this->db->prepare(
            'UPDATE users
                SET firstname = :firstname,
                    lastname  = :lastname,
                    email     = :email
              WHERE id = :id'
        );

        return $stmt->execute([
            ':firstname' => Security::sanitize($data['firstname']),
            ':lastname'  => Security::sanitize($data['lastname']),
            ':email'     => strtolower(trim($data['email'])),
            ':id'        => $id,
        ]);
    }

    /** changement le mot de passe d'un utilisateur.
     * 
     * TODO
     * on vérifie l'ancien mot de passe avant d'accepter le nouveau,
     * utilisé depuis "Mon profil" (section sécurité).
    */
    public function updatePassword(int $id, string $currentPassword, string $newPassword): bool
    {
        $stmt = $this->db->prepare(
            'SELECT password_hash FROM users WHERE id = ? LIMIT 1'
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row || !Security::verifyPassword($currentPassword, $row['password_hash'])) {
            return false; // mot de passe actuel incorrect
        }

        $stmt = $this->db->prepare(
            'UPDATE users SET password_hash = :hash WHERE id = :id'
        );
        return $stmt->execute([
            ':hash' => Security::hashPassword($newPassword),
            ':id'   => $id,
        ]);
    }

    // paramètres

    /**
     * TODO
     * récupére les paramètres de l'utilisateur comme (préférences alimentaires, budget par défaut, nombre de personnes...) et utilisé par la page "paramètres" du dropdown
     */
    public function getSettings(int $id): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT dietary_preference, default_budget, default_persons, notifications_enabled
               FROM user_settings
              WHERE user_id = ?
              LIMIT 1'
        );
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * enregistre (crée ou met à jour) les paramètres d'un utilisateur, utilisé par le formulaire "paramètres"
     */
    public function saveSettings(int $id, array $settings): bool
    {
        $stmt = $this->db->prepare(
            'INSERT INTO user_settings (user_id, dietary_preference, default_budget, default_persons, notifications_enabled)
             VALUES (:user_id, :dietary, :budget, :persons, :notif)
             ON DUPLICATE KEY UPDATE
                 dietary_preference      = VALUES(dietary_preference),
                 default_budget          = VALUES(default_budget),
                 default_persons         = VALUES(default_persons),
                 notifications_enabled   = VALUES(notifications_enabled)'
        );

        return $stmt->execute([
            ':user_id' => $id,
            ':dietary' => $settings['dietary_preference'] ?? 'all',
            ':budget'  => (float)($settings['default_budget']  ?? 50),
            ':persons' => (int)($settings['default_persons']   ?? 2),
            ':notif'   => (int)($settings['notifications_enabled'] ?? 1),
        ]);
    }


    // mes menus sauvegardés

    /**
     * renvois tous les menus hebdomadaires sauvegardés par un utilisateur du plus récent au plus ancien
     * utilisé par la page "Mes menus sauvegardés" du dropdown.
     */
    public function getSavedMenus(int $userId): array
    {
        $stmt = $this->db->prepare(
            'SELECT id, title, week_start, budget, persons, total_cost, created_at
               FROM weekly_menus
              WHERE user_id = :user_id
              ORDER BY created_at DESC'
        );
        $stmt->execute([':user_id' => $userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // sauvegarde un menus hebdomadaires pour un utilisateur
    public function saveMenu(int $userId, array $menuData): int|false
    {
        $stmt = $this->db->prepare(
            'INSERT INTO weekly_menus (user_id, title, week_start, budget, persons, total_cost, created_at)
             VALUES (:user_id, :title, :week_start, :budget, :persons, :total_cost, NOW())'
        );

        $ok = $stmt->execute([
            ':user_id'    => $userId,
            ':title'      => Security::sanitize($menuData['title'] ?? 'Menu sans titre'),
            ':week_start' => $menuData['week_start'],
            ':budget'     => (float)$menuData['budget'],
            ':persons'    => (int)$menuData['persons'],
            ':total_cost' => (float)$menuData['total_cost'],
        ]);

        return $ok ? (int)$this->db->lastInsertId() : false;
    }

    // supprimer un menu sauvegardé (vérifie que l'utilisateur est le propriétaire)
    public function deleteMenu(int $menuId, int $userId): bool
    {
        $stmt = $this->db->prepare(
            'DELETE FROM weekly_menus WHERE id =. :menu_id AND user_id = :user_id'
        );
        return $stmt->execute([':menu_id' => $menuId, ':user_id' => $userId]);
    }

    // mes favoris
    // renvoie toutes les recettes favorites d'un utilisateur, utilisé par la page "Mes favoris"
    public function getFavorites(int $userId): array 
    {
        $stmt = $this->db->prepare(
            'SELECT r.id, r.name, r.meal_type, r.prep_time, r.estimated_cost,
                    r.calories, r.dietary, f.added_at
               FROM favorites f
               JOIN recipes r ON r.id = f.recipe_id
              WHERE f.user_id = :user_id
              ORDER BY f.added_at DESC'
        );
        $stmt->execute([':user_id' => $userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ajout d'une recette aux favoris d'un utilisateur, ignore silencieusement si elle est dèjà présente (Ignore).
    public function addFavorite(int $userId, int $recipeId): bool
    {
        $stmt = $this->db->prepare(
            'INSERT IGNORE INTO favorites (user_id, recipe_id, added_at)
             VALUES (:user_id, :recipe_id, NOW())'
        );
        return $stmt->execute([':user_id' => $userId, ':recipe_id' => $recipeId]);
    }

    // retire une recette des favoris d'un utilisateur
    public function removeFavorite(int $userId, int $recipeId): bool
    {
        $stmt = $this->db->prepare(
            'DELETE FROM favorites WHERE user_id = :user_id AND recipe_id = :recipe_id'
        );
        return $stmt->execute([':user_id' => $userId, ':recipe_id' => $recipeId]);
    }

    // Vérifie si une recette est dans les favoris de l'utilisateur.
    // Utile pour afficher/masquer le bouton "Mes favoris dans l'interface.
    public function isFavorite(int $userId, int $recipeId): bool
    {
        $stmt = $this->db->prepare(
            'SELECT 1 FROM favorites WHERE user_id = ? AND recipe_id = ?
            LIMIT 1'
        );
        $stmt->execute([$userId, $recipeId]);
        return (bool)$stmt->fetchColumn();
    }

    // déconnexion
    // on déconnecte l'utilisateur : détruit la session php et efface le cookie, appelé par l'action "Se déconnecter"
    public function logout(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $_SESSION = [];

        // Expire le cookie de session immédiatement
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                $params['secure'],
                $params['httponly']
            );
        }

        session_destroy();
    }

    // token de réinitialisation de mot de passe
    // génère et enregistre un token de réinitialisation (valable 1h), lié au lien "Mot de passe oublié ?" de la page login
    public function createResetToken(string $email): string|false
    {
        if (!$this->findByEmail($email)) {
            return false;
        }

        $token   = bin2hex(random_bytes(32));
        $expires = date('Y-m-d H:i:s', time() + 3600);

        $stmt = $this->db->prepare(
            'INSERT INTO password_resets (email, token, expires_at)
             VALUES (:email, :token, :expires)
             ON DUPLICATE KEY UPDATE token = VALUES(token), expires_at = VALUES(expires_at)'
        );

        $stmt->execute([
            ':email'   => strtolower(trim($email)),
            ':token'   => hash('sha256', $token),
            ':expires' => $expires,
        ]);

        return $token; // renvoyer le token en clair pour l'e-mail
    }

    // valide un token de réinitialisation et change le mot de passe.
    public function resetPasswordWithToken(string $token, string $newPassword): bool
    {
        $hashedToken = hash('sha256', $token);

        $stmt = $this->db->prepare(
            'SELECT email FROM password_resets
              WHERE token = :token AND expires_at > NOW()
              LIMIT 1'
        );
        $stmt->execute([':token' => $hashedToken]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return false; // token invalide ou expiré
        }

        $user = $this->findByEmail($row['email']);
        if (!$user) {
            return false;
        }

        // Met à jour le mot de passe
        $stmt = $this->db->prepare(
            'UPDATE users SET password_hash = :hash WHERE id = :id'
        );
        $stmt->execute([
            ':hash' => Security::hashPassword($newPassword),
            ':id'   => $user['id'],
        ]);

        // Supprime le token utilisé
        $stmt = $this->db->prepare(
            'DELETE FROM password_resets WHERE token = :token'
        );
        $stmt->execute([':token' => $hashedToken]);

        return true;
    }

}