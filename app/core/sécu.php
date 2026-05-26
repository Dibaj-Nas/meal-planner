<?php
namespace App\Core;

/**
 * Security — Utilitaires de sécurité
 *
 * Regroupe :
 *   - Protection CSRF (génération et vérification de tokens)
 *   - Hachage et vérification de mots de passe
 *   - Nettoyage des entrées utilisateur
 *   - Validation d'e-mail
 *   - Génération de tokens aléatoires (reset mot de passe, etc.)
 */
class Security
{
    /* ── CSRF ──────────────────────────────────────── */

    /**
     * Génère (ou récupère depuis la session) un token CSRF.
     *
     * @return string Token hexadécimal 64 caractères
     */
    public static function csrfToken(): string
    {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    /**
     * Vérifie le token CSRF soumis avec le formulaire.
     *
     * @param  string $submitted Token reçu depuis $_POST ou header
     * @return bool
     */
    public static function verifyCsrf(string $submitted): bool
    {
        $stored = $_SESSION['csrf_token'] ?? '';
        /* hash_equals() résiste aux attaques timing */
        return hash_equals($stored, $submitted);
    }

    /**
     * Retourne le champ HTML caché contenant le token CSRF.
     *
     * @return string <input type="hidden" ...>
     */
    public static function csrfField(): string
    {
        $token = htmlspecialchars(self::csrfToken(), ENT_QUOTES, 'UTF-8');
        return '<input type="hidden" name="csrf_token" value="' . $token . '">';
    }

    /* ── MOT DE PASSE ──────────────────────────────── */

    /**
     * Hache un mot de passe avec l'algorithme Argon2ID (le plus sécurisé).
     * Retombe sur bcrypt si Argon2 n'est pas disponible.
     *
     * @param  string $password Mot de passe en clair
     * @return string Hash sécurisé
     */
    public static function hashPassword(string $password): string
    {
        $algo = defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_BCRYPT;
        return password_hash($password, $algo);
    }

    /**
     * Vérifie un mot de passe contre son hash stocké.
     *
     * @param  string $password Mot de passe en clair
     * @param  string $hash     Hash stocké en base
     * @return bool
     */
    public static function verifyPassword(string $password, string $hash): bool
    {
        return password_verify($password, $hash);
    }

    /* ── NETTOYAGE DES ENTRÉES ─────────────────────── */

    /**
     * Nettoie une chaîne : supprime les espaces superflus et échappe le HTML.
     *
     * @param  mixed  $value Valeur brute
     * @return string
     */
    public static function sanitize(mixed $value): string
    {
        return htmlspecialchars(trim((string) $value), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /**
     * Nettoie un tableau de valeurs.
     *
     * @param  array $data   Données brutes
     * @param  array $fields Clés à nettoyer (toutes si vide)
     * @return array
     */
    public static function sanitizeArray(array $data, array $fields = []): array
    {
        $keys = $fields ?: array_keys($data);
        foreach ($keys as $key) {
            if (isset($data[$key]) && is_string($data[$key])) {
                $data[$key] = self::sanitize($data[$key]);
            }
        }
        return $data;
    }

    /* ── VALIDATION ────────────────────────────────── */

    /**
     * Valide le format d'une adresse e-mail.
     *
     * @param  string $email
     * @return bool
     */
    public static function isValidEmail(string $email): bool
    {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }

    /**
     * Vérifie la robustesse d'un mot de passe.
     * Minimum : 8 caractères, 1 majuscule, 1 chiffre.
     *
     * @param  string $password
     * @return bool
     */
    public static function isStrongPassword(string $password): bool
    {
        return strlen($password) >= 8
            && preg_match('/[A-Z]/', $password)
            && preg_match('/[0-9]/', $password);
    }

    /* ── TOKENS ALÉATOIRES ─────────────────────────── */

    /**
     * Génère un token URL-safe aléatoire (pour reset de mot de passe, etc.).
     *
     * @param  int $bytes Nombre d'octets aléatoires (défaut : 32 → 64 hex chars)
     * @return string
     */
    public static function generateToken(int $bytes = 32): string
    {
        return bin2hex(random_bytes($bytes));
    }

    /* ── PROTECTION HEADERS HTTP ───────────────────── */

    /**
     * Envoie les headers de sécurité HTTP recommandés.
     * À appeler avant tout output.
     */
    public static function secureHeaders(): void
    {
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: SAMEORIGIN');
        header('X-XSS-Protection: 1; mode=block');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        if (APP_ENV === 'production') {
            header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
        }
    }
}