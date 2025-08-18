<?php
namespace app\Controllers;

use app\Attributes\Route;
use app\Repository\UserRepository;
use DateMalformedStringException;
use app\Enums\LogType;
use Random\RandomException;

#[Route('/login', name: 'app_login')]
class LoginController extends AbstractController
{
    public function __construct()
    {
        parent::__construct(true); // true = route publique, pas de vérif session pour éviter le TOO_MANY_REDIRECT
    }

    /**
     * @throws DateMalformedStringException
     * @throws RandomException
     */
    public function index(): void
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->login();
        } else {
            // Générer un token CSRF pour le formulaire
            if (!isset($_SESSION['csrf_token'])) {
                $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            }

            $this->render('login', [
                'csrf_token' => $_SESSION['csrf_token']
            ], 'Connexion');
        }
    }

    /**
     * @throws DateMalformedStringException
     */
    private function login(): void
    {
        // Vérifier le token CSRF avec protection contre null
        $submittedToken = $_POST['csrf_token'] ?? '';
        $sessionToken = $_SESSION['csrf_token'] ?? '';

        if (empty($submittedToken) || empty($sessionToken) || !hash_equals($sessionToken, $submittedToken)) {
            $this->logService->log(LogType::ACCESS, 'Tentative de connexion avec token CSRF invalide', [
                'username' => $_POST['username'] ?? '',
                'submitted_token_length' => strlen($submittedToken),
                'session_token_exists' => !empty($sessionToken),
                'ip' => $_SERVER['REMOTE_ADDR'] ?? '',
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? ''
            ], 'DANGER');

            $_SESSION['flash_message'] = [
                'type' => 'danger',
                'message' => 'Token de sécurité invalide. Veuillez réessayer.'
            ];
            header('Location: /login');
            exit;
        }

        // Supprimer le token après utilisation
        unset($_SESSION['csrf_token']);

        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';

        if (empty($username) || empty($password)) {
            $this->logService->logAccess('LOGIN_ATTEMPT_EMPTY_FIELDS', ['username' => $username]);
            $_SESSION['flash_message'] = ['type' => 'danger', 'message' => 'Veuillez remplir tous les champs.'];
            header('Location: /login');
            exit;
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
                'user_id' => $_SESSION['user']['id'],
                'session_destroyed' => true
            ]);
        }
        $_SESSION = [];
        session_destroy();

        header('Location: /');
        exit;
    }
}