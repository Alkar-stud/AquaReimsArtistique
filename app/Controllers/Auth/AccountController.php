<?php
namespace app\Controllers\Auth;

use app\Attributes\Route;
use app\Controllers\AbstractController;
use app\Repository\User\UserRepository;
use app\Services\Mails\MailPrepareService;
use app\Utils\FlashMessageService;
use DateMalformedStringException;
use Exception;

#[Route('/account', name: 'app_account')]
class AccountController extends AbstractController
{
    private FlashMessageService $flashMessageService;

    public function __construct()
    {
        parent::__construct(false); // true = route publique, pas de vérif session pour éviter le TOO_MANY_REDIRECT
        $this->flashMessageService = new FlashMessageService();
    }
    public function index(): void
    {

        // Récupérer le message flash s'il existe
        $flashMessage = $this->flashMessageService->getFlashMessage();
        $this->flashMessageService->unsetFlashMessage();

        $this->render('auth/account', [
            'flash_message' => $flashMessage
        ], 'Mon compte');
    }

    /**
     * @throws DateMalformedStringException
     */
    #[Route('/account/update', name: 'app_account_update')]
    public function updateData(): void
    {
        $displayname = htmlspecialchars(trim($_POST['displayname'] ?? ''));
        $email = $_POST['email'] ?? '';

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $_SESSION['flash_message'] = [
                'type' => 'danger',
                'message' => "L'adresse email saisie est invalide."
            ];
            header('Location: /account');
            exit;
        }

        //On met à jour les champs concernés, displayname à NULL si vide
        $userRepository = new UserRepository();
        $userId = $_SESSION['user']['id'] ?? null;
        if ($userId) {
            //on vérifie si l'adresse mail n'est pas utilisée par quelqu'un d'autre
            $userEmailTarget = $userRepository->findByEmail($email);

            if ($userEmailTarget && $userEmailTarget->getId() != $userId) {
                $_SESSION['flash_message'] = [
                    'type' => 'danger',
                    'message' => "Il est impossible de mettre à jour cette adresse email."
                ];
                header('Location: /account');
                exit;
            }
            if ($displayname == '') {
                $displayName = null;
            }
            //Si l'adresse mail et displayname sont identiques, on ne met pas à jour, mais on met quand même le message.
            if ($displayname == $_SESSION['user']['displayname'] && $email == $_SESSION['user']['email']) {
                $_SESSION['flash_message'] = [
                    'type' => 'info',
                    'message' => "Vos informations n'ont pas été modifiées."];
                header('Location: /account');
                exit;
            }
            if ($userRepository->updateData($userId, $displayname, $email)) {
                $_SESSION['user']['displayname'] = $displayname;
                $_SESSION['user']['email'] = $email;
                $_SESSION['flash_message'] = [
                    'type' => 'success',
                    'message' => "Vos informations ont bien été mise à jour."];
                header('Location: /account');
                exit;
            } else {
                $_SESSION['flash_message'] = [
                    'type' => 'danger',
                    'message' => "Erreur lors de la mise à jour de vos informations."];
            }
        }

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
            $_SESSION['flash_message'] = [
                'type' => 'danger',
                'message' => "Les mots de passe ne correspondent pas."];
            header('Location: /account');
            exit;
        }

        //Si le nouveau mot de passe est identique au mot de passe actuel, on refuse
        if ($new_password == $current_password) {
            $_SESSION['flash_message'] = [
                'type' => 'warning',
                'message' => "Le nouveau mot de passe est identique à l'ancien."];
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

            $_SESSION['flash_message'] = [
                'type' => 'success',
                'message' => "Le mot de passe a bien été changé."];
        } else {
            $_SESSION['flash_message'] = [
                'type' => 'danger',
                'message' => "Le mot de passe actuel est erroné."];
        }
        header('Location: /account');
        exit;
    }


}