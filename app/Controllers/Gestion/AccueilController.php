<?php

namespace app\Controllers\Gestion;

use app\Attributes\Route;
use app\Controllers\AbstractController;
use app\Models\Accueil;
use app\Repository\AccueilRepository;
use app\Repository\Event\EventsRepository;
use app\Repository\Event\EventSessionRepository;
use app\Services\UploadService;
use Exception;

class AccueilController extends AbstractController
{
    private AccueilRepository $repository;
    private EventsRepository $eventsRepository;
    private EventSessionRepository $eventSessionRepository;

    function __construct()
    {
        parent::__construct(false);
        $this->repository = new AccueilRepository();
        $this->eventsRepository = new EventsRepository();
        $this->eventSessionRepository = new EventSessionRepository();
    }

    #[Route('/gestion/accueil', name: 'app_gestion_accueil')]
    public function index(?string $search = null): void
    {
        $accueil = $this->repository->findDisplayed(false);
        //Récupération de la liste des events à venir pour la liste déroulante
        $events = $this->eventsRepository->findUpcoming();

        // Récupération des dernières sessions pour chaque événement
        $eventSessions = [];
        foreach ($events as $event) {
            $lastSession = $this->eventSessionRepository->findLastSessionByEventId($event->getId());
            if ($lastSession) {
                $eventSessions[$event->getId()] = $lastSession['event_start_at'];
            }
        }

        // Récupérer le message flash s'il existe
        $flashMessage = $this->flashMessageService->getFlashMessage();
        $this->flashMessageService->unsetFlashMessage();

        $this->render('/gestion/accueil', [
            'accueil' => $accueil,
            'events' => $events,
            'eventSessions' => $eventSessions,
            'searchParam' => 'displayed',
            'flash_message' => $flashMessage,
            'js_data' => [
                'eventSessions' => $eventSessions,
                'delaiToDisplay' => 1
            ]
        ], "Gestion de la page d'accueil");
    }

    #[Route('/gestion/accueil/list/{search}', name: 'app_gestion_accueil_list')]
    public function list(?string $search = null): void
    {
        // Si le paramètre est 'displayed' (ou manquant), on affiche les contenus actifs.
        // Sinon, si c'est '0', on affiche tout.
        if ($search === '0') {
            $accueil = $this->repository->findAll();
        } else {
            $accueil = $this->repository->findDisplayed(false);
        }
        // Récupération de la liste des events à venir pour la liste déroulante
        $events = $this->eventsRepository->findUpcoming();

        // Récupération des dernières sessions pour chaque événement
        $eventSessions = [];
        foreach ($events as $event) {
            $lastSession = $this->eventSessionRepository->findLastSessionByEventId($event->getId());
            if ($lastSession) {
                $eventSessions[$event->getId()] = $lastSession['event_start_at'];
            }
        }

        // Récupérer le message flash s'il existe
        $flashMessage = $this->flashMessageService->getFlashMessage();
        $this->flashMessageService->unsetFlashMessage();

        $this->render('/gestion/accueil', [
            'accueil' => $accueil,
            'events' => $events,
            'eventSessions' => $eventSessions,
            'searchParam' => $search,
            'flash_message' => $flashMessage,
            'js_data' => [
                'eventSessions' => $eventSessions,
                'delaiToDisplay' => 1
            ]
        ], "Gestion de la page d'accueil");
    }

