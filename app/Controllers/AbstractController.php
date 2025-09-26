<?php

namespace app\Controllers;

use app\Core\TemplateEngine;
use app\Models\User\User;
use app\Repository\User\UserRepository;
use app\Services\FlashMessageService;
use app\Services\Security\SessionValidateService;
use app\Services\Security\CsrfService;
use app\Utils\DurationHelper;
use Throwable;

abstract class AbstractController
{
    protected FlashMessageService $flashMessageService;
    private SessionValidateService $sessionValidateService;
    protected CsrfService $csrfService;
    protected ?User $currentUser = null;

    public function __construct(bool $isPublicRoute = false)
    {
        $this->configureSession();
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $this->flashMessageService = new FlashMessageService();
        $this->sessionValidateService = new SessionValidateService();
        $this->csrfService = new CsrfService();

        // Valide automatiquement le CSRF pour les méthodes non sûres
        $this->maybeEnforceCsrf();

        // Vérifier la session de l'utilisateur
        try {
            $this->checkUserSession($isPublicRoute);
        } catch (Throwable) {
            $this->logoutAndRedirect('Une erreur de session est survenue. Veuillez vous reconnecter.');
        }
    }

    protected function render(string $view, array $data = [], string $title = '', bool $partial = false): void
    {
        $engine = new TemplateEngine();

        $uri = strtok($_SERVER['REQUEST_URI'], '?');
        $data['uri'] = $uri;
        $data['is_gestion_page'] = str_starts_with($uri, '/gestion');
        $data['load_ckeditor'] = $data['is_gestion_page'] && (str_starts_with($uri, '/gestion/mail_templates') || str_starts_with($uri, '/gestion/accueil'));

        // Injecte automatiquement le token CSRF si non fourni
        $data['csrf_token'] ??= $this->csrfService->getToken($this->getCsrfContext());

        // Centralisation de la récupération des messages flash
        $data['flash_message'] = $this->flashMessageService->getFlashMessage();
        $this->flashMessageService->unsetFlashMessage();

        if (($_ENV['APP_DEBUG'] ?? 'false') === 'true') {
            $data['user_is_authenticated'] = isset($_SESSION['user']['id']);
            $data['debug_user_info'] = null;
            if ($data['user_is_authenticated']) {
                $data['debug_user_info'] = [
                    'name' => $_SESSION['user']['username'] ?? 'N/A',
                    'id' => $_SESSION['user']['id'],
                    'role_label' => $_SESSION['user']['role']['label'] ?? 'N/A',
                    'role_id' => $_SESSION['user']['role']['id'] ?? 'N/A',
                    'session_id' => session_id()
                ];
            }
            $timeoutValue = defined('TIMEOUT_SESSION') ? TIMEOUT_SESSION : 0;
            $durationInSeconds = 0;

            if (is_numeric($timeoutValue)) {
                $durationInSeconds = (int)$timeoutValue;
            } elseif (is_string($timeoutValue) && str_starts_with($timeoutValue, 'PT')) {
                $durationInSeconds = DurationHelper::iso8601ToSeconds($timeoutValue);
            }
            $data['js_data']['debug']['sessionTimeoutDuration'] = $durationInSeconds;
            $data['js_data']['debug']['sessionLastActivity'] = $_SESSION['user']['LAST_ACTIVITY'] ?? time();
        }

        $templateTpl = __DIR__ . '/../views/' . $view . '.tpl';
        try {
            if (file_exists($templateTpl)) {
                $content = $engine->render($templateTpl, $this->prepareLoopData($data));
            } else {
                http_response_code(404);
                $content = '';
            }
        } catch (Throwable $e) {
            $isDebug = (($_ENV['APP_DEBUG'] ?? 'false') === 'true');

            // Log détaillé
            error_log('[Template render] ' . $e->getMessage() . PHP_EOL . $e->getTraceAsString());

            // Statut HTTP
            http_response_code(500);

            // Message utilisateur (détaillé en dev, générique en prod)
            $this->flashMessageService->setFlashMessage(
                'danger',
                $isDebug
                    ? 'Erreur lors du rendu du template: ' . $e->getMessage()
                    : 'Une erreur est survenue lors de l\'affichage de la page.'
            );

            // Contenu de secours (affiche la stack en dev, vide en prod)
            $content = $isDebug
                ? '<pre class="alert alert-danger" style="white-space:pre-wrap;">'
                . htmlspecialchars((string)$e)
                . '</pre>'
                : '';
        }

        if ($partial) {
            echo $content;
        } else {
            $layoutData = array_merge($data, [
                'title' => $title,
                'content' => $content
            ]);
            echo $engine->render(__DIR__ . '/../views/layout/base.tpl', $layoutData);
        }
    }

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

