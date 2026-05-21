<?php
/**
* config.php - configuration de la base de données 
* planificateur de Repas

* Fournit une connexion PDO unique (singleton) réutilisée
* dans tout le projet (modèles, API…).
*/

declare(strict_types=1);

// paramètres de connexion

define('DB_HOST', $_ENV['DB_HOST'] ?? 'localhost');
define('DB_PORT', $_ENV['DB_PORT'] ?? '3306');
define('DB_NAME', $_ENV['DB_NAME'] ?? 'meal_planner');
define('DB_USER', $_ENV['DB_USER'] ?? 'root');
define('DB_PASS', $_ENV['DB_PASS'] ?? '');
define('DB_CHARSET', 'utf8mb4');

// Headers communs à toute l'API --
header('Content-Type: application/json; charset=utf-8');
header('X-Content_Type-Options: nosniff');

// en production, restreindre l'origine :
// header('Access-Control-Allow-Origine: https://domain.com');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

/**
 *  Retourne la connexion PDO (instance unique)
 * 
 * @throws \RuntimeException si la connexion échoue
 * @return \PDO
 */

function getDB(): \PDO
{
    static $pdo = null;

    if ($pdo !== null) {
        return $pdo;
    }

    $dsn = sprintf(
        'mysql:host=%s;port=%s;dbname=%s;charset=%s',
        DB_HOST, DB_PORT, DB_NAME, DB_CHARSET
    );

    $options = [
        \PDO::ATTR_ERRMODE            => \PDO::ERRMODE_EXCEPTION,
        \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
        \PDO::ATTR_EMULATE_PREPARES   => false,
    ];

    try {
        $pdo = new \PDO($dsn, DB_USER, DB_PASS, $options);
    } catch (\PDOException $e) {
        //on ne divulgue pas les détails de connexion en production 
        error_log('[DB] connexion échouée: ' . $e->getMessage());
        http_response_code(503);
        echo json_encode(['success' => false, 'error' => 'Service temporairement indisponible']);
        exit;
    }

    return $pdo;

}

/**
 * Encode une réponse JSON et termine l'exécution.
 *
 * @param bool  $success
 * @param mixed $data     Données à retourner (succès)
 * @param string $error   Message d'erreur (échec)
 * @param int   $code     Code HTTP
 */

function jsonResponse(bool $success, $data = null, string $error = '', int $code = 200) : void
{
    http_response_code($code);
    $payload = ['success' => $success];
    if ($success && $data !== null) {
        $payload['data'] = $data;
    }
    if (!$success && $error !== '') {
        $payload['error'] = $error;
    }
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

?>