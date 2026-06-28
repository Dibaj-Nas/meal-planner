<?php
/**
 * config/config.example.php — Modèle de configuration
 *
 * Copiez ce fichier vers config/config.php puis adaptez les valeurs.
 * Vous pouvez aussi définir les variables dans un fichier .env à la racine.
 *
 *   cp config/config.example.php config/config.php
 *   cp .env.example .env
 */

declare(strict_types=1);

/** Charge le fichier .env (clé=valeur) si présent à la racine du projet. */
(function (): void {
    $envFile = dirname(__DIR__) . '/.env';
    if (!is_file($envFile)) {
        return;
    }
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
            continue;
        }
        [$key, $value] = array_map('trim', explode('=', $line, 2));
        if ($key !== '' && getenv($key) === false) {
            putenv("{$key}={$value}");
            $_ENV[$key] = $value;
        }
    }
})();

// 1. Environnement
define('ENV',       getenv('APP_ENV') ?: 'development');
define('APP_DEBUG', ENV === 'development');

if (APP_DEBUG) {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
    error_reporting(0);
}

// 2. Chemins
define('ROOT_PATH', dirname(__DIR__));
define('APP_PATH', ROOT_PATH . '/app');
define('PHPMAILER_VENDOR', APP_PATH . '/models/utlisateurs/vendor');

// 3. Base de données
define('DB_HOST',    getenv('DB_HOST')    ?: 'localhost');
define('DB_NAME',    getenv('DB_NAME')    ?: 'meal_planner');
define('DB_USER',    getenv('DB_USER')    ?: 'root');
define('DB_PASS',    getenv('DB_PASS')    ?: '');
define('DB_CHARSET', 'utf8mb4');

/** Compte système portant les données seed (ingrédients / recettes par défaut). */
define('SYSTEM_USER_ID', (int) (getenv('SYSTEM_USER_ID') ?: 1));

// 4. Application
define('APP_URL',          getenv('APP_URL') ?: 'http://localhost:3000');
define('SESSION_LIFETIME', (int) (getenv('SESSION_LIFETIME') ?: 1800));
define('CSRF_SECRET',      getenv('CSRF_SECRET') ?: 'changez-cette-cle-secrete-minimum-64-caracteres-xxxx');

// 5. PHPMailer (SMTP) — laissez MAIL_PASSWORD vide en dev si vous n'envoyez pas d'e-mails
define('MAIL_HOST',       getenv('MAIL_HOST')       ?: 'smtp.gmail.com');
define('MAIL_PORT',       (int) (getenv('MAIL_PORT') ?: 587));
define('MAIL_USERNAME',   getenv('MAIL_USERNAME')   ?: '');
define('MAIL_PASSWORD',   getenv('MAIL_PASSWORD')   ?: '');
define('MAIL_ENCRYPTION', getenv('MAIL_ENCRYPTION') ?: 'tls');
define('MAIL_FROM_EMAIL', getenv('MAIL_FROM')       ?: '');
define('MAIL_FROM_NAME',  getenv('MAIL_FROM_NAME')  ?: 'Planificateur de Repas');

// 6. Session sécurisée
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => SESSION_LIFETIME,
        'path'     => '/',
        'secure'   => (ENV === 'production'),
        'httponly' => true,
        'samesite' => 'Strict',
    ]);
    session_start();
}

// 7. Autoloaders
$composerAutoload = PHPMAILER_VENDOR . '/autoload.php';
if (file_exists($composerAutoload)) {
    require_once $composerAutoload;
} else {
    $src = PHPMAILER_VENDOR . '/phpmailer/phpmailer/src';
    if (is_dir($src)) {
        require_once $src . '/Exception.php';
        require_once $src . '/PHPMailer.php';
        require_once $src . '/SMTP.php';
    }
}

spl_autoload_register(function (string $fqcn): void {
    $prefix = 'App\\';
    if (strncmp($prefix, $fqcn, strlen($prefix)) !== 0) {
        return;
    }

    $relative  = substr($fqcn, strlen($prefix));
    $parts     = explode('\\', $relative);
    $className = array_pop($parts);
    $subDir    = strtolower(implode('/', $parts));

    $file = APP_PATH . '/' . ($subDir ? $subDir . '/' : '') . $className . '.php';

    if (file_exists($file)) {
        require_once $file;
        return;
    }

    // Repli insensible à la casse (macOS / déploiements hétérogènes)
    $dir = APP_PATH . '/' . ($subDir ? $subDir . '/' : '');
    if (!is_dir($dir)) {
        return;
    }
    foreach (scandir($dir) ?: [] as $entry) {
        if (strcasecmp($entry, $className . '.php') === 0) {
            require_once $dir . $entry;
            return;
        }
    }
});

require_once APP_PATH . '/core/Security.php';
if (!class_exists('App\\Core\\Security', false)) {
    class_alias('app\\core\\Security', 'App\\Core\\Security');
}
