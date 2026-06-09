<?php
/**
 * config/config.php — Configuration globale
 * Planificateur de Repas
 *
 * Important : Ce fichier NE doit PAS être versionné avec de vraies credentials.
 *     il sera dans le .gitignore en production.
 *
 * Chemins PHPMailer réels :
 *   app/models/utlisateurs/vendor/phpmailer/phpmailer/src/
 */

declare(strict_types=1);

// 1. Environnement
define('ENV',       getenv('APP_ENV') ?: 'development');   // 'development' | 'production'
define('APP_DEBUG', ENV === 'development');
 
if (APP_DEBUG) {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
    error_reporting(0);
}


// 2. les chemins 

/** ROOT_PATH = racine du projet (dossier parent de /config) */
define('ROOT_PATH', dirname(__DIR__));
 
/** APP_PATH = dossier /app */
define('APP_PATH', ROOT_PATH . '/app');
 
/** PHPMAILER_VENDOR = chemin vers le vendor Composer */
define('PHPMAILER_VENDOR', APP_PATH . '/vendor');

// 3. la base de données 
define('DB_HOST',    getenv('DB_HOST')    ?: 'localhost');
define('DB_NAME',    getenv('DB_NAME')    ?: 'meal_planner');
define('DB_USER',    getenv('DB_USER')    ?: 'root');
define('DB_PASS',    getenv('DB_PASS')    ?: '');
define('DB_CHARSET', 'utf8mb4');

/**
 * ID du compte système qui porte les recettes et ingrédients
 * pré-chargés (seed). Utilisé par MenuController pour la
 * génération de menus par défaut.
 */
define('SYSTEM_USER_ID', 1);

// 4. application 
define('APP_URL',          getenv('APP_URL') ?: 'http://localhost:8000');
define('SESSION_LIFETIME', 1800);   // 30 min en secondes
define('CSRF_SECRET',      getenv('CSRF_SECRET') ?: 'changez-cette-cle-secrete-minimum-64-caracteres-xxxx');


// 5. PHPMailer (SMTP)
define('MAIL_HOST',       getenv('MAIL_HOST')       ?: 'smtp.gmail.com');
define('MAIL_PORT',       (int)(getenv('MAIL_PORT') ?: 587));
define('MAIL_USERNAME',   getenv('MAIL_USERNAME')   ?: 'planificateur.repas@gmail.com');
define('MAIL_PASSWORD',   getenv('MAIL_PASSWORD')   ?: '');   // TODO : définir en variable d'env
define('MAIL_ENCRYPTION', 'tls');
define('MAIL_FROM_EMAIL', getenv('MAIL_FROM') ?: 'noreply@planificateur-repas.com');
define('MAIL_FROM_NAME',  'Planificateur de Repas');
 
// 6. session sécurisée
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => SESSION_LIFETIME,
        'path'     => '/',
        'secure'   => (ENV === 'production'),   // HTTPS uniquement en prod
        'httponly' => true,
        'samesite' => 'Strict',
    ]);
    session_start();
}

// 7. autoloaders

// Autoloader Composer (PHPMailer + autres dépendances)
$composerAutoload = PHPMAILER_VENDOR . '/autoload.php';
if (file_exists($composerAutoload)) {
    require_once $composerAutoload;
} else {
    // Chargement manuel si Composer n'est pas disponible
    $src = PHPMAILER_VENDOR . '/phpmailer/phpmailer/src';
    if (is_dir($src)) {
        require_once $src . '/Exception.php';
        require_once $src . '/PHPMailer.php';
        require_once $src . '/SMTP.php';
    }
}

// autoloader PSR-4 pour les classes de app/
// Convention :
//   App\Controllers\AuthController  → app/controllers/AuthController.php
//   App\Models\User                 → app/models/User.php
//   App\Core\Database               → app/core/Database.php
//   App\Services\MealGeneratorService → app/services/MealGeneratorService.php
//   App\Middleware\AuthMiddleware    → app/middleware/AuthMiddleware.php
spl_autoload_register(function (string $fqcn): void {
    $prefix = 'App\\';
    if (strncmp($prefix, $fqcn, strlen($prefix)) !== 0) {
        return;
    }
 
    // Transforme App\Controllers\AuthController → controllers/AuthController
    $relative = substr($fqcn, strlen($prefix));          // Controllers\AuthController
    $parts     = explode('\\', $relative);
    $className = array_pop($parts);                       // AuthController
    $subDir    = strtolower(implode('/', $parts));        // controllers
 
    $file = APP_PATH . '/' . ($subDir ? $subDir . '/' : '') . $className . '.php';
 
    if (file_exists($file)) {
        require_once $file;
    }
});