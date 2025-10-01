<?php
namespace app\Controllers;

use app\Core\TemplateEngine;
use app\Enums\LogType;
use app\Models\User\User;
use app\Repository\User\UserRepository;
use app\Services\FlashMessageService;
use app\Services\Log\Logger;
use app\Services\Log\LoggingBootstrap;
use app\Services\Log\RequestContext;
use app\Services\Reservation\ReservationSessionService;
use app\Services\Security\SessionValidateService;
use app\Services\Security\CsrfService;
use app\Utils\DurationHelper;
use Throwable;

abstract class AbstractController
{
    protected FlashMessageService $flashMessageService;
    private SessionValidateService $sessionValidateService;
    protected CsrfService $csrfService;
    protected ReservationSessionService $reservationSessionService;
    private static bool $globalHandlersRegistered = false;

    protected ?User $currentUser = null;

    public function __construct(bool $isPublicRoute = false)
    {
        // Bootstrap logging + contexte requête (X-Request-Id)
        LoggingBootstrap::ensureInitialized();
        self::registerGlobalErrorHandlers();

        // Configuration et démarrage de la session
        $this->configureSession();
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        RequestContext::boot();

        // Validation de la session utilisateur (y compris la régénération)
        $this->sessionValidateService = new SessionValidateService();
        // On initialise le service de messages flash ici, car il peut être appelé
        // par logoutAndRedirect() à l'intérieur de checkUserSession().
        $this->flashMessageService = new FlashMessageService();

        // On initialise le service de session de réservation ici pour qu'il soit toujours disponible.
        $this->reservationSessionService = new ReservationSessionService();

        try {
            $this->checkUserSession($isPublicRoute);
        } catch (Throwable) {
            $this->logoutAndRedirect('Une erreur de session est survenue. Veuillez vous reconnecter.');
        }

        // Initialisation des services qui dépendent d'une session stable
        $this->csrfService = new CsrfService();

        // Validation du token CSRF pour les requêtes POST, PUT, etc.
        // Doit se faire après l'initialisation de CsrfService.
        $this->maybeEnforceCsrf();

        // Journaliser la requête dans le channel "url" (maintenant que l'utilisateur est connu)
        Logger::get()->info(
            LogType::URL->value,
            'request',
            [
                'uri' => strtok($_SERVER['REQUEST_URI'] ?? '/', '?'),
                'method' => $_SERVER['REQUEST_METHOD'] ?? 'GET',
            ]
        );
    }

