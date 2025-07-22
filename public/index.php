<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
date_default_timezone_set('Europe/Paris');
session_start();

//Chargement des dépendances
require_once __DIR__ . '/../vendor/autoload.php';
//Chargement des variables d'environnement pour la base de données
require_once __DIR__ . '/../config/database.php';
//Chargement des variables de configuration
require_once __DIR__ . '/../config/app.php';

// Chargement des routes
$routes = require __DIR__ . '/../routes/web.php';
$uri = strtok($_SERVER['REQUEST_URI'], '?');

// Determine if the application is in maintenance mode...
if (MAINTENANCE && $uri != '/login') {
    $uri = '/maintenance';
}

//On ajoutera plus tard si le $user est super admin
if (isset($routes[$uri])) {
    // Le nom complet de la classe est déjà dans le tableau
    $controllerClass = $routes[$uri];

    if (class_exists($controllerClass)) {
        $controller = new $controllerClass();
        $controller->index();
    } else {
        http_response_code(500);
        echo "Erreur: Le contrôleur '$controllerClass' n'a pas été trouvé.";
    }
} else {
    // Gérer la page 404 proprement
    http_response_code(404);
    // On peut aussi créer un ErrorController pour gérer ça
    $title = 'Erreur 404';
    $page = __DIR__ . '/../app/views/404.html.php';
    require __DIR__ . '/../app/views/base.html.php';
}
