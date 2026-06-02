<?php
namespace app\core;

/**
 * Security - Utilitaires de sécurité 
 * 
 * Regroupe : 
 *  - protection CSRF (génération et vérification de tokens)
 *  - Hashage et vérification de mot de passe
 *  - Nettoyage des entrées utilisateur
 *  - Validation d'e-mail
 *  - Géneration de tokens aléatoires (reset mot de passe, etc.)
 */

class Security
{
    // CSRF

    /** génère (ou récupère depuis la session) un token CSRF.
     * @return string tonken hexadécimal 64 caractères
     */

    public static function csrfToken(): string
    {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    /** on vérifie le token CSRF soumis avec le        formulaire.
     *
     * @param string $submitted token reçu depuis $_POST ou header
     * @return bool  
    */
    public static function verifyCsrf(string $submitted) : bool
    {
        $stored = $_SESSION['csrf_token'] ?? '';
        // hash_equals() résiste aux attaques timing
        return hash_equals($stored, $submitted);
    }

    /**
     * returne le champ HTML caché contenant le token CSRF
     * @return string type="hidden"
     */
    public static function csrfField(): string 
    {
        $token = htmlspecialchars(self::csrfToken(), ENT_QUOTES, 'UTF-8');
        return '<input type="hidden" name="csrf_token" value="' . $token . '">';
    }

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

    /** vérifiecaiton de un mot de passe contre son hash stocké
     * @param string $password Mot de passe clair
     * @param string $hash  hash stocké en base 
     * @return bool 
     */
    public static function verifyPassword(string $password, string $hash): bool
    {
        return password_verify($password, $hash);
    }

    // Netoyage des entrées
    /**
     * on supprime les espaces superflus et échappe le html
     * 
     * @param mixed $value valeur brute 
     * @return string
     */
    public static function sanitize(mixed $value): string 
    {
        return htmlspecialchars(trim((string) $value), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

    }

    /**
     * on netoie le tableau de valeurs 
     * 
     * @param array $data données brutes
     * @param array $fields clés à nettoyer (toutes si vide)
     * @return array
     */
    public static function sanitizeArray(array $data, array $fields = []): array
    {
        $keys = $fields ?: array_keys($data);
        foreach ($keys as $key) {
            if (isset($data[$key]) && is_string($data[$key])) {
                $data[$key] = self::sanitize($data [$key]);
            }
        }
        return $data;
    }

    // Validation 

    /**
     * on valide le format d'une adresse e-mail. 
     * 
     * @param string $email
     * @return bool
     */
    public static function isValidEmail(string $email): bool
    {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }

    /**
     * on vérifie les robustesse d'un mot de passe.
     * minimum : 8 caractères, 1 majuscule, 1 chiffre, 1 caractère spécial
     * 
     * @param string $password
     * @return bool
     */

    public static function isStrongPassword(string $password): bool
    {
        return strlen($password) >= 8
            && preg_match('/[A-Z]/', $password)
            && preg_match('/[0-9]/', $password)
            && preg_match('/[\W_]/', $password);
    }

    // Tokens aléatoires

    /**
     * on génère un token URL-safe aléatoire pour reset de mot de passe etc
     * 
     * @param int $bytes nombre d'octets aléatoires 
     * (défaut : 32 -> 64 hex chars)
     * @return string
     */
    public static function generateToken(int $bytes = 32): string
    {
        return bin2hex(random_bytes($bytes));
    }

    // protection headers HTTP

    /**
     * On envoie les headers de sécurité http 
     * à appeler avant tout output.
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


?>