    /**
     * @param string $view
     * @param array $data
     * @param string $title
     * @param bool $partial
     * @return void
     */
    protected function render(string $view, array $data = [], string $title = '', bool $partial = false): void
    {
        $engine = new TemplateEngine();

        $uri = strtok($_SERVER['REQUEST_URI'], '?');
        $data['uri'] = $uri;
        $data['is_gestion_page'] = str_starts_with($uri, '/gestion');
        $data['load_ckeditor'] = $data['is_gestion_page'] && (str_starts_with($uri, '/gestion/mail_templates') || str_starts_with($uri, '/gestion/accueil'));

        // Injecter le token pour la page COURANTE (jamais le Referer)
        $data['csrf_token'] ??= $this->csrfService->getToken($this->getCurrentPath());

        // Centralisation de la récupération des messages flash
        $data['flash_message'] = $this->flashMessageService->getFlashMessage();
        $this->flashMessageService->unsetFlashMessage();

        if (($_ENV['APP_DEBUG'] ?? 'false') === 'true') {
            $data['user_is_authenticated'] = isset($_SESSION['user']['id']);
            $data['debug_user_info'] = null;

            // Assurer que la clé debug existe pour le JS
            $data['js_data']['debug'] ??= [];

            if ($data['user_is_authenticated']) {
                $data['debug_user_info'] = [
                    'name' => $_SESSION['user']['username'] ?? 'N/A',
                    'id' => $_SESSION['user']['id'],
                    'role_label' => $_SESSION['user']['role']['label'] ?? 'N/A',
                    'role_id' => $_SESSION['user']['role']['id'] ?? 'N/A',
                    'session_id' => session_id()
                ];

                // Données pour le timeout de la session utilisateur
                $timeoutValue = defined('TIMEOUT_SESSION') ? TIMEOUT_SESSION : 0;
                $durationInSeconds = is_numeric($timeoutValue) ? (int)$timeoutValue : DurationHelper::iso8601ToSeconds($timeoutValue);

                $data['js_data']['debug']['sessionTimeoutDuration'] = $durationInSeconds;
                $data['js_data']['debug']['sessionLastActivity'] = $_SESSION['user']['LAST_ACTIVITY'] ?? time();
                $data['js_data']['debug']['isUserSession'] = true;

            } else {
                // Si pas d'utilisateur connecté, on vérifie s'il y a une session de réservation
                $reservationSession = $this->reservationSessionService->getReservationSession();
                if ($reservationSession && !empty($reservationSession['event_id'])) {
                    $data['reservation_session_active'] = true;
                    $data['js_data']['debug']['sessionTimeoutDuration'] = $this->reservationSessionService->getReservationTimeoutDuration();
                    $data['js_data']['debug']['sessionLastActivity'] = $reservationSession['last_activity'] ?? time();
                    $data['js_data']['debug']['isUserSession'] = false;
                }
            }
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

            // Log détaillé via logger applicatif
            Logger::get()->error(
                LogType::APPLICATION->value,
                'template_render',
                ['exception' => $e, 'view' => $view, 'uri' => $data['uri'] ?? null]
            );

            http_response_code(500);

            $this->flashMessageService->setFlashMessage(
                'danger',
                $isDebug
                    ? 'Erreur lors du rendu du template: ' . $e->getMessage()
                    : 'Une erreur est survenue lors de l\'affichage de la page.'
            );

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

    /**
     * @param array $data
     * @return array
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
     * @param bool $isPublicRoute
     * @return void
     * @throws \Exception
     */
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

    /**
     * @param string $message
     * @param bool $saveRedirectUrl
     * @return void
     */
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

    /**
     * @return void
     */
    protected function configureSession(): void
    {
        if (session_status() !== PHP_SESSION_NONE) {
            return;
        }

        $config = require __DIR__ . '/../../config/session.php';

        $envRaw = strtolower($_ENV['APP_ENV'] ?? 'local');
        $envKey = match ($envRaw) {
            'prod', 'production' => 'production',
            'dev', 'development', 'local' => 'local',
            default => 'local',
        };

        $sessionConfig = $config[$envKey] ?? $config['local'];

        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.cookie_secure', ($sessionConfig['cookie_secure'] ?? true) ? '1' : '0');
        ini_set('session.cookie_httponly', ($sessionConfig['cookie_httponly'] ?? true) ? '1' : '0');

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

    /**
     * @param bool $destroyOldSession
     * @return void
     */
    protected function regenerateSessionId(bool $destroyOldSession = false): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id($destroyOldSession);
        }
    }

    /**
     * @param array $data
     * @param int $statusCode
     * @return void
     */
    protected function json(array $data, int $statusCode = 200): void
    {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');

        // Toujours joindre un nouveau token pour le contexte courant (ou Referer pour POST/PUT/…)
        try {
            $context = $this->getCsrfContext();
            // On s'assure que le token est bien (re)généré pour la réponse
            // C'est crucial pour que le client récupère le nouveau token après une requête POST/PUT...
            // qui a consommé l'ancien.
            $this->csrfService->generateToken($context);
            $data['csrf_token'] ??= $this->csrfService->getToken($context);
        } catch (Throwable) {
            // On ignore en cas d'absence de service (ex. pendant des tests).
        }

        echo json_encode($data);
        exit;
    }

    /**
     * @param string $url
     * @return bool
     */
    private function isValidInternalRedirect(string $url): bool
    {
        return str_starts_with($url, '/') && !str_starts_with($url, '//') && !str_contains($url, '://');
    }

    // Contexte CSRF: Referer seulement pour les requêtes non-GET

    /**
     * @return string
     */
    protected function getCsrfContext(): string
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

        // Pour les requêtes qui modifient des données (POST, etc.), le contexte
        // est la page d'où vient la requête (le Referer).
        // Cela permet à un token généré sur /reservation d'être valide pour un appel
        // API vers /reservation/etape1.
        if (in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            // On utilise le Referer s'il est de la même origine, sinon on se rabat sur le path courant.
            return $this->getSameOriginRefererPath() ?? $this->getCurrentPath();
        }

        // Pour les requêtes GET (affichage de page), le contexte est la page elle-même.
        // C'est ce qui génère le token pour le formulaire/la page.
        return $this->getCurrentPath();
    }

    // Chemin de la page courante (sans query)

    /**
     * @return string
     */
    protected function getCurrentPath(): string
    {
        return parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
    }

    // Referer si même origine (hôte comparé en ignorant le port)

    /**
     * @return string|null
     */
    private function getSameOriginRefererPath(): ?string
    {
        if (empty($_SERVER['HTTP_REFERER'])) {
            return null;
        }

        $refererHost = parse_url($_SERVER['HTTP_REFERER'], PHP_URL_HOST);
        $serverHostRaw = $_SERVER['HTTP_X_FORWARDED_HOST'] ?? ($_SERVER['HTTP_HOST'] ?? '');
        $serverHost = parse_url('https://' . $serverHostRaw, PHP_URL_HOST); // normalise et ignore le port

        if ($refererHost && $serverHost && strcasecmp($refererHost, $serverHost) === 0) {
            $path = parse_url($_SERVER['HTTP_REFERER'], PHP_URL_PATH);
            return $path ?: null;
        }

        return null;
    }