    public function checkUserSession(bool $isPublicRoute = false): void
    {
        if ($isPublicRoute) {
            return;
        }

        if (!isset($_SESSION['user']['id']) || !isset($_SESSION['user']['LAST_ACTIVITY'])) {
            $this->logoutAndRedirect('Votre session est invalide ou a expiré. Veuillez vous reconnecter.');
            return;
        }

        if (!$this->sessionValidateService->isSessionActive($_SESSION['user'] ?? null, 'LAST_ACTIVITY', TIMEOUT_SESSION)) {
            $this->logoutAndRedirect('Votre session a expiré pour cause d\'inactivité. Veuillez vous reconnecter.', true);
            return;
        }

        $userRepository = new UserRepository();
        $user = $userRepository->findById((int)$_SESSION['user']['id']);

        if (!$user) {
            $this->logoutAndRedirect('Votre compte utilisateur est introuvable. Veuillez vous reconnecter.');
        }

        $this->currentUser = $user;

        if (!isset($_SESSION['user']['LAST_REGENERATION']) || (time() - $_SESSION['user']['LAST_REGENERATION'] > 1800)) {
            $this->regenerateSessionId(true);
            $_SESSION['user']['LAST_REGENERATION'] = time();
            $userRepository->addSessionId($user->getId(), session_id());
            $user->setSessionId(session_id());
        }

        $_SESSION['user']['LAST_ACTIVITY'] = time();

        if ($user->getSessionId() !== session_id()) {
            $this->logoutAndRedirect('Votre session n\'est plus valide (connexion depuis un autre appareil ?). Veuillez vous reconnecter.');
        }
    }

    private function logoutAndRedirect(string $message, bool $saveRedirectUrl = false): void
    {
        $redirectUrlAfterLogin = null;

        if ($saveRedirectUrl && isset($_SERVER['REQUEST_URI'])) {
            $redirectUrl = $_SERVER['REQUEST_URI'];
            if ($this->isValidInternalRedirect($redirectUrl)) {
                $redirectUrlAfterLogin = $redirectUrl;
            }
        }

        $_SESSION = [];
        session_destroy();
        session_start();
        $this->flashMessageService->setFlashMessage('warning', $message);

        if ($redirectUrlAfterLogin) {
            $_SESSION['redirect_after_login'] = $redirectUrlAfterLogin;
        }

        $this->redirect('/login');
    }

    protected function configureSession(): void
    {
        if (session_status() !== PHP_SESSION_NONE) {
            return;
        }

        $config = require __DIR__ . '/../../config/session.php';

        // Normalisation de l'env (.env peut contenir prod/dev/production/local)
        $envRaw = strtolower($_ENV['APP_ENV'] ?? 'local');
        $envKey = match ($envRaw) {
            'prod', 'production' => 'production',
            'dev', 'development', 'local' => 'local',
            default => 'local',
        };

        $sessionConfig = $config[$envKey] ?? $config['local'];

        // Durcissement
        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.cookie_secure', ($sessionConfig['cookie_secure'] ?? true) ? '1' : '0');
        ini_set('session.cookie_httponly', ($sessionConfig['cookie_httponly'] ?? true) ? '1' : '0');

        // __Host- exige: domain vide, path '/', secure=true
        session_name($sessionConfig['name'] ?? '__Host-SID');

        session_set_cookie_params([
            'lifetime' => $sessionConfig['cookie_lifetime'] ?? 0,
            'path' => $sessionConfig['cookie_path'] ?? '/',
            'domain' => $sessionConfig['cookie_domain'] ?? '',
            'secure' => $sessionConfig['cookie_secure'] ?? true,
            'httponly' => $sessionConfig['cookie_httponly'] ?? true,
            'samesite' => $sessionConfig['cookie_samesite'] ?? 'Strict',
        ]);
    }

