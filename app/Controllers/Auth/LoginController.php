<?php
namespace app\Controllers\Auth;

use app\Attributes\Route;
use app\Controllers\AbstractController;
use app\Repository\User\UserRepository;
use app\Services\Auth\UserSessionService;
use app\Services\Log\Logger;

class LoginController extends AbstractController
{
    private UserRepository $userRepository;
    private UserSessionService $userSessionService;

    public function __construct()
    {
        parent::__construct(true);
        $this->userRepository = new UserRepository();
        $this->userSessionService = new UserSessionService($this->userRepository);
    }

    #[Route('/login', name: 'app_login', methods: ['GET'])]
    public function index(): void
    {
        // Genère un token CSRF pour le contexte POST cible
        $this->render('auth/login', [], 'Connexion');
    }

    #[Route('/login-check', name: 'app_login_check', methods: ['POST'])]
    public function login(): void
    {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';

        if ($username === '' || $password === '') {
            $this->flashMessageService->setFlashMessage('danger', 'Veuillez remplir tous les champs.');
            $this->redirect('/login');
        }

        $user = $this->userRepository->findByUsername($username);

        if (
            $user &&
            $user->getUsername() === $username && // Vérification stricte de la casse
            password_verify($password, $user->getPassword())
        ) {
            Logger::get()->security('login_success', ['username' => $username, 'user_id' => $user->getId()]);

            if (password_needs_rehash($user->getPassword(), PASSWORD_DEFAULT, ['cost' => (int)$_ENV['BCRYPT_ROUNDS']])) {
                $newHash = password_hash($password, PASSWORD_DEFAULT, ['cost' => (int)$_ENV['BCRYPT_ROUNDS']]);
                $this->userRepository->updatePassword($user->getId(), $newHash);
            }

            $this->userSessionService->login($user);

            $redirectUrl = $_SESSION['redirect_after_login'] ?? '/';
            unset($_SESSION['redirect_after_login']);
            if (!$this->isValidInternalRedirect($redirectUrl)) {
                $redirectUrl = '/';
            }

            $this->redirect($redirectUrl);
        } else {
            Logger::get()->security('login_fail', ['username' => $username]);
        }

        $this->flashMessageService->setFlashMessage('danger', 'Identifiants incorrects.');
        $this->redirect('/login');
    }

    #[Route('/logout', name: 'app_logout')]
    public function logout(): void
    {
        $this->userSessionService->logout();
        $this->redirect('/');
    }

    private function isValidInternalRedirect(string $url): bool
    {
        return str_starts_with($url, '/') && !str_contains($url, '://');
    }
}
