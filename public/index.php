<?php
/**
 * index.php point d'entrée unique (front controller) 
 * 
 * ce fichier : il route toutes les requêtes HTTP vers les bons contrôleurs.
 */

require_once __DIR__ . '/../config/config.php';

// les chemins de base
define('BASE_PATH',       dirname(__DIR__));
define('APP_PATH',        BASE_PATH . '/app');
define('CONTROLLER_PATH', APP_PATH  . '/controllers');
define('MODEL_PATH',      APP_PATH  . '/models');
define('SERVICE_PATH',    APP_PATH  . '/services');
define('VIEW_PATH',       APP_PATH  . '/views');

// autoloader PSR-0 (controllers + models + services)
spl_autoload_register(function (string $class): void {
    $dirs = [CONTROLLER_PATH, MODEL_PATH, SERVICE_PATH, APP_PATH . '/core'];
    foreach ($dirs as $dir) {
        $file = $dir . '/' . $class . '.php';
        if (file_exists($file)) {
            require_once $file;
            return;
        }
    }
});

// headers communs
header('Content-Type: application/json; charset=UTF-8');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');

// CORS — à restreindre en production
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// répondre immédiatement aux pre-flight OPTIONS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// lecture de la requête
$uri    = trim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/');
$method = $_SERVER['REQUEST_METHOD'];

// décompose l'URL en segments : "api/ingredients/42" -> ['api', 'ingredients', '42']
$segments = explode('/', $uri);

// routeur

// structure attendue : /api/<resource>/[<id>]
$base     = $segments[0] ?? '';          // "api"  ou ""
$resource = $segments[1] ?? '';          // "ingredients" | "recipes" | "menus" | "auth"
$id       = isset($segments[2]) && is_numeric($segments[2])
              ? (int)$segments[2]
              : null;

// ── Page principale (SPA) ─────────────────────────────────────
if ($base === '' || $base === 'index.html') {
    header('Content-Type: text/html; charset=UTF-8');
    readfile(BASE_PATH . '/public/index.html');
    exit;
}

// ── API REST ──────────────────────────────────────────────────
if ($base !== 'api') {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'Route introuvable']);
    exit;
}

switch ($resource) {

    // ── Authentification ──────────────────────────────────────
    case 'auth':
    case 'login':
    case 'register':
        $controller = new AuthController();
        $controller->handle($method, $resource, $id);
        break;

    // ── Ingrédients ───────────────────────────────────────────
    case 'ingredients':
    case 'ingredient':
        $controller = new IngredientController();
        $controller->handle($method, $id);
        break;

    // ── Recettes ──────────────────────────────────────────────
    case 'recipes':
    case 'recipe':
        $controller = new RecipeController();
        $controller->handle($method, $id);
        break;

    // ── Menus hebdomadaires ───────────────────────────────────
    case 'menus':
    case 'menu':
        $controller = new MenuController();
        $controller->handle($method, $id);
        break;

    // ── Génération automatique de menu ────────────────────────
    case 'generate':
        $controller = new MealController();
        $controller->generate($method);
        break;

    // ── Export PDF ────────────────────────────────────────────
    case 'export-pdf':
    case 'export_pdf':
        $controller = new ExportController();
        $controller->exportPdf($method);
        break;

    // ── Export ICS (calendrier) ───────────────────────────────
    case 'export-ics':
    case 'export_ics':
        $controller = new ExportController();
        $controller->exportIcs($method);
        break;

    // ── Nutritionnel ──────────────────────────────────────────
    case 'nutrition':
        $controller = new NutritionController();
        $controller->handle($method, $id);
        break;

    // ── Route inconnue ────────────────────────────────────────
    default:
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'error'   => "Ressource « {$resource} » introuvable."
        ]);
        break;
}