    protected function regenerateSessionId(bool $destroyOldSession = false): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id($destroyOldSession);
        }
    }

    protected function json(array $data): void
    {
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }

    private function isValidInternalRedirect(string $url): bool
    {
        return str_starts_with($url, '/') && !str_starts_with($url, '//') && !str_contains($url, '://');
    }

    // -------- CSRF centralisé --------
    // Contexte par défaut : chemin de l'URL (stable entre GET du formulaire et POST de soumission)
    protected function getCsrfContext(): string
    {
        $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
        return $path ?: '/';
    }

    // Enforce pour toutes les méthodes non sûres
    private function maybeEnforceCsrf(): void
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        if (!in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            return;
        }

        $context = null;
        // Pour les requêtes POST/PUT etc., le contexte CSRF doit être celui de la page
        // qui a affiché le formulaire, que l'on retrouve via le Referer.
        if (isset($_SERVER['HTTP_REFERER'])) {
            $refererPath = parse_url($_SERVER['HTTP_REFERER'], PHP_URL_PATH);
            $refererHost = parse_url($_SERVER['HTTP_REFERER'], PHP_URL_HOST);
            $serverHost = $_SERVER['HTTP_HOST'] ?? '';

            // On vérifie que le Referer vient bien de notre propre site pour la sécurité.
            if ($refererPath && $refererHost === $serverHost) {
                $context = $refererPath;
            }
        }

        $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($_POST['csrf_token'] ?? '');

        if ($context === null || !$this->csrfService->validateAndConsume($token, $context)) {
            $this->flashMessageService->setFlashMessage('danger', 'Token CSRF invalide ou manquant.');
            header('Location: ' . ($context ?: '/'));
            exit;
        }
    }

    /**
     * Vérifie si l'utilisateur courant a les droits de faire ce qui est demandé.
     * Redirige si false sinon retourne true
     * @param int $minRoleLevel
     * @param string|null $urlReturn
     * @return void
     */
    protected function checkIfCurrentUserIsAllowedToManagedThis(int $minRoleLevel = 99, ?string $urlReturn = null): void
    {
        if ($urlReturn !== null && !preg_match('/^[a-z-]*$/', $urlReturn)) {
            http_response_code(404);
            exit;
        }

        if (!$this->currentUser || $this->currentUser->getRole()->getLevel() > $minRoleLevel) {
            $this->flashMessageService->setFlashMessage('danger', "Accès refusé");
            if ($urlReturn !== null) {
                $urlReturn = '/' . $urlReturn;
            }
            $this->redirect('/gestion' . $urlReturn);
        }
    }

    protected function redirect(string $url): void
    {
        header('Location: ' . $url);
        exit;
    }

    /**
     * Redirige vers une URL en ajoutant une ancre si elle est présente dans POST.
     * @param string $url
     * @param string $anchorKey La clé POST contenant l'ancre.
     * @param int $id
     * @param string|null $context
     */
    protected function redirectWithAnchor(string $url, string $anchorKey = 'form_anchor', int $id = 0, ?string $context = null): void
    {
        $anchor = '';
        if ($id != 0) {
            // Déduire le préfixe selon le contexte
            $prefix = $context === 'desktop' ? 'config-row-' : 'config-card-';
            $anchor = $prefix . $id;
        } elseif (!empty($_POST[$anchorKey])) {
            $anchor = $_POST[$anchorKey];
        }
        if ($anchor) {
            $url .= '#' . $anchor;
        }
        $this->redirect($url);
    }
}
