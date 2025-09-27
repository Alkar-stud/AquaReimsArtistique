<?php
namespace app\Controllers\Gestion;

use app\Attributes\Route;
use app\Controllers\AbstractController;
use app\Repository\Event\EventRepository;
use app\Services\Event\EventCreateService;
use app\Services\Event\EventDeletionService;
use app\Services\Event\EventQueryService;
use app\Services\Event\EventResult;
use app\Services\Event\EventUpdateService;
use DateTime;
use Exception;
use Throwable;

#[Route('/gestion/events', name: 'app_gestion_events')]
class EventController extends AbstractController
{
    private EventQueryService $eventQueryService;
    private EventRepository $eventRepository;
    private EventDeletionService $eventDeletionService;
    private EventCreateService $eventCreateService;
    private EventUpdateService $eventUpdateService;

    public function __construct(
        EventQueryService              $eventQueryService,
        EventRepository                $eventRepository,
        EventDeletionService           $eventDeletionService,
        EventCreateService             $eventCreateService,
        EventUpdateService             $eventUpdateService
    ) {
        parent::__construct(false);
        $this->eventQueryService = $eventQueryService;
        $this->eventRepository = $eventRepository;
        $this->eventDeletionService = $eventDeletionService;
        $this->eventCreateService = $eventCreateService;
        $this->eventUpdateService = $eventUpdateService;
    }

    /**
     * Affiche la page de gestion des événements.
     *
     * Cette méthode récupère tous les événements avec leurs relations,
     * les sépare en deux listes (événements à venir et événements passés),
     * puis les transmet à la vue pour affichage.
     */
    public function index(): void
    {
        // On récupère tous les événements et leurs données associées (sessions, tarifs, etc.) via le service.
        $allEvents = $this->eventQueryService->getAllEventsWithRelations();

        // On prépare les listes pour séparer les événements à venir des événements passés.
        $eventsUpcoming = [];
        $eventsPast = [];
        $now = new DateTime();

        // On parcourt chaque événement pour le classer.
        foreach ($allEvents as $event) {

            $sessions = $event->getSessions();

            // Cas où un événement n'a pas encore de session définie.
            if (empty($sessions)) {
                // On le considère comme "à venir" par défaut pour qu'il reste visible et gérable.
                $eventsUpcoming[] = $event;
                continue;
            }

            // Pour déterminer si un événement est "passé", on se base sur la date de sa dernière session.
            // On trouve la date de session la plus tardive.
            $lastSessionDate = array_reduce($sessions, function ($latest, $session) {
                return $latest === null || $session->getEventStartAt() > $latest ? $session->getEventStartAt() : $latest;
            });

            // On classe ensuite l'événement en fonction de la date de sa dernière session.
            if ($lastSessionDate < $now) {
                $eventsPast[] = $event;
            } else {
                $eventsUpcoming[] = $event;
            }
        }

        // On récupère les listes complètes des piscines et des tarifs actifs pour les formulaires.
        $allPiscines = $this->eventQueryService->getAllPiscines();
        $allActiveTarifs = $this->eventQueryService->getAllActiveTarifs();

        // On envoie les deux listes d'événements (et les autres données nécessaires) à la vue.
        $this->render('/gestion/events', [
            'eventsUpcoming' => $eventsUpcoming,
            'eventsPast' => $eventsPast,
            'allPiscines' => $allPiscines,
            'allActiveTarifs' => $allActiveTarifs
        ], 'Gestion des événements');
    }


    #[Route('/gestion/events/add', name: 'app_gestion_events_add', methods: ['POST'])]
    public function add(): void
    {
        //On vérifie que le CurrentUser a bien le droit de faire ça
        $this->checkIfCurrentUserIsAllowedToManagedThis(2, 'events');

        try {
            // On demande la création de l'événement
            $this->eventCreateService->createEvent($_POST);
            // Si tout s'est bien passé, on affiche le message de succès.
            $this->flashMessageService->setFlashMessage('success', "L'événement a été ajouté avec succès.");
        } catch (Throwable $e) {
            // Si une exception est lancée, on l'attrape et on affiche son message.
            $this->flashMessageService->setFlashMessage('danger', $e->getMessage());
        }
        //Et on renvoie à l'index pour afficher la vue
        $this->redirect('/gestion/events');
    }

    #[Route('/gestion/events/update', name: 'app_gestion_events_update')]
    public function update(): void
    {
        //On vérifie que le CurrentUser a bien le droit de faire ça
        $this->checkIfCurrentUserIsAllowedToManagedThis(2, 'events');

        try {
            $result = $this->eventUpdateService->updateEvent($_POST);

            if ($result->getStatus() === EventResult::SUCCESS) {
                $this->flashMessageService->setFlashMessage('success', "L'événement '{$result->getEventName()}' a été modifié avec succès.");
            } else { // PARTIAL_SUCCESS
                $sessionNames = implode(', ', $result->getUndeletableSessionNames());
                $this->flashMessageService->setFlashMessage('warning', "L'événement '{$result->getEventName()}' a été modifié, mais les séances suivantes n'ont pas pu être supprimées car elles contiennent des réservations : " . $sessionNames . ".");
            }

        } catch (Throwable $e) {
            // Si une exception est lancée, on l'attrape et on affiche son message.
            $this->flashMessageService->setFlashMessage('danger', $e->getMessage());
        }
        //Et on renvoie à l'index pour afficher la vue
        $this->redirect('/gestion/events');

    }

    #[Route('/gestion/events/delete', name: 'app_gestion_events_delete')]
    public function delete(): void
    {
        //On vérifie que le CurrentUser a bien le droit de faire ça
        $this->checkIfCurrentUserIsAllowedToManagedThis(2, 'events');

        //On récupère l'event
        $eventId = (int)($_POST['event_id'] ?? 0);
        $event = $this->eventRepository->findById($eventId);

        if (!$event) {
            $this->flashMessageService->setFlashMessage('danger', 'Événement non trouvé.');
            $this->redirect('/gestion/events');
        }

        //On supprime l'event
        try {
            $this->eventDeletionService->deleteEvent($event->getid());
            $this->flashMessageService->setFlashMessage('success', "L'événement '{$event->getName()}' a été supprimé avec succès.");
        } catch (Throwable $e) {
            // Le message de l'exception est maintenant assez clair pour être affiché directement.
            $this->flashMessageService->setFlashMessage('danger', $e->getMessage());
        }

        $this->redirect('/gestion/events');
    }



}