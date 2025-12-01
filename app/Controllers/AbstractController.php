<?php

namespace app\Controllers;

use app\Core\TemplateEngine;
use app\Enums\LogType;
use app\Models\User\User;
use app\Repository\User\UserRepository;
use app\Services\FlashMessageService;
use app\Services\Log\Handler\ErrorHandler;
use app\Services\Log\Logger;
use app\Services\Log\LoggingBootstrap;
use app\Services\Log\RequestContext;
use app\Services\Reservation\ReservationSessionService;
use app\Services\Security\SessionValidateService;
use app\Services\Security\CsrfService;
use app\Services\Security\AuthorizationService;
use app\Utils\DurationHelper;
use Exception;
use Throwable;

abstract class AbstractController
{
    protected FlashMessageService $flashMessageService;
    private SessionValidateService $sessionValidateService;
    protected ?CsrfService $csrfService = null;
    protected ReservationSessionService $reservationSessionService;
    protected ?User $currentUser = null;
    private array $securityConfig;
    protected AuthorizationService $authorizationService;

    /**
     * Initialise l'environnement contrôleur.
     * - Démarre/Configure la session.
     * - Initialise le contexte de requête et le logging.
     * - Enregistre les gestionnaires globaux d'erreurs.
     * - Valide la session utilisateur (sauf route publique).
     * - Initialise le CSRF et applique la vérification conditionnelle.
     * - Journalise la requête entrante.
     *
     * Effets de bord:
     * - Peut rediriger vers `/login` et terminer le script en cas de session invalide.
     * - Peut envoyer des en-têtes HTTP.
     *
     * @param bool $isPublicRoute Indique si la route est publique (pas de vérification de session utilisateur).
     */
    public function __construct(bool $isPublicRoute = false)
    {
        // Bootstrap logging + contexte requête (X-Request-Id)
        LoggingBootstrap::ensureInitialized();
        ErrorHandler::register(($_ENV['APP_DEBUG'] ?? 'false') === 'true');

        // Configuration et démarrage de la session
        $this->configureSession();
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        RequestContext::boot();

        // Charge la config de sécurité
        $this->securityConfig = require __DIR__ . '/../../config/security.php';

        // Initialisation des services
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

        $this->authorizationService = new AuthorizationService();

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
     * Rend une vue avec un layout commun.
     * - Injecte des données communes (URI, messages flash, utilisateur, debug, CSRF).
     * - Peut rendre partiellement le contenu sans layout.
     *
     * Effets de bord:
     * - Définit le code de statut HTTP.
     * - Écrit la sortie HTML directement (echo).
     * - Peut définir des en-têtes CSRF dans le layout.
     *
     * @param string $view Nom de la vue relative au répertoire `views` (sans extension).
     * @param array $data Données à passer au template.
     * @param string $title Titre de la page.
     * @param int $statusCode Code HTTP à renvoyer.
     * @param string $csrfContext Contexte CSRF à utiliser.
     * @param bool $partial Si true, renvoie uniquement le contenu de la vue (sans layout).
     * @return void
     */
    protected function render(string $view, array $data = [], string $title = '', int $statusCode = 200, string $csrfContext = 'default', bool $partial = false): void
    {
        http_response_code($statusCode);

        $engine = new TemplateEngine();

        $uri = strtok($_SERVER['REQUEST_URI'], '?');
        $data['uri'] = $uri;
        $data['is_gestion_page'] = str_starts_with($uri, '/gestion');
        //$data['load_ckeditor'] = $data['is_gestion_page'] && (str_starts_with($uri, '/gestion/mails_templates') || str_starts_with($uri, '/gestion/accueil'));
        // Charge CKEditor sur /gestion/mails_templates[/...] et /gestion/accueil[/...]
        $data['load_ckeditor'] = $data['is_gestion_page']
            && (bool)preg_match('#^/gestion/(mails_templates|accueil)(/|$)#', $uri);

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
                $reservationSession = $this->reservationSessionService->getReservationTempSession();
                if ($reservationSession && !empty($reservationSession['reservation']->getEvent())) {

                    $data['reservation_session_active'] = true;
                    $data['js_data']['debug']['sessionTimeoutDuration'] = $this->reservationSessionService->getReservationTimeoutDuration();
                    $data['js_data']['debug']['sessionLastActivity'] = $reservationSession['reservation']->getCreatedAt()->getTimestamp() ?? time();
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
                ['exception' => $e, 'view' => $view, 'uri' => $data['uri']]
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

        // Pour les pages de réservation, forcer le contexte unifié
        if (str_starts_with($view, 'reservation/')) {
            $csrfContext = '/reservation';
        } else {
            $csrfContext = $this->getCurrentPath();
        }

        if ($partial) {
            echo $content;
        } else {
            $layoutData = array_merge($data, [
                'title' => $title,
                'content' => $content,
                'csrf_token' => $this->csrfService->getToken($csrfContext),
                'csrf_context' => $csrfContext
            ]);
            echo $engine->render(__DIR__ . '/../views/layout/base.tpl', $layoutData);
        }
    }

    /**
     * Pour le moteur de template, enrichit les tableaux pour faciliter les boucles dans les vues
     * Prépare des structures de données adaptées aux boucles de templates.
     * - Pour chaque tableau indexé \[0..n\], ajoute `key_loop` avec des entrées \{item, index, first, last, count\}.
     *
     * @param array $data Données d'entrée.
     * @return array Données enrichies de clés `*_loop`.
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
     * Vérifie l'état de la session utilisateur pour les routes non publiques.
     * - Valide l'activité et la péremption.
     * - Régénère l'ID de session périodiquement et le persiste en base.
     * - Recharge l'utilisateur courant.
     *
     * Effets de bord:
     * - Met à jour `$_SESSION['user']`.
     * - Peut rediriger vers `/login` et terminer le script.
     *
     * @param bool $isPublicRoute Si true, aucune vérification n'est effectuée.
     * @return void
     * @throws Exception En cas d'erreur interne lors d'opérations dépendantes (ex. dépôt).
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
            $this->logoutAndRedirect('Votre session a expiré pour cause d\'inactivité. Veuillez vous reconnecter.');
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
     * Supprime la session actuelle, enregistre un message flash et redirige vers `/login`.
     * Optionnellement, mémorise l'URL courante pour y revenir après connexion.
     *
     * Effets de bord:
     * - Vide `$_SESSION`, détruit et redémarre la session.
     * - Définit un message flash.
     * - Envoie un en-tête `Location` et termine le script.
     *
     * @param string $message Message à afficher après redirection.
     * @return void
     */
    private function logoutAndRedirect(string $message): void
    {
        $redirectUrlAfterLogin = null;

        if (isset($_SERVER['REQUEST_URI'])) {
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
     * Applique les paramètres de sécurité et de cookie de la session selon l'environnement.
     *
     * Effets de bord:
     * - Modifie les directives INI des sessions.
     * - Définit le nom de session et les paramètres de cookie.
     *
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
     * Régénère l'ID de session s'il y a une session active.
     *
     * @param bool $destroyOldSession Si true, détruit l'ancienne session.
     * @return void
     */
    protected function regenerateSessionId(bool $destroyOldSession = false): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id($destroyOldSession);
        }
    }

    /**
     * Envoie une réponse JSON standardisée avec renouvellement du jeton CSRF.
     * - Détecte automatiquement le contexte CSRF (entête `X-CSRF-Context` ou URI).
     * - Ajoute des en-têtes `X-CSRF-Token` et `X-CSRF-Context`.
     *
     * Effets de bord:
     * - Définit le code de statut HTTP et les en-têtes.
     * - Écrit la réponse JSON et termine le script.
     *
     * @param array $data Données à sérialiser en JSON.
     * @param int $statusCode Code HTTP de la réponse.
     * @param string|null $csrfContext Contexte CSRF à utiliser, ou null pour auto.
     * @return void
     */
    protected function json(array $data, int $statusCode = 200, ?string $csrfContext = null): void
    {
        // Détection automatique du contexte CSRF depuis les en-têtes HTTP
        if ($csrfContext === null) {
            $csrfContext = $_SERVER['HTTP_X_CSRF_CONTEXT'] ?? $this->getCsrfContext();
        }

        $newToken = $this->csrfService->getToken($csrfContext);

        // Ajouter des informations de débogage en environnement de développement
        if (($_ENV['APP_DEBUG'] ?? 'false') === 'true') {
            $data['_debug'] = [
                'csrf_context' => $csrfContext,
                'http_x_csrf_context' => $_SERVER['HTTP_X_CSRF_CONTEXT'] ?? null,
                'referer_path' => $this->getSameOriginRefererPath(),
                'current_path' => $this->getCurrentPath(),
            ];
        }

        http_response_code($statusCode);
        header('Content-Type: application/json; charset=UTF-8');
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');
        header('X-CSRF-Token: ' . $newToken);
        header('X-CSRF-Context: ' . $csrfContext);

        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    /**
     * Indique si une URL est une redirection interne sûre.
     * - Doit commencer par `/` et ne pas contenir de double slash initial ni de schéma.
     *
     * @param string $url URL candidate.
     * @return bool True si l'URL est interne.
     */
    private function isValidInternalRedirect(string $url): bool
    {
        return str_starts_with($url, '/') && !str_starts_with($url, '//') && !str_contains($url, '://');
    }

    /**
     * Détermine le contexte CSRF à partir de l'URL courante.
     * - Les routes `/reservation` et leurs sous-chemins utilisent le contexte `/reservation`.
     * - Sinon, utilise le premier segment de chemin (`/segment`) ou `default`.
     *
     * @return string Contexte CSRF.
     */
    protected function getCsrfContext(): string
    {
        $uri = $_SERVER['REQUEST_URI'] ?? '';

        // Pour toutes les routes de réservation (avec ou sans slash final)
        if ($uri === '/reservation' || str_starts_with($uri, '/reservation/')) {
            return '/reservation';
        }

        // Pour les autres routes
        $path = parse_url($uri, PHP_URL_PATH) ?: '/';
        $segments = explode('/', trim($path, '/'));
        return !empty($segments[0]) ? '/' . $segments[0] : 'default';
    }


    /**
     * Chemin de la page courante (sans query)
     *
     * @return string
     */
    protected function getCurrentPath(): string
    {
        return parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
    }

    // Referer si même origine (hôte comparé en ignorant le port)

    /**
     * Retourne le chemin de la requête courante sans query string.
     *
     * @return string|null Chemin absolu commençant par `/`.
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
     * Accès au service CSRF initialisé.
     *
     * @return CsrfService Instance du service CSRF.
     */
    protected function csrf(): CsrfService
    {
        return $this->csrfService ??= new CsrfService();
    }

    /**
     * Applique la validation/consommation CSRF pour les méthodes matrices \[POST, PUT, PATCH, DELETE\].
     * - En mode debug, la validation est désactivée.
     * - En cas d'échec, renvoie HTTP 419 avec un nouveau jeton et termine le script.
     *
     * Effets de bord:
     * - Peut envoyer une réponse JSON et terminer l'exécution.
     *
     * @return void
     */
    private function maybeEnforceCsrf(): void
    {
        // En debug, on ne valide/consomme pas le jeton CSRF
        if (($_ENV['APP_DEBUG'] ?? 'false') === 'true') {
            return;
        }

        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        // Si c'est une requête qui modifie l'état (POST, PUT, PATCH, DELETE)
        if (in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            $csrfToken = null;
            $csrfContext = null;

            // Recherche le token dans l'entête
            if (isset($_SERVER['HTTP_X_CSRF_TOKEN'])) {
                $csrfToken = $_SERVER['HTTP_X_CSRF_TOKEN'];
            }
            // Recherche le contexte du token dans l'entête
            if (isset($_SERVER['HTTP_X_CSRF_CONTEXT'])) {
                $csrfContext = $_SERVER['HTTP_X_CSRF_CONTEXT'];
            }

            // Si CSRF échoue -> redirection avec message d'erreur
            if ($csrfToken && !$this->csrfService->validateAndConsume($csrfToken, $csrfContext)) {
                http_response_code(419);
                header('Content-Type: application/json; charset=UTF-8');

                // Générer un nouveau token pour remplacer celui qui vient d'échouer
                $newToken = $this->csrfService->getToken($csrfContext);
                header('X-CSRF-Token: ' . $newToken);
                header('X-CSRF-Context: ' . $csrfContext);

                echo json_encode([
                    'success' => false,
                    'error' => 'Jeton de sécurité invalide ou expiré.',
                    'csrf_token' => $newToken
                ]);
                exit;
            }
        }
    }

    /**
     * Vérifie que l'utilisateur courant a un niveau de rôle suffisant pour gérer la ressource.
     * - Un niveau numériquement plus petit signifie plus de privilèges.
     *
     * Effets de bord:
     * - Peut définir un message flash et rediriger vers `/gestion` (avec option de retour).
     *
     * @param int $minRoleLevel Niveau de rôle minimal requis.
     * @param string|null $urlReturn Segment d'URL \[a-z_-]* pour retourner dans l'UI, ou null.
     * @return void
     */
    protected function checkIfCurrentUserIsAllowedToManagedThis(int $minRoleLevel = 99, ?string $urlReturn = null): void
    {
        if ($urlReturn !== null && !preg_match('/^[a-z_-]*$/', $urlReturn)) {
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
     * Redirige vers l'URL donnée avec un en-tête `Location` puis termine le script.
     *
     * @param string $url URL absolue ou relative au site.
     * @return void
     */
    protected function redirect(string $url): void
    {
        header('Location: ' . $url);
        exit;
    }

    /**
     * Construit et applique une redirection vers une URL, avec ancre optionnelle.
     * - L'ancre peut être dérivée d'un identifiant ou du champ POST `form_anchor` (par défaut).
     *
     * @param string $url URL de base.
     * @param string $anchorKey Nom du champ POST contenant l'ancre si `$id` n'est pas fourni.
     * @param int $id Identifiant d'élément \=> construit une ancre basée sur le contexte.
     * @param string|null $context Contexte d'affichage (`desktop` \=> `config-row-`, sinon `config-card-`).
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
     * Vérifie qu'une permission donnée est incluse dans les droits de l'utilisateur courant.
     * - En cas d'absence, renvoie une réponse JSON 403 via `json()` et termine le script.
     *
     * @param string $required
     * @param string $featureKey
     * @return void
     */
    protected function checkUserPermission(string $required = '', string $featureKey = 'reservations_access_level'): void
    {
        // Si l'utilisateur n'est pas chargé, on protège par défaut
        if (!$this->currentUser) {
            $this->json(['success' => false, 'message' => 'Accès refusé.'], 403);
        }

        if (!$this->authorizationService->hasPermission($this->currentUser, $required, $featureKey)) {
            $this->json(['success' => false, 'message' => 'Accès refusé. Vous n\'avez pas les droits nécessaires.'], 403);
        }
    }

}
