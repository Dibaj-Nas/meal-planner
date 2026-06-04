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

// 1. Environnement
define('ENV', 'development'); // en development ou en production
define('APP_DEBUG', APP_ENV === 'development');

if (APP_DEBUG) {
    ini_set('display_errors', 1);
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', 0);
    error_reporting(0);
}

// 2. les chemins 

/** 
 * ROOT_PATH = dossier racine de projet (parent de app/)
 */
define('ROOT_PATH', __DIR__ . '/..');

// APP_PATH = dossier de l'application (app/)
define('APP_PATH', ROOT_PATH . '/app');

// chemin vers le vendor PHPMailer 
define('PHPMAILER_PATH', APP_PATH . '/models/utilisateurs/vendor');

// 3. la base de données 
define('DB_HOST', 'localhost');
define('DB_NAME', 'planificateur_repas');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

// 4. application 
define('APP_URL', 'http//localhost:3000');
define('SESSION_LIFETIME', 1800); // 30 min
define('CSRF_SECRET', 'changez-cette-cle-secret-64-chars-minimum');

// 5. PHPMailer (SMTP)
define('MAIL_HOST', 'smtp.gmail.com');
define('MAIL_PORT', 587);
define('MAIL_USERNAME', 'planificateur.repas@gmail.com');
define('MAIL_PASSWORD', ''); // TODO le mot de passe de Gmail à défiinir
define('MAIL_ENCRYPTION', 'tls');
define('MAIL_FROM_EMAIL', 'noreplay@planificateur-repas.com');
define('MAIL_FROM_NAME', 'Planificateur de Repas');

// 6. session sécurisée
if (session_status() === PHP_SESSION_NONE) {
    session_start([
        'lifetime' => SESSION_LIFETIME,
        'path' => '/',
        'secure' => (APP_ENV === 'development'),
        'httponly' => true,
        'samesite' => 'strict',
    ]);
    session_start();
}

// 7. autoloaders

// autoloader composer (PHPMailer + dépendances)
$composerAutoload = PHPMAILER_VENDOR . '/autoload.php';
if (file_exists($composerAutoload)) {
    require_once $composerAutoload;
} else {
    // chargement manuel si composer n'est pas disponible
    $src = PHPMAILER_VENDOR . '/phpmailer/phpmailer/src';
    require_once $src . '/Exception.php';
    require_once $src . '/PHPMailer.php';
    require_once $src . '/SMTP.php';
}

// autoloader PSR-4 pour les classes de app/
spl_autoload_register(function (string $fullyQualifiedClass): void {
    /*
     * Convention de nommage :
     *   App\Controllers\AuthController  → app/controllers/AuthController.php
     *   App\Models\User                 → app/models/User.php
     *   App\Core\Database               → app/core/Database.php
     *   App\Services\MealGeneratorService → app/services/MealGeneratorService.php
     *   App\Middleware\AuthMiddleware    → app/middleware/AuthMiddleware.php
     */
    $prefix = 'app\\';
    if (strncmp($prefix, $fullyQualifiedClass, strlen($prefix)) !== 0)
        {
            return; // pas de namespace = on ignore
        }

        $relativePath = substr($fullyQualifiedClass, strlen($prefix)); // par ex: controllers\AuthController
        $parts = explode('\\', $relativePath);
        $className = array_pop($parts);
        $subDir = strtolower(implode('/', $parts));

        $file = APP_PATH . '/' . ($subDir ? $subDir . '/' : '') . $className . '.php';

    if (file_exists($file)) {
        require_once $file;
    }

});
?>