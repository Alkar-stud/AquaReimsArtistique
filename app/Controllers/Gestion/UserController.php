<?php
namespace app\Controllers\Gestion;

use app\Attributes\Route;
use app\Controllers\AbstractController;
use app\Models\User\Role;
use app\Models\User\User;
use app\Repository\User\RoleRepository;
use app\Repository\User\UserRepository;
use app\Services\Mails\MailPrepareService;
use app\Services\Security\TokenGenerateService;
use app\Services\DataValidation\UserDataValidationService;
use app\Utils\BuildLink;
use Exception;

class UserController extends AbstractController
{
    private UserRepository $userRepository;
    private RoleRepository $roleRepository;
    private UserDataValidationService $userDataValidationService;
    private Role $role;

    public function __construct()
    {
        parent::__construct(false);
        $this->userRepository = new UserRepository();
        $this->roleRepository = new RoleRepository();
        $this->userDataValidationService = new UserDataValidationService();
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
            'csrf_token_add' => $this->csrfService->getToken('/gestion/users/add'),
            'csrf_token_edit' => $this->csrfService->getToken('/gestion/users/edit'),
            'csrf_token_delete' => $this->csrfService->getToken('/gestion/users/delete')
        ], 'gestion des utilisateurs');
    }

    #[Route('/gestion/users/add', name: 'app_gestion_users_add', methods: ['POST'])]
    public function add(): void
    {
        //Vérifications d'usage
        $this->checksForUser('add', null);

        $tokenGenerateService = new TokenGenerateService();
        // Générer mot de passe aléatoire (on récupère la chaîne du token)
        $randomPasswordData = $tokenGenerateService->generateToken(12);
        //on le hash
        $hashedPassword = password_hash($randomPasswordData['token'], PASSWORD_DEFAULT, ['cost' => (int)$_ENV['BCRYPT_ROUNDS']]);

        // Générer token de réinitialisation
        $resetTokenData = $tokenGenerateService->generateToken((int)NB_CARACTERE_TOKEN, 'PT1H');

        // Créer l'utilisateur avec les données validées
        $newUser = new User();
        $newUser->setUsername($this->userDataValidationService->getUsername())
            ->setPassword($hashedPassword)
            ->setEmail($this->userDataValidationService->getEmail())
            ->setDisplayName($this->userDataValidationService->getDisplayName() ?: null)
            ->setRole($this->role)
            ->setIsActif($this->userDataValidationService->getIsActive())
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

    #[Route('/gestion/users/edit', name: 'app_gestion_users_edit', methods : ['POST'])]
    public function edit(): void
    {
        //On récupère le user.
        $userId = (int)($_POST['user_id'] ?? 0);

        $user = $this->userRepository->findById($userId);
        if (!$user) {
            $this->flashMessageService->setFlashMessage('danger', "Utilisateur non trouvé.");
            $this->redirectWithAnchor('/gestion/users');
        }
        //Vérifications d'usage
        $this->checksForUser('update', $user);

        // mettre à jour le user en forçant la déconnexion pour prise en compte des modifications
        $user->setUsername($this->userDataValidationService->getUsername())
            ->setEmail($this->userDataValidationService->getEmail())
            ->setDisplayName($this->userDataValidationService->getDisplayName() ?: null)
            ->setRole($this->role)
            ->setIsActif($this->userDataValidationService->getIsActive())
            ->setSessionId(null);

        $this->flashMessageService->setFlashMessage('success','Utilisateur modifié.');

        $this->userRepository->update($user);
        $this->redirectWithAnchor('/gestion/users');
    }

    #[Route('/gestion/users/delete', name: 'app_gestion_users_delete', methods : ['POST'])]
    public function delete(): void
    {
        //On vérifie que le CurrentUser a bien le droit de faire ça
        $this->checkIfCurrentUserIsAllowedToManagedOthersUsers();

        //On récupère le user.
        $userId = (int)($_POST['user_id'] ?? 0);
        $user = $this->userRepository->findById($userId);
        if (!$user) {
            $this->flashMessageService->setFlashMessage('danger', "Utilisateur non trouvé.");
            $this->redirect('/gestion/users');
        }

        $this->userRepository->delete($userId);
        $this->flashMessageService->setFlashMessage('success', "Utilisateur supprimé.");
        $this->redirect('/gestion/users');
    }


    /**
     * Vérifications d'usage pour l'ajout ou la modification d'un utilisateur
     * $action possible pour la vérification d'email add ou update
     * @param string $action
     * @param User|null $user
     * @return void
     */
    private function checksForUser(string $action, ?User $user): void
    {
        //On vérifie que le CurrentUser a bien le droit de faire ça
        $this->checkIfCurrentUserIsAllowedToManagedOthersUsers();

        // Validation des données centralisée
        $error = $this->userDataValidationService->checkData($_POST);
        if ($error) {
            $this->flashMessageService->setFlashMessage('danger', $error);
            $this->redirect('/gestion/users');
        }

        //On vérifie que le rôle est bien strictement inférieur à celui du currentUser
        $this->role = $this->roleRepository->findById($this->userDataValidationService->getRoleId());
        if ($this->role->getLevel() <= $this->currentUser->getRole()->getLevel()) {
            $this->flashMessageService->setFlashMessage('danger', "Rôle non autorisé.");
            $this->redirect('/gestion/users');
        }

        //On vérifie que l'email n'existe pas déjà si c'est nécessaire
        //Si ajout, vérification simple
        //Si modif, vérification si $this->userDataValidationService->getEmail() est différent de $user->getEmail.
        if ($action == 'add' || $user->getEmail() == $this->userDataValidationService->getEmail()) {
            if (!$this->userDataValidationService->getEmail() &&
                $this->userRepository->findByEmail($this->userDataValidationService->getEmail())
            ) {
                $this->flashMessageService->setFlashMessage('danger', "Cette adresse email est déjà utilisée.");
                $this->redirect('/gestion/users');
            }
        }


    }

}