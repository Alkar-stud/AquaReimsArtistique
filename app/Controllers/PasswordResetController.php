<?php
namespace app\Controllers;

use app\Attributes\Route;
use app\Repository\UserRepository;
use app\Services\MailService;
use DateMalformedStringException;
use DateTime;
use Exception;
use Random\RandomException;

class PasswordResetController extends AbstractController
{
    public function __construct()
    {
        parent::__construct(true); // true = route publique, pas de vérif session pour éviter le TOO_MANY_REDIRECT
    }
    /**
     * Gère l'affichage (GET) et le traitement (POST) du formulaire de mot de passe oublié.
     * @throws RandomException
     * @throws DateMalformedStringException
     */
    #[Route('/forgot-password', name: 'app_forgot_password')]
    public function forgotPassword(): void
    {
        // On vérifie la méthode de la requête
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $email = trim($_POST['email'] ?? '');
            if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $_SESSION['flash_message'] = ['type' => 'danger', 'message' => 'Veuillez fournir une adresse email valide.'];
                header('Location: /forgot-password');
                exit;
            }

            $userRepository = new UserRepository();
            $user = $userRepository->findByEmail($email);

            if ($user) {
                // Générer un token sécurisé
                $token = bin2hex(random_bytes(32));

                // Définir une date d'expiration (ex : 1 heure)
                $date = new DateTime();
                $date->modify('+1 hour');
                $expiresAt = $date->format('Y-m-d H:i:s');

                // Sauvegarder le token et la date dans la BDD
                $userRepository->savePasswordResetToken($user->getId(), $token, $expiresAt);

                // Envoyer l'email avec le lien de réinitialisation
                try {
                    $mailService = new MailService();
                    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https://" : "http://";
                    $resetLink = $protocol . '' . $_SERVER['HTTP_HOST'] . '/reset-password?token=' . $token;

                    $mailService->sendPasswordResetEmail(
                        $user->getEmail(),
                        $user->getDisplayName(),
                        $resetLink
                    );
                } catch (Exception $e) {
                    error_log('Erreur critique du service Mail: ' . $e->getMessage());
                }
            }

            // IMPORTANT : Toujours afficher un message de succès générique pour la sécurité.
            $_SESSION['flash_message'] = ['type' => 'success', 'message' => 'Si votre adresse email est dans notre système, vous recevrez un lien pour réinitialiser votre mot de passe.'];
            header('Location: /forgot-password');
            exit;

        } else {
            // --- Logique GET ---
            $this->render('password/forgot', [], 'Mot de passe oublié');
        }
    }

    /**
     * Gère l'affichage (GET) et le traitement (POST) du formulaire de réinitialisation.
     * @throws DateMalformedStringException
     */
    #[Route('/reset-password', name: 'app_reset_password')]
    public function resetPassword(): void
    {
        $userRepository = new UserRepository();

        // --- Logique pour la requête POST (soumission du formulaire) ---
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $token = $_POST['token'] ?? '';
            $password = $_POST['password'] ?? '';
            $passwordConfirm = $_POST['password_confirm'] ?? '';

            // Valider le token une nouvelle fois
            $user = $userRepository->findByValidResetToken($token);

            if (!$user) {
                // Si le token est devenu invalide entre-temps
                $_SESSION['flash_message'] = ['type' => 'danger', 'message' => 'Ce lien de réinitialisation est invalide ou a expiré. Veuillez refaire une demande.'];
                header('Location: /forgot-password');
                exit;
            }

            // Valider les mots de passe
            if (empty($password) || $password !== $passwordConfirm) {
                $_SESSION['flash_message'] = ['type' => 'danger', 'message' => 'Les mots de passe ne correspondent pas ou sont vides.'];
                // On redirige vers la même page pour que l'utilisateur puisse réessayer
                header('Location: /reset-password?token=' . $token);
                exit;
            }

            // Tout est bon : on met à jour le mot de passe
            $newHashedPassword = password_hash($password, PASSWORD_DEFAULT, ['cost' => (int)$_ENV['BCRYPT_ROUNDS']]);
            $userRepository->updatePassword($user->getId(), $newHashedPassword);

            // IMPORTANT : On invalide le token pour qu'il ne soit pas réutilisé
            $userRepository->clearResetToken($user->getId());

            // 5. On redirige vers la page de connexion avec un message de succès
            $_SESSION['flash_message'] = ['type' => 'success', 'message' => 'Votre mot de passe a été réinitialisé avec succès. Vous pouvez maintenant vous connecter.'];
            header('Location: /login');
            exit;
        }

        // --- Logique pour la requête GET (affichage de la page) ---
        $token = $_GET['token'] ?? '';
        $user = $userRepository->findByValidResetToken($token);

        if (!$user) {
            // Le lien est invalide ou a expiré
            $_SESSION['flash_message'] = ['type' => 'danger', 'message' => 'Ce lien de réinitialisation est invalide ou a expiré.'];
            header('Location: /forgot-password');
            exit;
        }

        // Le token est valide, on affiche le formulaire en lui passant le token
        $this->render('password/reset', ['token' => $token], 'Réinitialiser le mot de passe');
    }

}