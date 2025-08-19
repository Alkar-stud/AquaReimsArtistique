<?php

use app\Attributes\Route;

$routes = [];

// Fonction récursive pour trouver tous les contrôleurs
function findControllers($dir, &$controllers) {
    foreach (glob("$dir/*.php") as $file) {
        $controllers[] = $file;
    }

    // Recherche dans les sous-dossiers
    foreach (glob("$dir/*", GLOB_ONLYDIR) as $subdir) {
        findControllers($subdir, $controllers);
    }
}

$controllers = [];
findControllers(__DIR__ . '/../app/Controllers', $controllers);

foreach ($controllers as $file) {
    require_once $file;

    // Extraction du namespace...
    $relativePath = str_replace(__DIR__ . '/../', '', $file);
    $relativePath = str_replace('.php', '', $relativePath);
    $namespace = str_replace('/', '\\', $relativePath);
    $className = "\\$namespace";

    if (class_exists($className)) {
        $reflectionClass = new ReflectionClass($className);

        // Utilisation du FQCN (Fully Qualified Class Name) pour les attributs
        $classAttributes = $reflectionClass->getAttributes('app\\Attributes\\Route');

        foreach ($classAttributes as $attribute) {
            $instance = $attribute->newInstance();
            $routes[$instance->path] = [
                'controller' => $className,
                'method' => 'index'
            ];
        }

        // Routes sur les méthodes
        foreach ($reflectionClass->getMethods() as $method) {
            $attributes = $method->getAttributes('app\\Attributes\\Route');
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