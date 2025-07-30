<?php

use app\Attributes\Route;

$routes = [];

foreach (glob(__DIR__ . '/../app/Controllers/*.php') as $file) {
    require_once $file;

    $controllerBaseName = basename($file, '.php');
    $className = 'app\\Controllers\\' . $controllerBaseName;

    if (class_exists($className)) {
        $reflectionClass = new ReflectionClass($className);

        // On cherche d'abord une route sur la CLASSE elle-même
        $classAttributes = $reflectionClass->getAttributes(Route::class);
        foreach ($classAttributes as $attribute) {
            $instance = $attribute->newInstance();
            // Par convention, une route sur une classe appelle la méthode 'index'
            $routes[$instance->path] = [
                'controller' => $className,
                'method' => 'index'
            ];
        }

        // ENSUITE, on cherche des routes sur les MÉTHODES pour les cas spécifiques
        foreach ($reflectionClass->getMethods() as $method) {
            $attributes = $method->getAttributes(Route::class);

            foreach ($attributes as $attribute) {
                $instance = $attribute->newInstance();
                $routes[$instance->path] = [
                    'controller' => $className,
                    'method' => $method->getName()
                ];
            }
        }
    }
}

return $routes;