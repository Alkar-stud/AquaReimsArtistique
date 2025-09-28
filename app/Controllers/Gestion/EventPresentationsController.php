<?php

namespace app\Controllers\Gestion;

use app\Attributes\Route;
use app\Controllers\AbstractController;
use app\Models\Event\EventPresentations;
use app\Repository\Event\EventPresentationsRepository;
use app\Repository\Event\EventRepository;
use app\Repository\Event\EventSessionRepository;
use app\Services\DataValidation\EventPresentationDataValidationService;
use app\Services\UploadService;
use DateTime;
use Exception;
use Throwable;

class EventPresentationsController extends AbstractController
{
    private EventPresentationsRepository $eventPresentationsRepository;
    private EventRepository $eventRepository;
    private EventSessionRepository $eventSessionRepository;
    private UploadService $uploadService;
    private EventPresentationDataValidationService $eventPresentationDataValidationService;

    public function __construct(
        EventPresentationsRepository $eventPresentationsRepository,
        EventRepository $eventRepository,
        EventSessionRepository $eventSessionRepository,
        UploadService $uploadService,
        EventPresentationDataValidationService $eventPresentationDataValidationService
    ) {
        parent::__construct(false);
        $this->eventPresentationsRepository = $eventPresentationsRepository;
        $this->eventRepository = $eventRepository;
        $this->eventSessionRepository = $eventSessionRepository;
        $this->uploadService = $uploadService;
        $this->eventPresentationDataValidationService = $eventPresentationDataValidationService;
        $this->checkIfCurrentUserIsAllowedToManagedThis(2);
    }

    #[Route('/gestion/accueil', name: 'app_gestion_accueil', methods: ['GET'])]
    public function index(string $search = 'displayed'): void
    {
        if ($search === 'displayed') {
            $eventPresentations = $this->eventPresentationsRepository->findFuturePresentations(true);
        } else {
            $eventPresentations = $this->eventPresentationsRepository->findAll(true);
        }

        $events = $this->eventRepository->findAll();
        $eventSessions = $this->eventSessionRepository->findAllLastSessionDateByEvent();

        $this->render('/gestion/event_presentations', [
            'accueil' => $eventPresentations,
            'events' => $events,
            'eventSessions' => $eventSessions,
            'searchParam' => $search
        ], "Gestion de la page d'accueil");
    }

    #[Route('/gestion/accueil/upload', name: 'app_gestion_accueil_upload')]
    public function uploadImage(): void
    {
        //On vérifie que le CurrentUser a bien le droit de faire ça
        $this->checkIfCurrentUserIsAllowedToManagedThis(2, 'accueil');

        if (!isset($_FILES['upload'])) {
            parent::json(['error' => ['message' => 'Aucun fichier envoyé.']], 400);
            return;
        }

        try {
            $displayUntil = $_GET['displayUntil'] ?? null;
            $url = $this->uploadService->handleImageUpload($_FILES['upload'], $displayUntil);

            // On génère un nouveau token pour le formulaire qui a initié la requête
            $newCsrfToken = $this->csrfService->getToken($this->getCsrfContext());

            parent::json(['url' => $url, 'csrfToken' => $newCsrfToken]);
        } catch (Exception $e) {
            error_log('CKEditor Upload Error: ' . $e->getMessage()); // Log pour le debug
            parent::json(['error' => ['message' => "Erreur lors de l'upload: " . $e->getMessage()]], 500);
        }
    }

    #[Route('/gestion/accueil/add', name: 'app_gestion_accueil_add', methods: ['POST'])]
    public function add(): void
    {
        //On vérifie que le CurrentUser a bien le droit de faire ça
        $this->checkIfCurrentUserIsAllowedToManagedThis(2, 'accueil');

        try {
            // Valider les données
            $error = $this->eventPresentationDataValidationService->checkData($_POST);
            if ($error) {
                throw new Exception($error);
            }

            // Créer l'objet EventPresentations avec les données validées
            $eventPresentations = (new EventPresentations())
                ->setEventId($this->eventPresentationDataValidationService->getEventId())
                ->setDisplayUntil($this->eventPresentationDataValidationService->getDisplayUntil()->format('Y-m-d H:i:s'))
                ->setContent($this->eventPresentationDataValidationService->getContent())
                ->setIsDisplayed($this->eventPresentationDataValidationService->isDisplayed());

            // Insérer en base de données
            $this->eventPresentationsRepository->insert($eventPresentations);
            $this->flashMessageService->setFlashMessage('success', "Le contenu a été ajouté avec succès.");

        } catch (Throwable $e) {
            $this->flashMessageService->setFlashMessage('danger', $e->getMessage());
        }
        $this->redirect('/gestion/accueil');
    }

