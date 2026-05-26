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

    



}


?>