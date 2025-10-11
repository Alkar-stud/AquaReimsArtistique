<?php

use app\Controllers\ErrorController;
use app\Core\Database;
use app\Core\Container;
use app\Core\Router;
use app\Services\Log\Logger;
use Dotenv\Dotenv;
use app\Services\Log\RequestContext;
use Dotenv\Exception\InvalidPathException;

date_default_timezone_set('Europe/Paris');

//Chargement des dépendances
require_once __DIR__ . '/../vendor/autoload.php';

try {
    // On charge d'abord le fichier le plus spécifique (.env.local) s'il existe.
    //    Ses variables auront la priorité, car elles sont chargées en premier.
    if (file_exists(__DIR__ . '/../.env.local')) {
        Dotenv::createImmutable(__DIR__ . '/../', '.env.local')->load();
    } elseif (file_exists(__DIR__ . '/../.env.prod')) {
        Dotenv::createImmutable(__DIR__ . '/../', '.env.prod')->load();
    } else if (file_exists(__DIR__ . '/../.env.docker')) {
        Dotenv::createImmutable(__DIR__ . '/../', '.env.docker')->load();
    }

    // ENSUITE, on charge le fichier de base .env.
    //    La méthode load() ne remplacera PAS les variables déjà chargées depuis .env.*.
    //    Elle lèvera une exception si .env est manquant
    Dotenv::createImmutable(__DIR__ . '/../', '.env')->load();

} catch (InvalidPathException $e) {
    // Cette erreur ne se déclenchera que si le .env de base est manquant.
    http_response_code(503);
    die(
        '<div style="font-family: sans-serif; padding: 2em; border: 2px solid #d00; background: #fee; color: #333;">' .
        '<h2>Erreur Critique de Configuration</h2>' .
        '<p>Le fichier de configuration de base <code>.env</code> est introuvable. L\'application ne peut pas démarrer.</p>' .
        '</div>'
    );
}

// --- Configuration de la gestion des erreurs en fonction de l'environnement ---
$appEnv = $_ENV['APP_ENV'] ?? 'local';

if ($appEnv === 'prod') {
    // En production : on n'affiche JAMAIS les erreurs à l'utilisateur.
    ini_set('display_errors', 0);
    ini_set('display_startup_errors', 0);
    error_reporting(E_ALL);
    // On s'assure que les erreurs sont bien enregistrées dans un fichier de log.
    ini_set('log_errors', 1);
} else {
    // En développement : on affiche tout pour faciliter le débogage.
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
}

// Context de requête
RequestContext::boot();

// Init logger depuis config
$sec = require __DIR__ . '/../config/security.php';
$cfg = require __DIR__ . '/../config/logging.php';
$handlers = [];
foreach ($cfg['handlers'] as $def) {
    $class = $def['class'];
    $args = $def['args'] ?? [];
    try { $handlers[] = new $class(...$args); } catch (Throwable) { /* ignore */ }
}
Logger::init($handlers, $sec['sensitive_data_keys'] ?? []);

// Log d'accès en fin de requête
register_shutdown_function(static function () {
    Logger::get()->access([
        'status' => http_response_code(),
        'route' => $_SERVER['REQUEST_URI'] ?? '/',
    ]);
});

//On récupère la route courante
$uri = strtok($_SERVER['REQUEST_URI'], '?');

// --- Chargement de la configuration de l'application ---
require_once __DIR__ . '/../config/env.php';
//Chargement des variables d'environnement pour la base de données
require_once __DIR__ . '/../config/database.php';
// Chargement des routes
$routes = require __DIR__ . '/../routes/web.php';

//Après, il faut que tout soit installé en BDD
// Si l'URI n'est pas la page d'installation, on vérifie si l'application est prête.
if ($uri !== '/install' && !str_starts_with($uri, '/install/')) {
    try {
        if (!Database::isInstalled()) {
            // Si la table des migrations n'existe pas, on affiche un message clair.
            http_response_code(503); // Service Unavailable
            die(
                '<div style="font-family: sans-serif; margin: 2em; padding: 2em; border: 2px solid #033BA8; border-radius: 5px; background: #E8F8FF; color: #2A2A2A; text-align: center;">' .
                '<h2>Application non installée</h2>' .
                '<p>L\'application ne semble pas encore être installée.</p>' .
                '<p>Veuillez suivre le lien ci-dessous pour finaliser la configuration.</p>' .
                '<p style="margin-top: 1.5em;"><a href="/install" style="padding: 10px 20px; background-color: #033BA8; color: #fff; text-decoration: none; border-radius: 5px; font-weight: bold; display: inline-block; transition: background-color 0.2s;">Lancer l\'installation</a></p>' .
                '</div>'
            );
        }

        // Si l'installation est détectée, on peut charger la configuration de l'application.
        require_once __DIR__ . '/../config/app.php';

        // Et vérifier le mode maintenance.
        /** @noinspection PhpUndefinedConstantInspection */
        if (defined('MAINTENANCE') && MAINTENANCE && $uri !== '/login' && !str_starts_with($uri, '/gestion/configuration/configs')) {
            $uri = '/maintenance';
        }
    } catch (PDOException $e) {
        // Si une erreur de connexion BDD survient (ex : mauvais mot de passe dans .env),
        // on affiche une erreur claire au lieu d'une page blanche.
        http_response_code(503);
        die("Erreur critique de connexion à la base de données. Vérifiez vos identifiants dans le fichier .env. Détail : " . htmlspecialchars($e->getMessage()));
    }
}

// --- Configuration du Conteneur d'Injection de Dépendances ---
// On crée une seule instance du conteneur qui sera utilisée partout.
$container = new Container();

// --- Utilisation du routeur ---
$router = new Router($routes);
try {
    $router->dispatch($uri, $container); // On passe le conteneur au routeur
} catch (Exception $e) {
    if ($e->getMessage() === '404') {
        $controller = new ErrorController();
        $controller->notFound();
        exit;
    }
}