    /**
     * @return void
     */
    private function maybeEnforceCsrf(): void
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        if (!in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            return;
        }

        $context = $this->getCsrfContext();
        $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($_POST['csrf_token'] ?? '');

        if (!$this->csrfService->validateAndConsume($token, $context)) {
            $this->flashMessageService->setFlashMessage('danger', 'Token CSRF invalide ou manquant.');
            // Redirige vers le referer si c'est une origine sûre, sinon vers la page d'accueil.
            $redirectTo = $this->getSameOriginRefererPath() ?? '/';
            header('Location: ' . $redirectTo);
            exit;
        }

    }

    /**
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

    /**
     * @param string $url
     * @return void
     */
    protected function redirect(string $url): void
    {
        header('Location: ' . $url);
        exit;
    }

    /**
     * @param string $url
     * @param string $anchorKey
     * @param int $id
     * @param string|null $context
     * @return void
     */
    protected function redirectWithAnchor(string $url, string $anchorKey = 'form_anchor', int $id = 0, ?string $context = null): void
    {
        $anchor = '';
        if ($id != 0) {
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

    /**
     * @return void
     */
    private static function registerGlobalErrorHandlers(): void
    {
        if (self::$globalHandlersRegistered) {
            return;
        }

        // Exceptions non interceptées
        set_exception_handler([self::class, 'handleUncaughtException']);

        // Erreurs PHP (warnings, notices, etc.)
        set_error_handler([self::class, 'handlePhpError']);

        // Erreurs fatales en fin de script
        register_shutdown_function([self::class, 'handleShutdown']);

        self::$globalHandlersRegistered = true;
    }

    /**
     * @param Throwable $e
     * @return void
     */
    public static function handleUncaughtException(Throwable $e): void
    {
        // Toujours initialiser le logger si besoin
        LoggingBootstrap::ensureInitialized();

        Logger::get()->error(
            LogType::APPLICATION->value,
            'uncaught_exception',
            ['exception' => $e]
        );

        self::respondHttp500($e);
    }

    /**
     * @param int $errno
     * @param string $errstr
     * @param string $errfile
     * @param int $errline
     * @return bool
     */
    public static function handlePhpError(int $errno, string $errstr, string $errfile, int $errline): bool
    {
        LoggingBootstrap::ensureInitialized();

        $level = match ($errno) {
            E_USER_ERROR, E_RECOVERABLE_ERROR => 'ERROR',
            E_WARNING, E_USER_WARNING => 'WARNING',
            E_NOTICE, E_USER_NOTICE, E_DEPRECATED, E_USER_DEPRECATED => 'NOTICE',
            default => 'INFO',
        };

        Logger::get()->log(
            $level,
            LogType::APPLICATION->value,
            'php_error',
            ['errno' => $errno, 'message' => $errstr, 'file' => $errfile . ':' . $errline]
        );

        // Laisser le handler natif continuer (retourner false)
        return false;
    }

    /**
     * @return void
     */
    public static function handleShutdown(): void
    {
        $err = error_get_last();
        if (!$err) {
            return;
        }

        $fatalTypes = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR];
        if (in_array($err['type'] ?? 0, $fatalTypes, true)) {
            LoggingBootstrap::ensureInitialized();

            Logger::get()->critical(
                LogType::APPLICATION->value,
                'fatal_error',
                ['type' => $err['type'], 'message' => $err['message'], 'file' => $err['file'] . ':' . $err['line']]
            );

            // Éviter tout double envoi si des headers sont déjà partis
            if (!headers_sent()) {
                self::respondHttp500('Fatal error');
            }
        }
    }

    /**
     * @param Throwable|string|null $e
     * @return void
     */
    private static function respondHttp500(null|Throwable|string $e): void
    {
        // Évite tout output parasite si on est déjà en train de sortir quelque chose
        http_response_code(500);

        $isDebug = (($_ENV['APP_DEBUG'] ?? 'false') === 'true');
        $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
        $isJson = str_contains($accept, 'application/json') || (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest');

        if ($isJson) {
            header('Content-Type: application/json');
            echo json_encode([
                'error' => 'Internal Server Error',
                'request_id' => RequestContext::getRequestId(),
                'details' => $isDebug ? (string)($e instanceof Throwable ? $e : ($e ?? '')) : null,
            ]);
        } else {
            $msg = $isDebug
                ? '<pre class="alert alert-danger" style="white-space:pre-wrap;">' . htmlspecialchars((string)($e instanceof Throwable ? $e : ($e ?? '')), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</pre>'
                : 'Une erreur interne est survenue.';
            header('Content-Type: text/html; charset=UTF-8');
            echo $msg;
        }
        exit;
    }

}
