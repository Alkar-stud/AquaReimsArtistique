<?php
namespace app\Controllers;

use app\Attributes\Route;
use app\Repository\UserRepository;
use DateMalformedStringException;

#[Route('/login', name: 'app_login')]
class LoginController extends AbstractController
{
    public function __construct()
    {
        parent::__construct(true); // true = route publique, pas de vérif session pour éviter le TOO_MANY_REDIRECT
    }

    public function index(): void
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->login();
        } else {
            $this->render('login', [], 'Connexion');
        }
    }

    /**
     * @throws DateMalformedStringException
     */
    private function login(): void
    {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';

        if (empty($username) || empty($password)) {
            $this->logService->logAccess('LOGIN_ATTEMPT_EMPTY_FIELDS', ['username' => $username]);
            $_SESSION['flash_message'] = ['type' => 'danger', 'message' => 'Veuillez remplir tous les champs.'];
            header('Location: /login');
            exit; // MANQUAIT - tout le code après n'était jamais exécuté
        }

        $userRepository = new UserRepository();
        $user = $userRepository->findByUsername($username);

        if ($user && password_verify($password, $user->getPassword())) {
            if (password_needs_rehash($user->getPassword(), PASSWORD_DEFAULT, ['cost' => (int)$_ENV['BCRYPT_ROUNDS']])) {
                $newHash = password_hash($password, PASSWORD_DEFAULT, ['cost' => (int)$_ENV['BCRYPT_ROUNDS']]);
                $userRepository->updatePassword($user->getId(), $newHash);
            }

            unset($_SESSION['flash_message']);

            // Régénérer l'ID de session après connexion réussie
            session_regenerate_id(true);

            // On stocke les informations de l'utilisateur en session
            $_SESSION['user'] = [
                'id' => $user->getId(),
                'username' => $user->getUsername(),
                'email' => $user->getEmail(),
                'role' => [
                    'id' => $user->getRole()->getId(),
                    'name' => $user->getRole()->getLibelle(),
                    'level' => $user->getRole()->getLevel()
                ],
                'LAST_ACTIVITY' => time(),
                'LAST_REGENERATION' => time()
            ];

            // Une seule fois l'ajout de session
            $userRepository->addSessionId($user->getId(), session_id());

            $this->logService->logAccess('LOGIN_SUCCESS', [
                'user_id' => $user->getId(),
                'username' => $user->getUsername(),
                'session_regenerated' => true
            ]);

            header('Location: /');
            exit;
        }

        $this->logService->logAccess('LOGIN_FAILED', [
            'username' => $username,
            'user_exists' => $user !== null
        ]);

        $_SESSION['flash_message'] = ['type' => 'danger', 'message' => 'Identifiants incorrects.'];
        header('Location: /login');
        exit;
    }

    #[Route('/logout', name: 'app_logout')]
    public function logout(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        // Si l'utilisateur avait un token de session, on le supprime
        if (isset($_SESSION['user']['id'])) {
            $userRepository = new UserRepository();
            $userRepository->removeSessionId($_SESSION['user']['id'], session_id());
            $this->logService->logAccess('LOGOUT', [
                'user_id' => $_SESSION['user']['id'] ?? null,
                'session_destroyed' => true
            ]);
        }
        $_SESSION = [];
        session_destroy();

        header('Location: /');
        exit;
    }
}