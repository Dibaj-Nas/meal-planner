<?php
declare(strict_types=1);

/**
 * api.php — Point d'entrée Ajax unique
 * Routes : api.php/auth/login, api.php/ingredients, api.php/menus/generate, etc.
 */

require_once __DIR__ . '/config/config.php';

ob_start();
ini_set('display_errors', '0');

header('Content-Type: application/json; charset=UTF-8');
header('X-Content-Type-Options: nosniff');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];
$path   = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '';
$base   = '/api.php';
$route  = '';

if (str_starts_with($path, $base)) {
    $route = trim(substr($path, strlen($base)), '/');
} else {
    $route = trim((string) ($_GET['route'] ?? ''), '/');
}

$segments = $route !== '' ? explode('/', $route) : [];
$resource = $segments[0] ?? '';
$second   = $segments[1] ?? null;
$third    = $segments[2] ?? null;
$id       = null;
$action   = null;

if ($second !== null && ctype_digit($second)) {
    $id     = (int) $second;
    $action = $third;
} elseif ($second !== null && $third !== null && ctype_digit($third)) {
    $action = $second;
    $id     = (int) $third;
} elseif ($second !== null) {
    $action = $second;
}

try {
    switch ($resource) {
        case 'auth':
            (new App\Controllers\AuthController())->handle($method, $action ?? '');
            break;

        case 'ingredients':
        case 'ingredient':
            (new App\Controllers\IngredientController())->handle($method, $id);
            break;

        case 'recipes':
        case 'recipe':
            (new App\Controllers\RecipeController())->handle($method, $id);
            break;

        case 'menus':
        case 'menu':
            (new App\Controllers\MenuController())->handle($method, $action, $id);
            break;

        case 'account':
            (new App\Controllers\AccountController())->handle($method, $action, $id);
            break;

        default:
            App\Core\Api::json(['success' => false, 'error' => "Ressource « {$resource} » introuvable."], 404);
    }
} catch (\Throwable $e) {
    $message = APP_DEBUG ? $e->getMessage() : 'Erreur serveur.';
    App\Core\Api::json(['success' => false, 'error' => $message], 500);
}
