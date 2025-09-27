<?php
namespace app\Core;

use ReflectionException;
use Exception;

class Router
{
    private array $routes;

    public function __construct(array $routes)
    {
        $this->routes = $routes;
    }

    /**
     * @throws Exception
     * @throws ReflectionException
     */
    public function dispatch(string $uri): void
    {
        foreach ($this->routes as $route) {
            // Remplace les placeholders comme {id} par une expression régulière.
            // Utilise les 'requirements' si elles sont définies, sinon utilise [^/]+ (tout sauf un slash).
            $pattern = preg_replace_callback(
                '#\{([a-zA-Z0-9_]+)}#',
                function ($matches) use ($route) {
                    $paramName = $matches[1];
                    $requirement = $route['requirements'][$paramName] ?? '[^/]+';
                    return '(?P<' . $paramName . '>' . $requirement . ')';
                },
                $route['path']
            );

            $pattern = '#^' . $pattern . '$#';

            if (preg_match($pattern, $uri, $matches)) {
                $controllerClass = $route['controller'];
                $methodName = $route['method'];

                if (class_exists($controllerClass)) {
                    // --- Utilisation de notre nouveau conteneur ---
                    $container = new Container();
                    // On demande simplement le contrôleur, le conteneur fait tout le travail !
                    $controller = $container->get($controllerClass);

                    if (method_exists($controller, $methodName)) {
                        // Extrait les paramètres nommés de l'URL
                        $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
                        call_user_func_array([$controller, $methodName], $params);
                    } else {
                        http_response_code(500);
                        echo "Erreur: La méthode '$methodName' n'existe pas dans le contrôleur '$controllerClass'.";
                    }
                } else {
                    http_response_code(500);
                    echo "Erreur: Le contrôleur '$controllerClass' n'a pas été trouvé.";
                }
                return; // Route trouvée et exécutée, on arrête le traitement.
            }
        }

        // Si la boucle se termine, aucune route n'a été trouvée.
        throw new Exception('404');
    }
}
