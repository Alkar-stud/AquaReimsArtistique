<?php

use Dotenv\Dotenv;
use Dotenv\Exception\InvalidPathException;

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
date_default_timezone_set('Europe/Paris');
session_start();
$session_id = session_id();

//Chargement des dépendances
require_once __DIR__ . '/../vendor/autoload.php';


try {
    // On charge d'abord le fichier le plus spécifique (.env.local) s'il existe.
    //    Ses variables auront la priorité, car elles sont chargées en premier.
    if (file_exists(__DIR__ . '/../.env.local')) {
        Dotenv::createImmutable(__DIR__ . '/../', '.env.local')->load();
    }

    if (file_exists(__DIR__ . '/../.env.prod')) {
        Dotenv::createImmutable(__DIR__ . '/../', '.env.prod')->load();
    }

    // ENSUITE, on charge le fichier de base .env.
    //    La méthode load() ne remplacera PAS les variables déjà chargées depuis .env.local.
    //    Elle lèvera une exception si. Env est manquant, ce qui est le comportement attendu.
    Dotenv::createImmutable(__DIR__ . '/../', '.env')->load();

} catch (InvalidPathException $e) {
    // Cette erreur ne se déclenchera que si le. Env de base est manquant.
    http_response_code(503);
    die(
        '<div style="font-family: sans-serif; padding: 2em; border: 2px solid #d00; background: #fee; color: #333;">' .
        '<h2>Erreur Critique de Configuration</h2>' .
        '<p>Le fichier de configuration de base <code>.env</code> est introuvable. L\'application ne peut pas démarrer.</p>' .
        '</div>'
    );
}


// --- Chargement de la configuration de l'application ---
require_once __DIR__ . '/../config/env.php';
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
