<?php

namespace app\Controllers;

use app\Repository\User\UserRepository;
use app\Services\Logs\LogService;
use app\Services\SessionValidationService;
use app\Utils\CsrfHelper;
use app\Utils\TemplateEngine;
use DateMalformedStringException;
use Exception;
use JetBrains\PhpStorm\NoReturn;
use Random\RandomException;
use ReflectionClass;
use RuntimeException;

abstract class AbstractController
{
    protected LogService $logService;
    protected SessionValidationService $sessionValidationService;

    /**
     * @throws DateMalformedStringException
     * @throws RandomException
     */
    public function __construct(bool $isPublicRoute = false)
    {
        $this->configureSession();
        $this->logService = new LogService();
        $this->sessionValidationService = new SessionValidationService();
        // Log URL et Route
        $this->logUrlAccess();
        $this->logRouteAccess();

        $this->checkUserSession($isPublicRoute);

    }

    /**
     * Pour permettre au controller d'afficher la vue.
     *
     * @param string $view
     * @param array $data
     * @param string $title
     * @param bool $partial
     * @return void
     */
    protected function render(string $view, array $data = [], string $title = '', bool $partial = false): void
    {
        $content = '';
        $engine = new TemplateEngine();

        // Préparation des variables pour les templates, afin d'éviter la logique PHP dans les vues.
        $uri = strtok($_SERVER['REQUEST_URI'], '?');
        $data['uri'] = $uri;
        $data['is_gestion_page'] = str_starts_with($uri, '/gestion');
        $data['load_ckeditor'] = $data['is_gestion_page'] && (str_starts_with($uri, '/gestion/mail_templates') || str_starts_with($uri, '/gestion/accueil'));

        $templateTpl = __DIR__ . '/../views/templates/' . $view . '.tpl';

        // Détermine si c'est un template .tpl ou un ancien .html.php
        if (file_exists($templateTpl)) {
            $content = $engine->render($templateTpl, $this->prepareLoopData($data));
        } else {
            // On garde l'ancien chemin le temps de tout migrer
            $templatePhp = __DIR__ . '/../views/' . $view . '.html.php';
            if (!file_exists($templatePhp)) {
                throw new RuntimeException("La vue '$view' n'a pas été trouvée.");
            }
            extract($data);
            ob_start();
            include $templatePhp;
            $content = ob_get_clean();
        }

        if ($partial) {
            // Affiche juste le fragment demandé
            echo $content;
        } else {
            // Affiche tout avec le layout global .tpl
            $layoutData = array_merge($data, [
                'title' => $title,
                'content' => $content
            ]);
            echo $engine->render(__DIR__ . '/../views/templates/layout/base.tpl', $layoutData);
        }
    }

    /**
     * Enrichit les données pour les boucles en ajoutant des métadonnées utiles.
     * @param array $data Les données à passer à la vue.
     * @return array Les données enrichies.
     */
    private function prepareLoopData(array $data): array
    {
        foreach ($data as $key => $value) {
            if (is_array($value) && !empty($value) && array_keys($value) === range(0, count($value) - 1)) {
                $loopData = [];
                $total = count($value);
                foreach ($value as $index => $item) {
                    $loopData[] = [
                        'item' => $item,
                        'index' => $index,
                        'first' => ($index === 0),
                        'last' => ($index === $total - 1),
                        'count' => $total
                    ];
                }
                $data[$key . '_loop'] = $loopData;
            }
        }
        return $data;
    }


    /**
     * Vérifie la session de l'utilisation
     * @throws DateMalformedStringException
     * @throws Exception
     */
    public function checkUserSession(bool $isPublicRoute = false): void
    {
        if ($isPublicRoute) {
            return;
        }

        // Si la session utilisateur n'existe pas ou est incomplète, on déconnecte.
        if (!isset($_SESSION['user']['id']) || !isset($_SESSION['user']['LAST_ACTIVITY'])) {
            $this->logoutAndRedirect('Votre session est invalide ou a expiré. Veuillez vous reconnecter.');
            return;
        }

        // Vérifier le timeout d'inactivité en utilisant le service dédié
        if (!$this->sessionValidationService->isSessionActive($_SESSION['user'] ?? null, 'LAST_ACTIVITY', TIMEOUT_SESSION)) {
            $this->logoutAndRedirect('Votre session a expiré pour cause d\'inactivité. Veuillez vous reconnecter.');
            return;
        }

        // Régénérer l'ID de session périodiquement (toutes les 30 minutes)
        if (!isset($_SESSION['user']['LAST_REGENERATION']) || (time() - $_SESSION['user']['LAST_REGENERATION'] > 1800)) { // 30 minutes
            $this->regenerateSessionId(true); // true pour détruire l'ancienne session
            $_SESSION['user']['LAST_REGENERATION'] = time();
        }

        // Mettre à jour le timestamp de la dernière activité
        $_SESSION['user']['LAST_ACTIVITY'] = time();

        // Vérifier que l'ID de session correspond à celui en base de données
        $userRepository = new UserRepository();
        $user = $userRepository->findById($_SESSION['user']['id']);

        if (!$user || $user->getSessionId() !== session_id()) {
            $this->logoutAndRedirect('Votre session n\'est plus valide (connexion depuis un autre appareil ?). Veuillez vous reconnecter.');
        }
    }

