<?php
namespace app\Controllers\Gestion;

use app\Attributes\Route;
use app\Controllers\AbstractController;
use app\Models\User\User;
use app\Repository\User\RoleRepository;
use app\Repository\User\UserRepository;
use app\Services\Mails\MailPrepareService;
use app\Services\Security\TokenGenerateService;
use app\Utils\BuildLink;
use Exception;

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

    #[Route('/gestion/users', name: 'app_gestion_users')]
    public function index(): void
    {
        // On s'assure qu'un utilisateur est connecté pour obtenir son niveau de rôle
        $level = $this->currentUser?->getRole()?->getLevel() ?? 0;

        $this->render('/gestion/users', [
            'users' => $this->userRepository->findAllWithRoleLevelLowerThan($level),
            'roles' => $this->roleRepository->findAll(),
            'currentUser' => $this->currentUser,
            'csrf_token' => $this->csrfService->getToken('/gestion/users/add')
        ], 'gestion des utilisateurs');
    }

    #[Route('/gestion/users/add', name: 'app_gestion_users_add', methods: ['POST'])]
    public function add(): void
    {
        //On vérifie que le CurrentUser a bien le droit de faire ça
        if (!$this->currentUser || !in_array($this->currentUser->getRole()->getLevel(), [0, 1])) {
            $this->flashMessageService->setFlashMessage('danger', "Accès refusé");
            $this->redirect('/gestion/users');
        }

        $roles = $this->roleRepository->findAll();

        $username = trim($_POST['username'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $displayName = trim($_POST['display_name'] ?? '');
        $roleId = (int)($_POST['role'] ?? 0);

        // Validation
        if (empty($username) || empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->flashMessageService->setFlashMessage('danger', "Username et email sont obligatoires et doivent être valides.");
            $this->redirect('/gestion/users');
        }

        //On vérifie que l'email n'existe pas déjà
        if ($this->userRepository->findByEmail($email)) {
            $this->flashMessageService->setFlashMessage('danger', "Cette adresse email est déjà utilisée.");
            $this->redirect('/gestion/users');
        }

        //On vérifie que le rôle est bien strictement inférieur à celui du currentUser
        $role = $this->roleRepository->findById($roleId);
        if (!$role || $role->getLevel() <= $this->currentUser->getRole()->getLevel()) {
            $this->flashMessageService->setFlashMessage('danger', "Rôle non autorisé.");
            $this->redirect('/gestion/users');
        }

        $tokenGenerateService = new TokenGenerateService();
        // Générer mot de passe aléatoire
        // Générer mot de passe aléatoire (on récupère la chaîne du token)
        $randomPasswordData = $tokenGenerateService->generateToken(12);
        $hashedPassword = password_hash($randomPasswordData['token'], PASSWORD_DEFAULT, ['cost' => (int)$_ENV['BCRYPT_ROUNDS']]);

        // Générer token de réinitialisation
        $resetTokenData = $tokenGenerateService->generateToken((int)NB_CARACTERE_TOKEN, 'PT1H');

        // Créer l'utilisateur
        $newUser = new User();
        $newUser->setUsername($username)
            ->setPassword($hashedPassword)
            ->setEmail($email)
            ->setDisplayName($displayName ?: null)
            ->setRole($role)
            ->setIsActif(true)
            ->setPasswordResetToken($resetTokenData['token'])
            ->setPasswordResetExpiresAt($resetTokenData['expires_at_str']);

        $this->userRepository->insert($newUser);

        // Envoyer l'email
        try {
            $buildLink = new buildLink();
            $resetLink = $buildLink->buildResetLink('/reset-password', $resetTokenData['token']);
            (new MailPrepareService())->sendPasswordResetEmail(
                $newUser->getEmail(),
                $newUser->getDisplayName(),
                $resetLink
            );

        } catch (Exception $e) {
            error_log('Erreur MailService: ' . $e->getMessage());
        }
        $this->flashMessageService->setFlashMessage('success', "Utilisateur créé et email envoyé.");
        $this->redirect('/gestion/users');

    }


}