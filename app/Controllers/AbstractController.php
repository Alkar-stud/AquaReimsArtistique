<?php

namespace app\Controllers;
use app\Repository\UserRepository;

abstract class AbstractController
{
    public function __construct(bool $isPublicRoute = false)
    {
        $this->checkUserSession($isPublicRoute);
    }

    protected function render(string $view, array $data = [], string $title = ''): void
    {
        extract($data);
        ob_start();

        $page = __DIR__ . '/../views/' . $view . '.html.php';

        // On vérifie que le fichier de vue existe avant de l'inclure
        if (!file_exists($page)) {
            ob_end_clean(); // Nettoie le buffer en cas d'erreur
            // Lancer une exception au lieu de trigger_error
            throw new \RuntimeException("La vue '$page' n'existe pas.");
        }

        include $page;
        $content = ob_get_clean();
        require __DIR__ . '/../views/base.html.php';
    }

    public function checkUserSession(bool $isPublicRoute = false): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        // Si la route est publique, on ne vérifie pas la session
        if ($isPublicRoute) {
            return;
        }
        //Si n'existe pas, c'est peut-être que pas encore connecté
        if (!isset($_SESSION['user']) && !$isPublicRoute) {
            header('Location: /login');
            exit;
        }

        $timeout = 1800; // 30 minutes ==> à récupérer en BDD config quand sera implémenté

        if (!isset($_SESSION['user']['id'])) {
            $flash = [
                'type' => 'warning',
                'message' => 'Votre session a expiré ou vous devez vous reconnecter.'
            ];
            $_SESSION = [];
            session_destroy();
            session_start();
            $_SESSION['flash_message'] = $flash;
            if (!$isPublicRoute) {
                header('Location: /login');
                exit;
            }
            return;
        }

        if (isset($_SESSION['user']['LAST_ACTIVITY']) && (time() - $_SESSION['user']['LAST_ACTIVITY'] > $timeout)) {
            $flash = [
                'type' => 'warning',
                'message' => 'Votre session a expiré pour cause d\'inactivité. Veuillez vous reconnecter.'
            ];
            $_SESSION = [];
            session_destroy();
            session_start();
            $_SESSION['flash_message'] = $flash;
            if (!$isPublicRoute) {
                header('Location: /login');
                exit;
            }
            return;
        }
        $_SESSION['user']['LAST_ACTIVITY'] = time();

        $userRepository = new UserRepository();
        $user = $userRepository->findById($_SESSION['user']['id']);

        if (!$user || $user->getSessionId() !== session_id()) {
            $flash = [
                'type' => 'warning',
                'message' => 'Votre session n\'est plus valide. Veuillez vous reconnecter.'
            ];
            $_SESSION = [];
            session_destroy();
            session_start();
            $_SESSION['flash_message'] = $flash;
            if (!$isPublicRoute) {
                header('Location: /login');
                exit;
            }
            return;
        }
    }

}