<?php

namespace app\Controllers\Gestion;

use app\Attributes\Route;
use app\Controllers\AbstractController;
use app\Models\User\User;
use app\Repository\User\RoleRepository;
use app\Repository\User\UserRepository;
use app\Services\MailService;
use DateMalformedStringException;
use DateTime;
use Exception;
use Random\RandomException;

#[Route('/gestion/users', name: 'app_gestion_users')]
class UserController extends AbstractController
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
     * @throws DateMalformedStringException
     */
    public function index(): void
    {
        $users = $this->userRepository->findAllByLevel();
        $roles = $this->roleRepository->findAllByLevel();
        $currentUser = $_SESSION['user'] ?? null;
        if (is_array($currentUser)) {
            $userRepository = new UserRepository();
            $currentUser = $userRepository->findById($currentUser['id']);
        }

        // Récupérer le message flash s'il existe
        $flashMessage = $this->flashMessageService->getFlashMessage();
        $this->flashMessageService->unsetFlashMessage();

        $this->render('/gestion/user', [
            'users' => $users,
            'roles' => $roles,
            'currentUser' => $currentUser,
            'flash_message' => $flashMessage
        ], 'Gestion des utilisateurs');
    }

    /**
     * @throws DateMalformedStringException
     * @throws RandomException
     */
    #[Route('/gestion/users/add', name: 'app_gestion_users_add')]
    public function add(): void
    {
        $currentUser = $_SESSION['user'] ?? null;
        if (!$currentUser || !in_array($currentUser['role']['level'], [0, 1])) {
            $this->flashMessageService->setFlashMessage('danger', "Accès refusé");
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
                $this->flashMessageService->setFlashMessage('danger', "Username et email sont obligatoires et doivent être valides.");
                header('Location: /gestion/users/add');
                exit;
            }

            //On vérifie que l'email n'existe pas déjà
            if ($this->userRepository->findByEmail($email)) {
                $this->flashMessageService->setFlashMessage('danger', "Cette adresse email est déjà utilisée.");
                header('Location: /gestion/users');
                exit;
            }

            $role = $this->roleRepository->findById($roleId);
            if (!$role || $role->getLevel() <= $currentUser['role']['level']) {
                $this->flashMessageService->setFlashMessage('danger', "Rôle non autorisé.");
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
                ->setIsActif(true)
                ->setCreatedAt(date('Y-m-d H:i:s'))
                ->setPasswordResetToken($token)
                ->setPasswordResetExpiresAt($expiresAt);

            $this->userRepository->insert($user);

            // Envoyer l'email
            try {
                $mailService = new MailService();
                $protocol = "https://";
                $resetLink = $protocol . $_SERVER['HTTP_HOST'] . '/reset-password?token=' . $token;
                $mailService->send(
                    $email,
                    'new_account',
                    [
                        'display_name' => $displayName,
                        'username' => $username,
                        'app_name' => $_ENV['APP_NAME'],
                        'timeout_token_new_account' => '1 heure',
                        'link' => $resetLink
                    ]
                );
            } catch (Exception $e) {
                error_log('Erreur MailService: ' . $e->getMessage());
            }

            $this->flashMessageService->setFlashMessage('success', "Utilisateur créé et email envoyé.");
            header('Location: /gestion/users');
            exit;
        }

        // Récupérer le message flash s'il existe
        $flashMessage = $this->flashMessageService->getFlashMessage();
        $this->flashMessageService->unsetFlashMessage();

        // Affichage du formulaire
        $this->render('/gestion/user', [
            'roles' => $roles,
            'currentUser' => $currentUser,
            'flash_message' => $flashMessage
        ], 'Créer un utilisateur');
    }

    /**
     * @throws DateMalformedStringException
     */
    #[Route('/gestion/users/edit', name: 'app_gestion_users_edit')]
    public function edit(): void
    {
        $currentUser = $_SESSION['user'] ?? null;
        if (!$currentUser || !in_array($currentUser['role']['level'], [0, 1])) {
            $this->flashMessageService->setFlashMessage('danger', "Accès refusé");
            header('Location: /gestion/users');
            exit;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $id = (int)($_POST['id'] ?? 0);
            $username = trim($_POST['username'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $displayName = trim($_POST['display_name'] ?? '');
            $roleId = (int)($_POST['role'] ?? 0);
            $isActif = isset($_POST['is_actif']) && $_POST['is_actif'] === 'on' ? 1 : 0;

            // Validation
            if (empty($username) || empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $this->flashMessageService->setFlashMessage('danger', "Username et email obligatoires et valides.");
                header('Location: /gestion/users/add');
                exit;
            }

            //On récupère le user avant modification pour comparer
            $user = $this->userRepository->findById($id);

            if (!$user) {
                $this->flashMessageService->setFlashMessage('danger', "Utilisateur introuvable.");
                header('Location: /gestion/users');
                exit;
            }

            //On vérifie que l'email n'existe pas déjà
            if ($this->userRepository->findByEmail($email) && $email != $user->getEmail()) {
                $this->flashMessageService->setFlashMessage('danger', "Cette adresse email est déjà utilisée.");
                header('Location: /gestion/users');
                exit;
            }

            $role = $this->roleRepository->findById($roleId);
            if (!$role || $role->getLevel() <= $currentUser['role']['level']) {
                $this->flashMessageService->setFlashMessage('danger', "Rôle non autorisé.");
                header('Location: /gestion/users');
                exit;
            }

            $user->setUsername($username)
                ->setEmail($email)
                ->setDisplayName($displayName ?: null)
                ->setRole($role)
                ->setIsActif($isActif);

            $this->userRepository->update($user);

            $this->flashMessageService->setFlashMessage('success', "Utilisateur modifié.");
            header('Location: /gestion/users');
            exit;
        }

        header('Location: /gestion/users');
        exit;
    }

    /**
     */
    #[Route('/gestion/users/suspend', name: 'app_gestion_users_suspend')]
    public function suspendOnOff(): void
    {
        // Autorisation
        $currentUser = $_SESSION['user'] ?? null;
        if (!$currentUser || !in_array($currentUser['role']['level'], [0, 1])) {
            http_response_code(403);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Accès refusé']);
            exit;
        }

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            header('Allow: POST');
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Méthode non autorisée']);
            exit;
        }

        $raw = file_get_contents('php://input');
        $data = json_decode($raw, true);

        $id = (int)($data['id'] ?? 0);
        $actif = (bool)($data['actif'] ?? false);

        if ($id <= 0) {
            http_response_code(400);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'ID invalide']);
            exit;
        }

        $user = $this->userRepository->findById($id);
        if (!$user) {
            http_response_code(404);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Utilisateur introuvable']);
            exit;
        }

        $this->userRepository->suspendOnOff($id, $actif);

        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'id' => $id,
            'actif' => $actif
        ]);
        exit;
    }

    /**
     * @throws DateMalformedStringException
     */
    #[Route('/gestion/users/delete', name: 'app_gestion_users_delete')]
    public function delete(): void
    {
        $currentUser = $this->accessIsAllowed(); // Vérifie droits (level 0 ou 1)

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: /gestion/users');
            exit;
        }

        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) {
            $this->flashMessageService->setFlashMessage('danger', 'ID utilisateur manquant.');
            header('Location: /gestion/users');
            exit;
        }

        // Empêcher la suppression de son propre compte (optionnel mais recommandé)
        if ($id === (int)$currentUser['id']) {
            $this->flashMessageService->setFlashMessage('danger', "Vous ne pouvez pas supprimer votre propre compte.");
            header('Location: /gestion/users');
            exit;
        }

        $user = $this->userRepository->findById($id);
        if (!$user) {
            $this->flashMessageService->setFlashMessage('danger', "Utilisateur introuvable.");
            header('Location: /gestion/users');
            exit;
        }

        $this->userRepository->delete($id);
        $this->flashMessageService->setFlashMessage('success', "Utilisateur supprimé.");
        header('Location: /gestion/users');
        exit;
    }

    /**
     * @return array
     */
    private function accessIsAllowed(): array
    {
        $currentUser = $_SESSION['user'] ?? null;
        if (!$currentUser || !in_array($currentUser['role']['level'], [0, 1])) {
            http_response_code(403);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Accès refusé']);
            exit;
        }

        return $currentUser;
    }


}