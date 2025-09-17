<?php
namespace app\Core;


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
     */
    public function dispatch(string $uri): void
    {

        $found = false;

        foreach ($this->routes as $routePath => $routeInfo) {
            $pattern = preg_replace('#\{([a-zA-Z0-9_]+)}#', '(?P<$1>[^/]+)', $routePath);
            $pattern = '#^' . $pattern . '$#';

            if (preg_match($pattern, $uri, $matches)) {
                $found = true;
                $controllerClass = $routeInfo['controller'];
                $methodName = $routeInfo['method'];

                if (class_exists($controllerClass)) {
                    $controller = new $controllerClass();

                    if (method_exists($controller, $methodName)) {
                        $params = [];
                        foreach ($matches as $key => $value) {
                            if (!is_int($key)) {
                                $params[$key] = $value;
                            }
                        }
                        call_user_func_array([$controller, $methodName], $params);
                    } else {
                        http_response_code(500);
                        echo "Erreur: La méthode '$methodName' n'existe pas dans le contrôleur '$controllerClass'.";
                    }
                } else {
                    http_response_code(500);
                    echo "Erreur: Le contrôleur '$controllerClass' n'a pas été trouvé.";
                }
                break;
            }
        }

        if (!$found) {
            throw new Exception('404');
        }
    }
}
