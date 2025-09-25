<?php
namespace app\Controllers\Auth;

use app\Attributes\Route;
use app\Controllers\AbstractController;
use app\Models\User\User;
use app\Repository\User\UserRepository;
use app\Services\Mails\MailPrepareService;
use app\Services\Security\PasswordPolicyService;
use app\Services\Security\TokenGenerateService;
use app\Utils\BuildLink;
use Exception;


class PasswordResetController extends AbstractController
{
    private TokenGenerateService $tokenGenerate;
    private UserRepository $userRepository;
    private PasswordPolicyService $passwordPolicyService;
    private BuildLink $buildLink;
    private string $successMessage = 'Si votre email est connu de notre système, vous allez recevoir sous peu un email vous permettant de changer votre mot de passe.';

    public function __construct()
    {
        parent::__construct(true);
        $this->tokenGenerate = new TokenGenerateService();
        $this->userRepository = new UserRepository();
        $this->passwordPolicyService = new PasswordPolicyService();
        $this->buildLink = new BuildLink();
    }

    // --- FORGOT PASSWORD (GET) ---
    #[Route('/forgot-password', name: 'app_forgot_password', methods: ['GET'])]
    public function showForgotForm(): void
    {
        $this->render('password/forgot', [], 'Mot de passe oublié');
    }

    #[Route('/forgot-password-submit', name: 'app_forgot_password-submit', methods: ['POST'])]
    public function submitForgotForm(): void
    {
        $email = trim($_POST['email'] ?? '');
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->flashMessageService->setFlashMessage('danger', "Veuillez fournir une adresse email valide.");
            $this->redirect('/forgot-password');
        }

        //On tente de récupérer User via son email
        $user = $this->userRepository->findByEmail($email);
        if (!$user) {
            $this->flashMessageService->setFlashMessage('success', $this->successMessage);
            $this->redirect('/forgot-password');
        }

        //Génération du token
        $token = $this->tokenGenerate->generateToken($_ENV['NB_CARACTERE_TOKEN'], 'PT1H');

        // Sauvegarder le token et la date dans la BDD
        $this->userRepository->savePasswordResetToken($user->getId(), $token['token'], $token['expires_at_str']);

        // Envoyer l'email avec le lien de réinitialisation
        $resetLink = $this->buildLink::buildResetLink('/reset-password', $token['token']);

        try {
            (new MailPrepareService())->sendPasswordResetEmail(
                $user->getEmail(),
                $user->getDisplayName(),
                $resetLink
            );
        } catch (Exception $e) {
            error_log('Erreur critique du service Mail: ' . $e->getMessage());
        }

        $this->flashMessageService->setFlashMessage('success', $this->successMessage);
        $this->redirect('/forgot-password');
    }

    #[Route('/reset-password', name: 'app_reset_password')]
    public function showResetPassword(): void
    {
        $token = $_GET['token'] ?? '';
        $this->checkReinitTokenUser($token);
        //Pour envoyer le bon contexte
        $this->render('password/reset', [
            'token' => $token,
            'password_rules' => $this->passwordPolicyService->getRulesAsText(),
        ], 'Réinitialiser le mot de passe');
    }

    #[Route('/reset-password-submit', name: 'app_reset_password-submit', methods: ['POST'])]
    public function resetPasswordSubmit(): void
    {
        $token = trim($_POST['token'] ?? '');
        $user = $this->checkReinitTokenUser($token);

        $password = $_POST['password'] ?? '';
        $passwordConfirm = $_POST['password_confirm'] ?? '';
        // Valider les mots de passe
        if (empty($password) || $password !== $passwordConfirm) {
            $this->flashMessageService->setFlashMessage('danger', "Les mots de passe ne correspondent pas ou sont vides.");
            // On redirige vers la même page pour que l'utilisateur puisse réessayer
            $this->redirect('/reset-password?token=' . $token);
        }

        // Valider la complexité du mot de passe
        $policyErrors = $this->passwordPolicyService->validate($password);
        if (!empty($policyErrors)) {
            $this->flashMessageService->setFlashMessage('danger', implode("\n", $policyErrors));
            $this->redirect('/reset-password?token=' . rawurlencode($token));
        }

        // Tout est bon : on met à jour le mot de passe
        $cost = (int)($_ENV['BCRYPT_ROUNDS'] ?? 12);
        $newHashedPassword = password_hash($password, PASSWORD_DEFAULT, ['cost' => $cost]);
        $this->userRepository->updatePassword($user->getId(), $newHashedPassword);

        // IMPORTANT : On invalide le token pour qu'il ne soit pas réutilisé
        $this->userRepository->clearResetToken($user->getId());

        $this->flashMessageService->setFlashMessage('success', "Votre mot de passe a été réinitialisé avec succès. Vous pouvez maintenant vous connecter.");
        $this->redirect('/login');
    }

    private function checkReinitTokenUser(string $token): ?User
    {
        $user = $this->userRepository->findByValidResetToken($token);
        if (!$user) {
            $this->flashMessageService->setFlashMessage('danger', "Ce lien de réinitialisation est invalide ou a expiré.");
            $this->redirect('/forgot-password');
        }
        return $user;
    }


}