    /**
     * Centralise la logique de déconnexion de l'utilisateur.
     * Nettoie la session, définit un message flash et redirige vers la page de connexion.
     * @param string $message Le message à afficher à l'utilisateur.
     */
    #[NoReturn] private function logoutAndRedirect(string $message): void
    {
        $flash = ['type' => 'warning', 'message' => $message];
        $_SESSION = [];
        session_destroy();
        session_start();
        $_SESSION['flash_message'] = $flash;
        header('Location: /login');
        exit;
    }

    /**
     * Journalise les connexions
     * @return void
     */
    private function logUrlAccess(): void
    {
        $url = $_SERVER['REQUEST_URI'] ?? '';
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

        $context = [
            'query_params' => $_GET,
            'referer' => $_SERVER['HTTP_REFERER'] ?? null,
            'is_ajax' => $this->isAjaxRequest()
        ];

        $this->logService->logUrl($url, $method, $context);
    }

    /**
     * Journalise l'accès à la route
     * @return void
     * @throws RandomException
     */
    private function logRouteAccess(): void
    {
        $route = $this->getCurrentRoute();
        $controller = static::class;
        $action = $this->getCurrentAction();

        $requestId = $_SERVER['REQUEST_ID'] ?? bin2hex(random_bytes(8));
        $_SERVER['REQUEST_ID'] = $requestId;

        $context = [
            'route_params' => $this->getRouteParams(),
            'execution_time_start' => microtime(true),
            'request_id' => $requestId,
            'user_id' => $_SESSION['user']['id'] ?? null
        ];

        $this->logService->logRoute($route, $controller, $action, $context);
    }

    private function getCurrentRoute(): string
    {
        // Récupérer la route actuelle depuis le router ou les attributs
        $reflection = new ReflectionClass($this);
        $attributes = $reflection->getAttributes();

        foreach ($attributes as $attribute) {
            if ($attribute->getName() === 'app\Attributes\Route') {
                return $attribute->getArguments()[0] ?? 'unknown';
            }
        }

        return $_SERVER['REQUEST_URI'] ?? 'unknown';
    }

    private function getCurrentAction(): string
    {
        // Détecter l'action courante (simplifiée)
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        return $method === 'POST' ? 'store/update' : 'index/show';
    }

    private function getRouteParams(): array
    {
        return array_merge($_GET, $_POST);
    }

    private function isAjaxRequest(): bool
    {
        return isset($_SERVER['HTTP_X_REQUESTED_WITH']) &&
            strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    }

    /**
     * Initialise la configuration de session avant son démarrage
     * @return void
     */
    protected function configureSession(): void
    {
        // Ne configurer que si aucune session n'est active
        if (session_status() === PHP_SESSION_NONE) {
            $config = require __DIR__ . '/../../config/session.php';
            $env = $_ENV['APP_ENV'] ?? 'local';
            $sessionConfig = $config[$env] ?? $config['local'];

            foreach ($sessionConfig as $key => $value) {
                if ($key === 'session_name') {
                    session_name($value);
                } else {
                    ini_set("session.$key", $value ? '1' : '0');
                }
            }
        }
    }

    protected function regenerateSessionId(bool $destroyOldSession = false): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id($destroyOldSession);
        }
    }

    /**
     * Récupère le token CSRF soumis, quelle que soit la méthode (POST, JSON, GET).
     * @return string|null Le token trouvé, ou null.
     */
    private function getSubmittedCsrfToken(): ?string
    {
        if (!empty($_POST['csrf_token'])) {
            return $_POST['csrf_token'];
        }

        // Le flux ne peut être lu qu'une fois. On le met en cache si besoin.
        static $json_input = null;
        if ($json_input === null) {
            $json_input = json_decode(file_get_contents('php://input'), true);
        }

        if (is_array($json_input) && !empty($json_input['csrf_token'])) {
            return $json_input['csrf_token'];
        }

        return $_GET['csrf_token'] ?? null;
    }

    /**
     * Vérifie le token CSRF de la requête et arrête l'exécution avec une erreur JSON si invalide.
     * @param string $action L'action en cours pour le logging.
     * @param bool $consume Indique si le token doit être consommé (usage unique).
     */
    protected function checkCsrfOrExit(string $action, bool $consume = true): void
    {
        $token = $this->getSubmittedCsrfToken();

        if ($token === null || !CsrfHelper::validateToken($token, $action, $consume)) {
            // La méthode json() contient un exit.
            $this->json(['success' => false, 'error' => 'La session a expiré ou la requête est invalide. Veuillez rafraîchir la page.']);
        }
    }


    /**
     * @throws RandomException
     */
    protected function getCsrfToken(): string
    {
        return CsrfHelper::getToken();
    }

    protected function json(array $data): void
    {
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }

}