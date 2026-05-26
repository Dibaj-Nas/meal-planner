<?php
/**
 * config.php — Configuration globale de l'application
 * Planificateur de Repas
 *
 * Charge les constantes de connexion à la base de données,
 * les paramètres de l'application et la configuration PHPMailer.
 *
 * important : Ce fichier NE doit JAMAIS être versionné avec de vraies credentials.
 *    Ajoutez-le à .gitignore et utilisez un fichier .env en production.
 */


// 1. Environment

define('APP_ENV', 'development'); // 'development' | 'production'
define('APP_DEBUG', APP_ENV === 'development');

/* Affiche les erreurs PHP seulement en dev */
if (APP_DEBUG) {
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', 0);
    error_reporting(0);
}

// 2. BASE DE DONNÉES 

define('DB_HOST',    'localhost');
define('DB_NAME',    'meal_planner');
define('DB_USER',    'root');           // ← à remplacer
define('DB_PASS',    '');               // ← à remplacer
define('DB_CHARSET', 'utf8mb4');


// 3. Application


/* URL racine de l'application (sans slash final) */
define('APP_URL',  'http://localhost:3000');

/* Chemin absolu vers la racine du projet */
define('ROOT_PATH', dirname(__DIR__));

/* Durée de vie de la session en secondes (30 minutes) */
define('SESSION_LIFETIME', 1800);

/* Clé secrète pour les tokens CSRF — changez-la impérativement */
define('CSRF_SECRET', 'changez-cette-cle-secrete-en-production-64-chars');


//    4. PHPMAILER — Envoi d'e-mails


define('MAIL_HOST',       'smtp.gmail.com');    // Serveur SMTP
define('MAIL_PORT',       587);                 // Port STARTTLS
define('MAIL_USERNAME',   'votre@gmail.com');   // ← à remplacer
define('MAIL_PASSWORD',   'app-password');      // ← à remplacer (mot de passe app Gmail)
define('MAIL_ENCRYPTION', 'tls');               // 'tls' (port 587) ou 'ssl' (port 465)
define('MAIL_FROM_EMAIL', 'noreply@mealplanner.fr');
define('MAIL_FROM_NAME',  'Planificateur de Repas');


// 5. SESSION — démarrage sécurisé

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => SESSION_LIFETIME,
        'path'     => '/',
        'secure'   => APP_ENV === 'production', // HTTPS uniquement en prod
        'httponly' => true,                     // Inaccessible depuis JS
        'samesite' => 'Strict',
    ]);
    session_start();
}

// 6. AUTOLOADER SIMPLE (sans Composer pour les classes app/)

/**
 * Charge automatiquement les classes de l'application.
 * Convention : App\Controllers\AuthController → app/controllers/AuthController.php
 */
spl_autoload_register(function (string $class): void {
    /* Convertit le namespace en chemin de fichier */
    $prefix = 'App\\';
    if (strncmp($prefix, $class, strlen($prefix)) !== 0) {
        return;
    }

    $relativeClass = substr($class, strlen($prefix));
    $file = ROOT_PATH . '/app/' . str_replace('\\', '/', strtolower($relativeClass)) . '.php';

    /* Correction casse pour les dossiers (controllers → controllers) */
    $file = preg_replace_callback(
        '#/([a-z]+)/#',
        fn($m) => '/' . $m[1] . '/',
        $file
    );

    /* Reconstruire avec la casse correcte des noms de fichiers */
    $parts    = explode('\\', $relativeClass);
    $fileName = array_pop($parts);
    $dir      = strtolower(implode('/', $parts));
    $file     = ROOT_PATH . '/app/' . ($dir ? $dir . '/' : '') . $fileName . '.php';

    if (file_exists($file)) {
        require_once $file;
    }
});

/* Autoloader PHPMailer (vendor classique) */
$phpmailerAutoload = ROOT_PATH . '/vendor/phpmailer/phpmailer/src/PHPMailer.php';
if (file_exists($phpmailerAutoload)) {
    require_once ROOT_PATH . '/vendor/phpmailer/phpmailer/src/Exception.php';
    require_once ROOT_PATH . '/vendor/phpmailer/phpmailer/src/PHPMailer.php';
    require_once ROOT_PATH . '/vendor/phpmailer/phpmailer/src/SMTP.php';
}