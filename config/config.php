<?php
/**
 * config/config.php = la configuration global du projet
 * 
 * IMPORTANT: Ce fichier NE DOIT PAS être versionné avec de vraies credentials
 */


// 1. ENVIRONNEMENT


define('APP_ENV',   'development');  // 'development' | 'production'
define('APP_DEBUG', APP_ENV === 'development');

if (APP_DEBUG) {
    ini_set('display_errors', 1);
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', 0);
    error_reporting(0);
}


// 2. LES CHEMINS 


/**
 * ROOT_PATH = dossier racine du projet (parent de app/)
 * Exemple : /Users/dibajnasrullah/Tp_dev/Projet fin formation/Projet
 */
define('ROOT_PATH', dirname(__DIR__));

/**
 * APP_PATH = dossier app/
 */
define('APP_PATH', ROOT_PATH . '/app');

/**
 * Chemin exact vers le vendor PHPMailer dans VOTRE arborescence :
 * app/models/utlisateurs/vendor/
 */
define('PHPMAILER_VENDOR', APP_PATH . '/models/utlisateurs/vendor');


//  3. BASE DE DONNÉES


define('DB_HOST',    'localhost');
define('DB_NAME',    'meal_planner');
define('DB_USER',    'root');        // ← à remplacer
define('DB_PASS',    '');            // ← à remplacer
define('DB_CHARSET', 'utf8mb4');


//  4. APPLICATION


define('APP_URL',          'http://localhost:8000');
define('SESSION_LIFETIME', 1800);   // 30 minutes
define('CSRF_SECRET',      'changez-cette-cle-secrete-64-chars-minimum');


// 5. PHPMAILER (SMTP)


define('MAIL_HOST',       'smtp.gmail.com');
define('MAIL_PORT',       587);
define('MAIL_USERNAME',   'votre@gmail.com');    // ← à remplacer
define('MAIL_PASSWORD',   'votre-app-password'); // ← mot de passe d'application Gmail
define('MAIL_ENCRYPTION', 'tls');
define('MAIL_FROM_EMAIL', 'noreply@mealplanner.fr');
define('MAIL_FROM_NAME',  'Planificateur de Repas');


// 6. SESSION SÉCURISÉE


if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => SESSION_LIFETIME,
        'path'     => '/',
        'secure'   => (APP_ENV === 'production'),
        'httponly' => true,
        'samesite' => 'Strict',
    ]);
    session_start();
}


// 7. AUTOLOADERS


/* — Autoloader Composer (PHPMailer + dépendances) — */
$composerAutoload = PHPMAILER_VENDOR . '/autoload.php';
if (file_exists($composerAutoload)) {
    require_once $composerAutoload;
} else {
    /* Fallback : chargement manuel si Composer n'est pas disponible */
    $src = PHPMAILER_VENDOR . '/phpmailer/phpmailer/src';
    require_once $src . '/Exception.php';
    require_once $src . '/PHPMailer.php';
    require_once $src . '/SMTP.php';
}

/* — Autoloader PSR-4 maison pour les classes app/ — */
spl_autoload_register(function (string $fullyQualifiedClass): void {
    /*
     * Convention de nommage :
     *   App\Controllers\AuthController  → app/controllers/AuthController.php
     *   App\Models\User                 → app/models/User.php
     *   App\Core\Database               → app/core/Database.php
     *   App\Services\MealGeneratorService → app/services/MealGeneratorService.php
     *   App\Middleware\AuthMiddleware    → app/middleware/AuthMiddleware.php
     */
    $prefix = 'App\\';
    if (strncmp($prefix, $fullyQualifiedClass, strlen($prefix)) !== 0) {
        return; // pas notre namespace → on ignore
    }

    $relativePath = substr($fullyQualifiedClass, strlen($prefix)); // ex: "Controllers\AuthController"
    $parts        = explode('\\', $relativePath);
    $className    = array_pop($parts);
    $subDir       = strtolower(implode('/', $parts));               // ex: "controllers"

    $file = APP_PATH . '/' . ($subDir ? $subDir . '/' : '') . $className . '.php';

    if (file_exists($file)) {
        require_once $file;
    }
});


?>