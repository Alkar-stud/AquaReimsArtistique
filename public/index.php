<?php

use Dotenv\Dotenv;
use app\Core\Router;
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


// Determine si l'application est en mode maintenance...
/** @noinspection PhpUndefinedConstantInspection */
if (MAINTENANCE && $uri != '/login') {
    $uri = '/maintenance';
}

// Utilisation du routeur
$router = new Router($routes);
$router->dispatch($uri);