    #[Route('/gestion/accueil/toggle-status', name: 'app_gestion_accueil_toggle_status', methods: ['POST'])]
    public function toggleStatus(): void
    {
        // On s'attend à recevoir du JSON
        $data = json_decode(file_get_contents('php://input'), true);

        if (!isset($data['id'], $data['status'])) {
            $this->json(['success' => false, 'message' => 'Données invalides.']);
            return;
        }

        try {
            $this->eventPresentationsRepository->updateStatus((int)$data['id'], (bool)$data['status']);
            $this->flashMessageService->setFlashMessage('success', "Le statut a été mis à jour avec succès.");
            // On génère et renvoie un nouveau token pour maintenir la session sécurisée
            $newCsrfToken = $this->csrfService->getToken($this->getCsrfContext());

            parent::json(['success' => true, 'csrfToken' => $newCsrfToken]);
        } catch (Exception $e) {
            error_log("Erreur lors de la mise à jour du statut : " . $e->getMessage());
            $this->json(['success' => false, 'message' => 'Erreur serveur.'], 500);
        }
    }

    #[Route('/gestion/accueil/edit', name: 'app_gestion_accueil_edit', methods: ['POST'])]
    public function edit(): void
    {
        //On vérifie que le CurrentUser a bien le droit de faire ça
        $this->checkIfCurrentUserIsAllowedToManagedThis(2, 'accueil');

        $eventPresentationsId = (int)($_POST['id'] ?? 0);
        $eventPresentations = $this->eventPresentationsRepository->findById($eventPresentationsId);

        if (!$eventPresentations) {
            $this->flashMessageService->setFlashMessage('danger', "Présentation non trouvée.");
            $this->redirect('/gestion/accueil');
        }

        try {
            // Valider les données
            $error = $this->eventPresentationDataValidationService->checkData($_POST);
            if ($error) {
                throw new Exception($error);
            }

            // On met à jour l'objet Event existant avec les nouvelles données validées
            $eventPresentations->setEventId($this->eventPresentationDataValidationService->getEventId());
            $eventPresentations->setDisplayUntil($this->eventPresentationDataValidationService->getDisplayUntil()->format('Y-m-d H:i:s'));
            $eventPresentations->setContent($this->eventPresentationDataValidationService->getContent());
            $eventPresentations->setIsDisplayed($this->eventPresentationDataValidationService->isDisplayed());

            // Insérer en base de données
            $this->eventPresentationsRepository->update($eventPresentations);
            $this->flashMessageService->setFlashMessage('success', "Le contenu a été modifié avec succès.");

        } catch (Throwable $e) {
            $this->flashMessageService->setFlashMessage('danger', $e->getMessage());
        }
        $this->redirect('/gestion/accueil');
    }

    #[Route('/gestion/accueil/delete', name: 'app_gestion_accueil_delete', methods: ['POST'])]
    public function delete(): void
    {
        //On vérifie que le CurrentUser a bien le droit de faire ça
        $this->checkIfCurrentUserIsAllowedToManagedThis(2, 'accueil');

        $eventPresentationsId = (int)($_POST['id'] ?? 0);
        $eventPresentations = $this->eventPresentationsRepository->findById($eventPresentationsId);

        if (!$eventPresentations) {
            $this->flashMessageService->setFlashMessage('danger', "Présentation non trouvée.");
            $this->redirect('/gestion/accueil');
        }

        $this->eventPresentationsRepository->delete($eventPresentationsId);
        $this->flashMessageService->setFlashMessage('success', "Le contenu a été supprimé avec succès.");
        $this->redirect('/gestion/accueil');
    }

}