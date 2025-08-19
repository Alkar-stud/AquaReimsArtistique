<?php

namespace app\Controllers\Gestion;

use app\Attributes\Route;
use app\Controllers\AbstractController;
use app\Models\MailTemplate;
use app\Repository\MailTemplateRepository;

#[Route('/gestion/mail_templates', name: 'app_gestion_mail_templates')]
class MailTemplatesController extends AbstractController
{
    private MailTemplateRepository $mailTemplateRepository;

    public function __construct()
    {
        parent::__construct(false);
        $this->mailTemplateRepository = new MailTemplateRepository();
    }

    public function index(): void
    {
        $templates = $this->mailTemplateRepository->findAll();
        $this->render('/gestion/mail_templates', [
            'templates' => $templates,
            'currentUser' => $_SESSION['user'] ?? null
        ], 'Gestion des templates de mails');
    }


    #[Route('/gestion/mail_templates/add', name: 'app_gestion_mail_templates_add')]
    public function add(): void
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['code']) && !empty($_POST['subject'])) {
            $code = trim($_POST['code']);
            $subject = trim($_POST['subject']);
            if ($this->mailTemplateRepository->findByCode($code)) {
                $_SESSION['flash_message'] = [
                    'type' => 'danger',
                    'message' => 'Ce code existe déjà.'
                ];
            } else {
				$mailTemplate = new MailTemplate();
				$mailTemplate->setCode($_POST['code'] ?? '')
					->setSubject($_POST['subject'] ?? '');
				$this->mailTemplateRepository->insert($mailTemplate);
				$_SESSION['flash_message'] = ['type' => 'success', 'message' => 'Le template a été ajouté.'];
				header('Location: /gestion/mail_templates');
				exit;
            }
        } else {
            $_SESSION['flash_message'] = [
                'type' => 'danger',
                'message' => 'Veuillez remplir tous les champs.'
            ];
        }
        header('Location: /gestion/mail_templates');
        exit;
    }

    #[Route('/gestion/mail_templates/edit', name: 'app_gestion_mail_templates_edit')]
    public function edit(): void
    {
        if (
            $_SERVER['REQUEST_METHOD'] === 'POST'
            && !empty($_POST['id'])
            && !empty($_POST['subject'])
        ) {
            $id = (int)$_POST['id'];
            $subject = trim($_POST['subject']);
            $body_html = $_POST['body_html'] ?? null;
            $body_text = $_POST['body_text'] ?? null;

            $this->mailTemplateRepository->updateTemplate($id, $subject, $body_html, $body_text);
            $_SESSION['flash_message'] = [
                'type' => 'success',
                'message' => 'Le template a été modifié.'
            ];
        } else {
            $_SESSION['flash_message'] = [
                'type' => 'danger',
                'message' => 'Veuillez remplir tous les champs obligatoires.'
            ];
        }
        header('Location: /gestion/mail_templates');
        exit;
    }
    #[Route('/gestion/mail_templates/delete', name: 'app_gestion_mail_templates_delete')]
    public function delete(): void
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id'])) {
            $id = (int)$_POST['id'];
            $this->mailTemplateRepository->deleteById($id);
            $_SESSION['flash_message'] = [
                'type' => 'success',
                'message' => 'Le template a été supprimé avec succès.'
            ];
        } else {
            $_SESSION['flash_message'] = [
                'type' => 'danger',
                'message' => 'Une erreur est survenue lors de la suppression.'
            ];
        }
        header('Location: /gestion/mail_templates');
        exit;
    }
}
