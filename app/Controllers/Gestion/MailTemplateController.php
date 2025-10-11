<?php

namespace app\Controllers\Gestion;

use app\Attributes\Route;
use app\Controllers\AbstractController;
use app\Models\Mail\MailTemplate;
use app\Repository\Mail\MailTemplateRepository;
use app\Services\DataValidation\MailTemplateDataValidationService;

class MailTemplateController extends AbstractController
{
    private MailTemplateRepository $mailTemplateRepository;
    private MailTemplate $mailTemplate;
    private MailTemplateDataValidationService $mailTemplateDataValidationService;

    public function __construct(
        MailTemplateRepository $mailTemplateRepository,
        MailTemplate $mailTemplate,
        MailTemplateDataValidationService $mailTemplateDataValidationService,
    )
    {
        parent::__construct(false);
        $this->mailTemplateRepository = $mailTemplateRepository;
        $this->mailTemplate = $mailTemplate;
        $this->mailTemplateDataValidationService = $mailTemplateDataValidationService;
    }

    #[Route('/gestion/mails_templates', name: 'app_gestion_mail_template')]
    public function index(): void
    {
        $templates = $this->mailTemplateRepository->findAll();
        $this->render('/gestion/mail_template', [
            'templates' => $templates,
            'currentUser' => $_SESSION['user'] ?? null,
        ], 'Gestion des templates de mails');
    }

    #[Route('/gestion/mails_templates/add', name: 'app_gestion_mail_templates_add', methods: ['POST'])]
    public function add(): void
    {
        //On vérifie que le CurrentUser a bien le droit de faire ça
        $this->checkIfCurrentUserIsAllowedToManagedThis(2, 'mails_templates');

        $errors = $this->mailTemplateDataValidationService->validateForAdd($_POST);

        // Unicité du code
        if (!$errors && $this->mailTemplateRepository->findByCode($this->mailTemplateDataValidationService->getCode() ?? '')) {
            $errors[] = 'Ce code existe déjà.';
        }

        if ($errors) {
            $this->flashMessageService->setFlashMessage('danger', implode(' ', $errors));
            $this->redirect('/gestion/mails_templates');
        }

        $this->mailTemplate
            ->setCode($this->mailTemplateDataValidationService->getCode() ?? '')
            ->setSubject($this->mailTemplateDataValidationService->getSubject() ?? '')
            ->setBodyHtml($this->mailTemplateDataValidationService->getBodyHtml())
            ->setBodyText($this->mailTemplateDataValidationService->getBodyText());

        $this->mailTemplateRepository->insert($this->mailTemplate);

        $this->flashMessageService->setFlashMessage('success', 'Le template a été ajouté.');
        $this->redirect('/gestion/mails_templates');
    }

    #[Route('/gestion/mails_templates/edit', name: 'app_gestion_mail_templates_edit', methods: ['POST'])]
    public function edit(): void
    {
        $this->checkIfCurrentUserIsAllowedToManagedThis(2, 'mails_templates');

        $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT) ?: 0;
        if ($id <= 0) {
            $this->flashMessageService->setFlashMessage('danger', 'Identifiant invalide.');
            $this->redirect('/gestion/mails_templates');
        }

        $errors = $this->mailTemplateDataValidationService->validateForEdit($_POST);
        if ($errors) {
            $this->flashMessageService->setFlashMessage('danger', implode(' ', $errors));
            $this->redirect('/gestion/mails_templates');
        }

        $template = $this->mailTemplateRepository->findById($id);
        if (!$template) {
            $this->flashMessageService->setFlashMessage('danger', 'Template introuvable.');
            $this->redirect('/gestion/mails_templates');
        }

        $template
            ->setSubject($this->mailTemplateDataValidationService->getSubject() ?? '')
            ->setBodyHtml($this->mailTemplateDataValidationService->getBodyHtml())
            ->setBodyText($this->mailTemplateDataValidationService->getBodyText());

        $this->mailTemplateRepository->update($template);

        $this->flashMessageService->setFlashMessage('success', 'Le template a été modifié.');
        $this->redirect('/gestion/mails_templates');
    }

    #[Route('/gestion/mails_templates/delete', name: 'app_gestion_mails_templates_delete')]
    public function delete(): void
    {
        $this->checkIfCurrentUserIsAllowedToManagedThis(2, 'mails_templates');

        $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT) ?: 0;
        if ($id <= 0) {
            $this->flashMessageService->setFlashMessage('danger', 'Identifiant invalide.');
            $this->redirect('/gestion/mails_templates');
        }

        if (!$this->mailTemplateRepository->delete($id)) {
            $this->flashMessageService->setFlashMessage('danger', 'Erreur lors de la suppression du template');
        } else {
            $this->flashMessageService->setFlashMessage('success', 'Le template a été supprimé.');
        }


        $this->redirect('/gestion/mails_templates');
    }


}