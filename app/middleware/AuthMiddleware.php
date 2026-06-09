<?php
namespace App\Middleware;

use app\core\Security;

/**
 * AuthMiddleware — Protection des routes authentifiées
 *
 * Vérifie qu'un utilisateur est connecté avant d'autoriser
 * l'accès à une route. Utilise la session PHP.
 *
 * Usage dans un contrôleur :
 *   AuthMiddleware::requireAuth();           // redirige si non connecté
 *   AuthMiddleware::requireAuth(true);       // retourne JSON 401 (API)
 *   AuthMiddleware::requireGuest();          // redirige si déjà connecté
 *
 * Usage dans le routeur (middleware global) :
 *   AuthMiddleware::handle('/dashboard', fn() => ...);
 */
class AuthMiddleware
{
    /** Clé de session contenant les données utilisateur */
    private const SESSION_KEY = 'user';

    /* ── Vérifications ──────────────────────────────── */

    /**
     * Vérifie si l'utilisateur est connecté.
     *
     * @return bool
     */
    public static function isLoggedIn(): bool
    {
        return isset($_SESSION[self::SESSION_KEY]['id'])
            && !empty($_SESSION[self::SESSION_KEY]['id']);
    }

    /**
     * Retourne les données de l'utilisateur connecté.
     *
     * @return array|null
     */
    public static function currentUser(): ?array
    {
        return $_SESSION[self::SESSION_KEY] ?? null;
    }

    /**
     * Retourne l'ID de l'utilisateur connecté.
     *
     * @return int|null
     */
    public static function currentUserId(): ?int
    {
        return isset($_SESSION[self::SESSION_KEY]['id'])
            ? (int) $_SESSION[self::SESSION_KEY]['id']
            : null;
    }

    /* ── Protection des routes ──────────────────────── */

    /**
     * Exige que l'utilisateur soit connecté.
     * - Si $apiMode = false : redirige vers /login
     * - Si $apiMode = true  : retourne une erreur JSON 401
     *
     * @param bool $apiMode Réponse JSON au lieu de redirection
     */
    public static function requireAuth(bool $apiMode = false): void
    {
        if (self::isLoggedIn()) {
            /* Renouvelle la session si elle approche de l'expiration */
            self::refreshSession();
            return;
        }

        if ($apiMode) {
            http_response_code(401);
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'error'   => 'Non authentifié. Veuillez vous connecter.',
            ]);
            exit;
        }

        /* Mémorise l'URL pour y revenir après connexion */
        $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'] ?? '/';
        header('Location: /login');
        exit;
    }

    /**
     * Redirige les utilisateurs déjà connectés.
     * (Utile pour les pages login/register.)
     *
     * @param string $redirectTo URL de redirection si connecté
     */
    public static function requireGuest(string $redirectTo = '/'): void
    {
        if (self::isLoggedIn()) {
            header("Location: {$redirectTo}");
            exit;
        }
    }

    /* ── Gestion de session ─────────────────────────── */

    /**
     * Initialise la session utilisateur après une connexion réussie.
     *
     * @param array $user Données utilisateur depuis la BDD
     */
    public static function login(array $user): void
    {
        /* Regénère l'ID de session pour prévenir la fixation de session */
        session_regenerate_id(true);

        $_SESSION[self::SESSION_KEY] = [
            'id'        => $user['id'],
            'firstname' => $user['firstname'],
            'lastname'  => $user['lastname'],
            'email'     => $user['email'],
            'role'      => $user['role'] ?? 'user',
            'logged_at' => time(),
        ];
    }

    /**
     * Détruit la session et déconnecte l'utilisateur.
     */
    public static function logout(): void
    {
        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(), '', time() - 42000,
                $params['path'],   $params['domain'],
                $params['secure'], $params['httponly']
            );
        }

        session_destroy();
    }

    /**
     * Rafraîchit le timestamp de session pour éviter l'expiration
     * lors d'une activité régulière.
     */
    private static function refreshSession(): void
    {
        $loggedAt = $_SESSION[self::SESSION_KEY]['logged_at'] ?? 0;

        /* Renouvelle si la session a plus de la moitié de sa durée de vie */
        if (time() - $loggedAt > SESSION_LIFETIME / 2) {
            session_regenerate_id(false);
            $_SESSION[self::SESSION_KEY]['logged_at'] = time();
        }
    }

    /**
     * Vérifie si la session a expiré (sécurité supplémentaire côté PHP).
     *
     * @return bool True si expirée
     */
    public static function isSessionExpired(): bool
    {
        $loggedAt = $_SESSION[self::SESSION_KEY]['logged_at'] ?? 0;
        return (time() - $loggedAt) > SESSION_LIFETIME;
    }
}
