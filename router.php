<?php

namespace meal_planner\Core\Router;

class Router
{
    // par méthode HTTP (GET / POST)
    private array $routes = [
        'GET' => [],
        'POST' => []
    ];

    // Enregistre une route GET
    public function get(string $path, $handler): void
    {
        $this->routes['GET'][$path] = $handler;
    }

    // Enregistre une route POST
    public function post(string $path, $handler): void
    {
        $this->routes['POST'][$path] = $handler;
    }

    // Analyse l'URL et exécute la bonne route
    public function dispatch(string $uri, string $method)
    {
        // Nettoie l'URL pour retirer les paramètres (?id=3)
        $uri = parse_url($uri, PHP_URL_PATH);

        // Parcourt toutes les routes correspondant à la méthode HTTP
        foreach ($this->routes[$method] as $route => $handler) {

            // Transforme les routes dynamiques 
            $pattern = preg_replace('#\{([a-zA-Z_]+)\}#', '([a-zA-Z0-9_-]+)', $route);
            $pattern = "#^" . $pattern . "$#";

            // Vérifie si l'URL correspond à la route
            if (preg_match($pattern, $uri, $matches)) {

                array_shift($matches);

                // Si le handler est une fonction anonyme (closure)
                if (is_callable($handler)) {
                    return call_user_func_array($handler, $matches);
                }

                // Si le handler est un contrôleur sous forme "Controller@method"
                if (is_string($handler) && strpos($handler, '@') !== false) {

                    
                    list($controller, $method) = explode('@', $handler);

                    // Construit le namespace complet du contrôleur
                    $controllerClass = "App\\Controllers\\$controller";

                    
                    if (!class_exists($controllerClass)) {
                        throw new \Exception("Controller $controllerClass not found");
                    }

                    // Instancie le contrôleur
                    $instance = new $controllerClass();

                    // Vérifie que la méthode existe dans le contrôleur
                    if (!method_exists($instance, $method)) {
                        throw new \Exception("Method $method not found in $controllerClass");
                    }

                    // Appelle la méthode du contrôleur avec les paramètres dynamiques
                    return call_user_func_array([$instance, $method], $matches);
                }
            }
        }

       
        http_response_code(404);
        echo "404 - Route not found";
    }
}
