<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
date_default_timezone_set('Europe/Paris');
session_start();

// Determine if the application is in maintenance mode...
if (file_exists($maintenance = __DIR__.'/../storage/framework/maintenance.php')) {
    require $maintenance;
}


require_once __DIR__ . '/../vendor/autoload.php';

$routes = require __DIR__ . '/../routes/web.php';
$uri = strtok($_SERVER['REQUEST_URI'], '?');

if (isset($routes[$uri])) {
    // Récupère le nom du contrôleur à partir du chemin
    $controllerFile = $routes[$uri];
    $controllerClass = 'app\\Controllers\\' . basename($controllerFile, '.php');
    if (class_exists($controllerClass)) {
        $controller = new $controllerClass();
        $controller->index();
    } else {
        http_response_code(500);
        echo 'Contrôleur non trouvé';
    }
} else {
    http_response_code(404);
    $title = 'Erreur 404';
    require __DIR__ . '/../config/env.php';
    $config = $config ?? [];
    $page = __DIR__ . '/../app/views/404.html.php';
    require __DIR__ . '/../app/views/base.html.php';
}