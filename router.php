<?php

namespace 

class Router
{
    // Tableau qui contient toutes les routes enregistrées
    private array $routes = [
        'GET'  => [],
        'POST' => []
    ];

    // Enregistre une route GET
    public function get(string $path, $handler)
    {
        $this->routes['GET'][$path] = $handler;
    }

    // Enregistre une route POST
    public function post(string $path, $handler)
    {

        $this->routes['POST'][$path] = $handler;
    }

    // fonction qui choisit la bonne route et l'exécute
    public function dispatch()
    {
        // Récupère l'URL actuelle 
        $uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

        // Récupère la méthode HTTP 
        $method = $_SERVER['REQUEST_METHOD'];

        // On parcourt toutes les routes enregistrées pour cette méthode
        foreach ($this->routes[$method] as $route => $handler) {

          
            
          if (strpos($route, ':id') !== false) {

    $pattern = str_replace(':id', '([0-9]+)', $route);

    if (preg_match('#^' . $pattern . '$#', $uri, $matches)) {

        return $this->callHandler($handler, [$matches[1]]);
    }
}

          
            if ($route === $uri) {
                return $this->callHandler($handler);
            }
        }

        // Si aucune route ne correspond
        echo "404 - Page non trouvée";
    }

    // Fonction qui exécute le handler
    private function callHandler($handler, array $params = [])
    {
        // Si c'est une fonction anonyme 
        if (is_callable($handler)) {
            return call_user_func_array($handler, $params);
        }

        // Si c'est un contrôleur 
        if (is_array($handler)) {
            $controller = new $handler[0](); // instancie le contrôleur
            $method = $handler[1];          // récupère le nom de la méthode

            // Appelle la méthode du contrôleur
            return call_user_func_array([$controller, $method], $params);
        }

        // Si rien ne correspond
        echo "Erreur : handler invalide";
    }
}
