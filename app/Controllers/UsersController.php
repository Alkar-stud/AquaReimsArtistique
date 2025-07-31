<?php

namespace app\Controllers;

use app\Attributes\Route;
use app\Repository\UserRepository;
use app\Repository\RoleRepository;
use app\Models\User;
use app\Services\MailService;
use DateTime;
use Exception;
use Random\RandomException;

#[Route('/gestion/users', name: 'app_gestion_users')]
class UsersController extends AbstractController
{
    private UserRepository $userRepository;
    private RoleRepository $roleRepository;

    public function __construct()
    {
        parent::__construct(false);
        $this->userRepository = new UserRepository();
        $this->roleRepository = new RoleRepository();
    }

    /**
     * @throws \DateMalformedStringException
     */
    public function index(): void
    {
        $users = $this->userRepository->findAll();
        $roles = $this->roleRepository->findAllByLevel();
        $currentUser = $_SESSION['user'] ?? null;
        if (is_array($currentUser)) {
            $userRepository = new UserRepository();
            $currentUser = $userRepository->findById($currentUser['id']);
        }
        $this->render('/gestion/users', [
            'users' => $users,
            'roles' => $roles,
            'currentUser' => $currentUser
        ], 'Gestion des utilisateurs');
    }

    /**
     * @throws \DateMalformedStringException
     * @throws RandomException
     */
    #[Route('/gestion/users/add', name: 'app_gestion_users_add')]
    public function add(): void
    {
        $currentUser = $_SESSION['user'] ?? null;
        if (!$currentUser || !in_array($currentUser['role']['level'], [0, 1])) {
            $_SESSION['flash_message'] = ['type' => 'danger', 'message' => 'Accès refusé.'];
            header('Location: /gestion/users');
            exit;
        }

        $roles = $this->roleRepository->findAllByLevel();

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $username = trim($_POST['username'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $displayName = trim($_POST['display_name'] ?? '');
            $roleId = (int)($_POST['role'] ?? 0);

            // Validation
            if (empty($username) || empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $_SESSION['flash_message'] = ['type' => 'danger', 'message' => 'Username et email obligatoires et valides.'];
                header('Location: /gestion/users/add');
                exit;
            }

            $role = $this->roleRepository->findById($roleId);
            if (!$role || $role->getLevel() <= $currentUser['role']['level']) {
                $_SESSION['flash_message'] = ['type' => 'danger', 'message' => 'Rôle non autorisé.'];
                header('Location: /gestion/users/add');
                exit;
            }

            // Générer mot de passe aléatoire
            $randomPassword = bin2hex(random_bytes(12));
            $hashedPassword = password_hash($randomPassword, PASSWORD_DEFAULT, ['cost' => (int)$_ENV['BCRYPT_ROUNDS']]);

            // Générer token de réinitialisation
            $token = bin2hex(random_bytes(32));
            $date = new DateTime();
            $date->modify('+1 hour');
            $expiresAt = $date->format('Y-m-d H:i:s');

            // Créer l'utilisateur
            $user = new User();
            $user->setUsername($username)
                ->setPassword($hashedPassword)
                ->setEmail($email)
                ->setDisplayName($displayName ?: null)
                ->setRole($role)
                ->setCreatedAt(date('Y-m-d H:i:s'))
                ->setPasswordResetToken($token)
                ->setPasswordResetExpiresAt($expiresAt);

            $this->userRepository->insert($user);

            // Envoyer l'email
            try {
                $mailService = new MailService();
                $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https://" : "http://";
                $resetLink = $protocol . $_SERVER['HTTP_HOST'] . '/reset-password?token=' . $token;
                $mailService->send(
                    $email,
                    'new_account',
                    [
                        'username' => $username,
                        'app_name' => $_ENV['APP_NAME'],
                        'timeout_token_new_account' => '1 heure',
                        'link' => $resetLink
                    ]
                );
            } catch (Exception $e) {
                error_log('Erreur MailService: ' . $e->getMessage());
            }

            $_SESSION['flash_message'] = ['type' => 'success', 'message' => 'Utilisateur créé et email envoyé.'];
            header('Location: /gestion/users');
            exit;
        }

        // Affichage du formulaire
        $this->render('/gestion/users', [
            'roles' => $roles,
            'currentUser' => $currentUser
        ], 'Créer un utilisateur');
    }
}