    #[Route('/gestion/accueil/add', name: 'app_gestion_accueil_add', methods: ['POST'])]
    public function add(): void
    {
        if (isset($_POST['display_until'], $_POST['content'], $_POST['event'])) {
            $accueil = new Accueil();
            $accueil->setEvent((int)$_POST['event'])
                ->setDisplayUntil($_POST['display_until'])
                ->setContent($_POST['content'])
                ->setIsdisplayed(isset($_POST['is_displayed']))
                ->setCreatedAt(date('Y-m-d H:i:s'));

            $this->repository->insert($accueil);
            $this->flashMessageService->setFlashMessage('success', "Le contenu a été ajouté avec succès.");
        } else {
            $this->flashMessageService->setFlashMessage('danger', "Données manquantes pour l'ajout.");
        }
        header('Location: /gestion/accueil');
        exit();
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
            $this->repository->updateStatus((int)$data['id'], (bool)$data['status']);
            $this->flashMessageService->setFlashMessage('success', "Le statut a été mis à jour avec succès.");
            $this->json(['success' => true]);
        } catch (Exception $e) {
            error_log("Erreur lors de la mise à jour du statut : " . $e->getMessage());
            $this->flashMessageService->setFlashMessage('danger', "Une erreur est survenue lors de la mise à jour du statut.");
            $this->json(['success' => false, 'message' => 'Erreur serveur.']);
        }
    }

    #[Route('/gestion/accueil/edit', name: 'app_gestion_accueil_edit', methods: ['POST'])]
    public function edit(): void
    {
        if (isset($_POST['id'], $_POST['display_until'], $_POST['content'])) {
            $id = (int)$_POST['id'];
            $accueil = $this->repository->findById($id);

            if (!$accueil) {
                $this->flashMessageService->setFlashMessage('danger', "Le contenu à modifier n'a pas été trouvé.");
            } else {
                $accueil->setEvent($_POST['event'])
                    ->setDisplayUntil($_POST['display_until'])
                    ->setContent($_POST['content'])
                    ->setIsdisplayed(isset($_POST['is_displayed']));

                $this->repository->update($accueil);
                $this->flashMessageService->setFlashMessage('success', "Le contenu a été modifié avec succès.");
            }
        } else {
            $this->flashMessageService->setFlashMessage('danger', "Données manquantes pour la modification.");
        }
        header('Location: /gestion/accueil');
        exit();
    }


    #[Route('/gestion/accueil/upload', name: 'app_gestion_accueil_upload')]
    public function uploadImage(): void
    {
        header('Content-Type: application/json');

        // Sécurité : Vérifier que l'utilisateur est connecté et a les droits
        if (!isset($_SESSION['user']['role']) || $_SESSION['user']['role']['level'] > 2) {
            http_response_code(403);
            echo json_encode(['error' => ['message' => 'Accès non autorisé.']]);
            return;
        }

        if (!isset($_FILES['upload'])) {
            http_response_code(400);
            echo json_encode(['error' => ['message' => 'Aucun fichier envoyé.']]);
            return;
        }

        // Vérification des erreurs de téléchargement PHP
        if ($_FILES['upload']['error'] !== UPLOAD_ERR_OK) {
            $uploadErrors = [
                UPLOAD_ERR_INI_SIZE => 'Le fichier dépasse la taille maximale autorisée par PHP (upload_max_filesize).',
                UPLOAD_ERR_FORM_SIZE => 'Le fichier dépasse la taille maximale autorisée par le formulaire.',
                UPLOAD_ERR_PARTIAL => 'Le fichier n\'a été que partiellement téléchargé.',
                UPLOAD_ERR_NO_FILE => 'Aucun fichier n\'a été téléchargé.',
                UPLOAD_ERR_NO_TMP_DIR => 'Dossier temporaire manquant sur le serveur.',
                UPLOAD_ERR_CANT_WRITE => 'Impossible d\'écrire le fichier sur le disque.',
                UPLOAD_ERR_EXTENSION => 'Une extension PHP a arrêté le téléchargement.'
            ];

            $errorMessage = $uploadErrors[$_FILES['upload']['error']] ?? 'Erreur inconnue lors du téléchargement.';
            http_response_code(500);
            echo json_encode(['error' => [
                'message' => $errorMessage,
                'code' => $_FILES['upload']['error'],
                'file_info' => $_FILES['upload']
            ]]);
            return;
        }


        $uploadService = new UploadService();
        try {
            $displayUntil = $_GET['displayUntil'] ?? null;
            $url = $uploadService->handleImageUpload($_FILES['upload'], $displayUntil);
            echo json_encode(['url' => $url]);
        } catch (Exception $e) {
            http_response_code(500);
            error_log('CKEditor Upload Error: ' . $e->getMessage()); // Log pour le debug
            echo json_encode(['error' => ['message' => $e->getMessage()]]);
        }
    }

    #[Route('/gestion/accueil/delete/{id}', name: 'app_gestion_accueil_delete', methods: ['POST'])]
    public function delete(?int $id): void
    {
        if (isset($id)) {
            $accueil = $this->repository->findById($id);

            if (!$accueil) {
                $this->flashMessageService->setFlashMessage('warning', "Ce contenu à supprimer n'a pas été trouvé.");
            } else {
                $this->repository->delete($id);
                $this->flashMessageService->setFlashMessage('success', "Le contenu a été supprimé avec succès.");
            }
        } else {
            $this->flashMessageService->setFlashMessage('danger', "Données manquantes pour la suppression.");
        }
        header('Location: /gestion/accueil');
        exit();
    }

}