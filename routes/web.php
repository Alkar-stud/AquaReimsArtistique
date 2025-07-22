<?php

use App\Attributes\Route;

$routes = [];

foreach (glob(__DIR__ . '/../app/Controllers/*.php') as $file) {
    require_once $file;
    $className = 'app\\Controllers\\' . basename($file, '.php');
    if (class_exists($className)) {
        $reflection = new ReflectionClass($className);
        $attributes = $reflection->getAttributes(Route::class);
        foreach ($attributes as $attribute) {
            $instance = $attribute->newInstance();
            $routes[$instance->path] = $file;
        }
    }
}

return $routes;