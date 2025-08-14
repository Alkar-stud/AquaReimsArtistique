<?php

namespace app\Controllers;

use app\Repository\UserRepository;
use app\Services\LogService;
use app\Enums\LogType;

abstract class AbstractController
{
    protected LogService $logService;

    public function __construct(bool $isPublicRoute = false)
    {
        $this->configureSession();
        $this->logService = new LogService();
        // Log URL et Route
        $this->logUrlAccess();
        $this->logRouteAccess();

        $this->checkUserSession($isPublicRoute);

    }

    protected function render(string $view, array $data = [], string $title = ''): void
    {
        extract($data);
        ob_start();

        $page = __DIR__ . '/../views/' . $view . '.html.php';

        if (!file_exists($page)) {
            ob_end_clean();
            throw new \RuntimeException("La vue '$page' n'existe pas.");
        }

        include $page;
        $content = ob_get_clean();
        require __DIR__ . '/../views/base.html.php';
    }

    public function checkUserSession(bool $isPublicRoute = false): void
    {
        // Démarrer la session seulement après configuration
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if ($isPublicRoute) {
            return;
        }

        if (!isset($_SESSION['user']) && !$isPublicRoute) {
            header('Location: /login');
            exit;
        }

        $timeout = 1800;

        if (!isset($_SESSION['user']['id'])) {
            $this->redirectToLogin('Votre session a expiré ou vous devez vous reconnecter.');
            return;
        }

        if (isset($_SESSION['user']['LAST_ACTIVITY']) && (time() - $_SESSION['user']['LAST_ACTIVITY'] > $timeout)) {
            $this->redirectToLogin('Votre session a expiré pour cause d\'inactivité. Veuillez vous reconnecter.');
            return;
        }

        // Régénérer l'ID de session périodiquement (toutes les 30 minutes)
        if (!isset($_SESSION['user']['LAST_REGENERATION']) ||
            (time() - $_SESSION['user']['LAST_REGENERATION'] > 1800)) {
            $this->regenerateSessionId();
            $_SESSION['user']['LAST_REGENERATION'] = time();
        }

        $_SESSION['user']['LAST_ACTIVITY'] = time();

        $userRepository = new UserRepository();
        $user = $userRepository->findById($_SESSION['user']['id']);

        if (!$user || $user->getSessionId() !== session_id()) {
            $this->redirectToLogin('Votre session n\'est plus valide. Veuillez vous reconnecter.');
            return;
        }
    }

    private function redirectToLogin(string $message): void
    {
        $flash = ['type' => 'warning', 'message' => $message];
        $_SESSION = [];
        session_destroy();
        session_start();
        $_SESSION['flash_message'] = $flash;
        header('Location: /login');
        exit;
    }

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

    private function logRouteAccess(): void
    {
        $route = $this->getCurrentRoute();
        $controller = static::class;
        $action = $this->getCurrentAction();

        $context = [
            'route_params' => $this->getRouteParams(),
            'execution_time_start' => microtime(true)
        ];

        $this->logService->logRoute($route, $controller, $action, $context);
    }

    private function getCurrentRoute(): string
    {
        // Récupérer la route actuelle depuis le router ou les attributs
        $reflection = new \ReflectionClass($this);
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

    protected function regenerateSessionId(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true); // true = supprime l'ancien fichier de session
        }
    }

    protected function validateCsrf(string $token): bool
    {
        return \app\Utils\CsrfHelper::validateToken($token);
    }

    protected function validateCsrfAndLog(string $submittedToken, string $action = 'unknown'): bool
    {
        $sessionToken = $_SESSION['csrf_token'] ?? '';

        if (empty($submittedToken) || empty($sessionToken) || !hash_equals($sessionToken, $submittedToken)) {
            $this->logService->log(LogType::ACCESS, "Tentative CSRF invalide sur action: $action", [
                'submitted_token_length' => strlen($submittedToken),
                'session_token_exists' => !empty($sessionToken),
                'ip' => $_SERVER['REMOTE_ADDR'] ?? '',
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
                'action' => $action
            ], 'DANGER');

            return false;
        }

        unset($_SESSION['csrf_token']);
        return true;
    }

    protected function getCsrfToken(): string
    {
        return \app\Utils\CsrfHelper::getToken();
    }

}