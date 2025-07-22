<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
date_default_timezone_set('Europe/Paris');
session_start();

//Chargement des dépendances
require_once __DIR__ . '/../vendor/autoload.php';
// Chargement des variables d'environnement
if (file_exists(__DIR__ . '/../.env.local')) {
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../', '.env.local');
} else {
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
}
$dotenv->load();

//Chargement des variables d'environnement pour la base de données
require_once __DIR__ . '/../config/database.php';
//Chargement des variables de configuration
require_once __DIR__ . '/../config/app.php';

// Chargement des routes
$routes = require __DIR__ . '/../routes/web.php';
$uri = strtok($_SERVER['REQUEST_URI'], '?');

// Determine if the application is in maintenance mode...
/** @noinspection PhpUndefinedConstantInspection */
if (MAINTENANCE && $uri != '/login') {
    $uri = '/maintenance';
}

//On ajoutera plus tard si le $user est super admin
if (isset($routes[$uri])) {
    // On récupère le tableau contenant le contrôleur et la méthode
    $routeInfo = $routes[$uri];
    $controllerClass = $routeInfo['controller'];
    $methodName = $routeInfo['method'];

    if (class_exists($controllerClass)) {
        $controller = new $controllerClass();

        // On vérifie que la méthode existe avant de l'appeler
        if (method_exists($controller, $methodName)) {
            // On appelle la méthode spécifiée (ex: index() ou logout())
            $controller->$methodName();
        } else {
            http_response_code(500);
            echo "Erreur: La méthode '$methodName' n'existe pas dans le contrôleur '$controllerClass'.";
        }
    } else {
        http_response_code(500);
        echo "Erreur: Le contrôleur '$controllerClass' n'a pas été trouvé.";
    }
} else {
    // Gérer la page 404
    http_response_code(404);
    $title = 'Erreur 404 - Page non trouvée';

    // On utilise la même logique que la méthode render() pour générer le contenu
    ob_start();
    require_once __DIR__ . '/../app/views/404.html.php';
    $content = ob_get_clean();

    // On inclut le template de base qui utilisera $title et $content
    require_once __DIR__ . '/../app/views/base.html.php';
}
