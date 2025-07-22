<?php

use App\Attributes\Route;

$routes = [];

foreach (glob(__DIR__ . '/../app/Controllers/*.php') as $file) {
    require_once $file;

    // On récupère le nom de base du fichier, qui est aussi le nom de la classe
    $controllerBaseName = basename($file, '.php');
    $className = 'app\\Controllers\\' . $controllerBaseName;

    if (class_exists($className)) {
        $reflection = new ReflectionClass($className);
        $attributes = $reflection->getAttributes(Route::class);
        foreach ($attributes as $attribute) {
            $instance = $attribute->newInstance();
            // On stocke directement le nom complet de la classe
            $routes[$instance->path] = $className; // $className vaut 'app\Controllers\HomeController'
        }
    }
}

return $routes;