<?php
namespace app\Controllers;

use app\Attributes\Route;
use app\Repository\UserRepository;

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
            $this->handleLogin();
        } else {
            $this->render('login', [], 'Connexion');
        }
    }

    private function handleLogin(): void
    {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';

        if (empty($username) || empty($password)) {
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

            // On stocke les informations de l'utilisateur en session
            $_SESSION['user'] = [
                'id' => $user->getId(),
                'LAST_ACTIVITY' => time(),
                'username' => $user->getUsername(),
                'displayname' => $user->getDisplayName(),
                'email' => $user->getEmail(),
            ];
            // On ajoute les informations du rôle s'il existe
            if ($user->getRole()) {
                $_SESSION['user']['role'] = [
                    'id' => $user->getRole()->getId(),
                    'name' => $user->getRole()->getLibelle(),
                    'level' => $user->getRole()->getLevel()
                ];
            }
            session_start();
            //et on ajoute l'identifiant de session en BDD
            $userRepository->addSessionId($user->getId(), session_id());

            header('Location: /');
            exit;
        }

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
        //On récupère le sessionID pour le supprimer en BDD

        // Si l'utilisateur avait un token de session, on le supprime
        if (isset($_SESSION['user']['id'])) {
            $userRepository = new UserRepository();
            $userRepository->removeSessionId($_SESSION['user']['id'], session_id());
        }
        $_SESSION = [];
        session_destroy();

        header('Location: /');
        exit;
    }
}