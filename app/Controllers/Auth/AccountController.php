<?php
namespace app\Controllers\Auth;

use app\Attributes\Route;
use app\Controllers\AbstractController;
use app\Repository\User\UserRepository;
use app\Services\Mails\MailPrepareService;
use DateMalformedStringException;
use Exception;

#[Route('/account', name: 'app_account')]
class AccountController extends AbstractController
{

    public function __construct()
    {
        parent::__construct(false); // true = route publique, pas de vérif session pour éviter le TOO_MANY_REDIRECT
     }
    public function index(): void
    {
        // Récupérer le message flash s'il existe
        $flashMessage = $this->flashMessageService->getFlashMessage();
        $this->flashMessageService->unsetFlashMessage();

        $this->render('auth/account', [], 'Mon compte');
    }

    /**
     * @throws DateMalformedStringException
     */
    #[Route('/account/update', name: 'app_account_update')]
    public function updateData(): void
    {
        $displayName = htmlspecialchars(trim(filter_input(INPUT_POST, 'displayname') ?? ''));
        $email = filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL);

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->flashMessageService->setFlashMessage('danger', "L'adresse email saisie est invalide.");
            header('Location: /account');
            exit;
        }

        //On met à jour les champs concernés, displayname à NULL si vide
        $userRepository = new UserRepository();
        $userId = $_SESSION['user']['id'] ?? null;
        if (!$userId) {
            $this->flashMessageService->setFlashMessage('danger', "Votre session est invalide. Veuillez vous reconnecter.");
            header('Location: /login');
            exit;
        }

        // Si l'adresse mail et displayname sont identiques, on ne met pas à jour.
        if ($displayName === ($_SESSION['user']['displayname'] ?? '') && $email === $_SESSION['user']['email']) {
            $this->flashMessageService->setFlashMessage('info', "Vos informations n'ont pas été modifiées.");
            header('Location: /account');
            exit;
        }

        // On vérifie si l'adresse mail n'est pas déjà utilisée par un autre utilisateur.
        $userWithSameEmail = $userRepository->findByEmail($email);
        if ($userWithSameEmail && $userWithSameEmail->getId() !== $userId) {
            $this->flashMessageService->setFlashMessage('danger', "Cette adresse email est déjà utilisée par un autre compte.");
            header('Location: /account');
            exit;
        }

        if ($userRepository->updateData($userId, $displayName, $email)) {
            $_SESSION['user']['displayname'] = $displayName;
            $_SESSION['user']['email'] = $email;
            $this->flashMessageService->setFlashMessage('success', "Vos informations ont bien été mises à jour.");
        } else {
            $this->flashMessageService->setFlashMessage('danger', "Une erreur est survenue lors de la mise à jour de vos informations.");
        }
        header('Location: /account');
        exit;

    }

    /**
     * @throws DateMalformedStringException
     */
    #[Route('/account/password', name: 'app_account_password')]
    public function updatePassword(): void
    {
        $current_password = $_POST['current_password'] ?? '';
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';

        //Si les nouveaux mots de passe ne sont pas identiques, on refuse
        if ($new_password != $confirm_password) {
            $this->flashMessageService->setFlashMessage('danger', "Erreur, les mots de passe ne correspondent pas");
            header('Location: /account');
            exit;
        }

        //Si le nouveau mot de passe est identique au mot de passe actuel, on refuse
        if ($new_password == $current_password) {
            $this->flashMessageService->setFlashMessage('warning', "Le nouveau mot de passe est identique à l'ancien.");
            header('Location: /account');
            exit;
        }

        $userRepository = new UserRepository();
        $user = $userRepository->findById($_SESSION['user']['id']);

        if ($user && password_verify($current_password, $user->getPassword())) {
            $newHash = password_hash($new_password, PASSWORD_DEFAULT, ['cost' => (int)$_ENV['BCRYPT_ROUNDS']]);
            $userRepository->updatePassword($user->getId(), $newHash);

            //on envoie un mail au user pour signaler le changement
            try {
                $mailPrepareService = new MailPrepareService();

                $mailPrepareService->sendPasswordModifiedEmail(
                    $user->getEmail(),
                    $user->getDisplayName()
                );
            } catch (Exception $e) {
                error_log('Erreur critique du service Mail: ' . $e->getMessage());
            }

            $this->flashMessageService->setFlashMessage('success', "Le mot de passe a bien été changé.");
        } else {
            $this->flashMessageService->setFlashMessage('danger', "Le mot de passe actuel est erroné.");
        }
        header('Location: /account');
        exit;
    }


}