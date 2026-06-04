<?php
namespace app\core;

/**
 * Router — Routeur HTTP minimaliste
 *
 * Enregistre des routes (GET / POST) et les fait correspondre
 * à des closures ou à des méthodes de contrôleurs.
 *
 * Usage dans public/index.php :
 *
 *   $router = new Router();
 *
 *   $router->get('/login',  [AuthController::class, 'showLogin']);
 *   $router->post('/login', [AuthController::class, 'login']);
 *
 *   // Route avec paramètre :id
 *   $router->delete('/ingredients/:id', [MealController::class, 'destroyIngredient']);
 *
 *   $router->dispatch();
 */

class Router
{
    // @var array<string, array> table des routes enregistrées
    private array $routes = [];

    // enregistrement des routes
    public function get(string $path, array|callable $handler): void
    {
        $this->addRoute('Get', $path, $handler);
    }

    public function post(string $path, array|callable $handler): void
    {
        $this->addRoute('Post', $path, $handler);
    }

    public function put(string $path, array|callable $handler): void
    {
        $this->addRoute('Put', $path, $handler);
    }

    public function delete(string $path, array|callable $handler): void
    {
        $this->addRoute('DELETE', $path, $handler);
    }

    /**
     * Enregistre une route avec la méthode et le chemin donnés.
     *
     * @param string         $method  HTTP method (GET, POST…)
     * @param string         $path    Chemin URL, ex : '/user/:id'
     * @param array|callable $handler [Classe::class, 'méthode'] ou Closure
     */
    private function addRoute(string $method, string $path, array|callable $handler): void
    {
        /* Convertit :param en groupe de capture regex */
        $pattern = preg_replace('/:([a-zA-Z_]+)/', '(?P<$1>[^/]+)', $path);
        $pattern = '#^' . $pattern . '$#';

        $this->routes[$method][] = [
            'pattern' => $pattern,
            'handler' => $handler,
        ];
    }

    /* ── Dispatch  */

    /**
     * Analyse la requête entrante et appelle le handler correspondant.
     * Retourne une réponse 404 si aucune route ne correspond.
     */
    public function dispatch(): void
    {
        Security::secureHeaders();

        /* Méthode HTTP — supporte POST avec _method (PUT, DELETE depuis HTML form) */
        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        if ($method === 'POST' && isset($_POST['_method'])) {
            $method = strtoupper($_POST['_method']);
        }

        /* URI sans query string */
        $uri = strtok($_SERVER['REQUEST_URI'] ?? '/', '?');
        $uri = '/' . trim($uri, '/') ?: '/';

        $routeGroup = $this->routes[$method] ?? [];

        foreach ($routeGroup as $route) {
            if (preg_match($route['pattern'], $uri, $matches)) {
                /* Paramètres nommés extraits depuis l'URL */
                $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);

                $this->callHandler($route['handler'], $params);
                return;
            }
        }

        /* Aucune route trouvée → 404 */
        $this->notFound();
    }

    /**
     * Appelle le handler (Closure ou [Classe, méthode]).
     *
     * @param array|callable $handler
     * @param array          $params  Paramètres extraits de l'URL
     */
    private function callHandler(array|callable $handler, array $params): void
    {
        if (is_callable($handler)) {
            call_user_func($handler, $params);
            return;
        }

        [$class, $method] = $handler;

        if (!class_exists($class)) {
            throw new \RuntimeException("Contrôleur introuvable : {$class}");
        }

        $controller = new $class();

        if (!method_exists($controller, $method)) {
            throw new \RuntimeException("Méthode introuvable : {$class}::{$method}");
        }

        $controller->$method($params);
    }

    /**
     * Réponse 404 par défaut.
     */
    private function notFound(): void
    {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Route introuvable.']);
    }
}

?>