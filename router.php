<?php

namespace meal_planner\Core\Router;

class Router
{
    private array $routes = [
        'GET' => [],
        'POST' => []
    ];

    public function get(string $path, $handler): void
    {
        $this->routes['GET'][$path] = $handler;
    }

    public function post(string $path, $handler): void
    {
        $this->routes['POST'][$path] = $handler;
    }

    public function dispatch(string $uri, string $method)
    {
        $uri = parse_url($uri, PHP_URL_PATH);

        foreach ($this->routes[$method] as $route => $handler) {

            $pattern = preg_replace('#\{([a-zA-Z_]+)\}#', '([a-zA-Z0-9_-]+)', $route);
            $pattern = "#^" . $pattern . "$#";

            if (preg_match($pattern, $uri, $matches)) {
                array_shift($matches);

                if (is_callable($handler)) {
                    return call_user_func_array($handler, $matches);
                }

                if (is_string($handler) && strpos($handler, '@') !== false) {
                    list($controller, $method) = explode('@', $handler);
                    $controllerClass = "App\\Controllers\\$controller";

                    if (!class_exists($controllerClass)) {
                        throw new \Exception("Controller $controllerClass not found");
                    }

                    $instance = new $controllerClass();

                    if (!method_exists($instance, $method)) {
                        throw new \Exception("Method $method not found in $controllerClass");
                    }

                    return call_user_func_array([$instance, $method], $matches);
                }
            }
        }

        http_response_code(404);
        echo "404 - Route not found";
    }
}
