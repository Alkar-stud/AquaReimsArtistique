<?php
namespace app\Controllers\Gestion;

use app\Attributes\Route;
use app\Controllers\AbstractController;
use app\Repository\Event\EventInscriptionDateRepository;
use app\Repository\Event\EventRepository;
use app\Repository\Event\EventSessionRepository;
use app\Repository\Event\EventTarifRepository;
use app\Services\Event\EventService;
use app\Services\DataValidation\EventDataValidationService;
use DateTime;

#[Route('/gestion/events', name: 'app_gestion_events')]
class EventController extends AbstractController
{
    private EventService $eventService;

    public function __construct()
    {
        parent::__construct(false);
        $this->eventService = new EventService();
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
        $allEvents = $this->eventService->getAllEventsWithRelations();

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
        $allPiscines = $this->eventService->getAllPiscines();
        $allActiveTarifs = $this->eventService->getAllActiveTarifs();

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
        $this->checkIfCurrentUserIsAllowedToManagedThis(1, 'events');

        $validationService = new EventDataValidationService();
        $error = $validationService->checkData($_POST);

        if ($error) {
            $this->flashMessageService->setFlashMessage('danger', $error);
            $this->redirect('/gestion/events');
        }

        // Récupération des données validées
        $event = $validationService->getValidatedEvent();
        $tarifIds = $validationService->getValidatedTarifs();
        $sessions = $validationService->getValidatedSessions();
        $inscriptionDates = $validationService->getValidatedInscriptionDates();

        // Repositories
        $eventRepository = new EventRepository();
        $eventTarifRepository = new EventTarifRepository();
        $sessionRepository = new EventSessionRepository();
        $inscriptionDateRepository = new EventInscriptionDateRepository();

        // Début de la transaction pour tout insérer
        $eventRepository->beginTransaction();
        try {
            // Insérer l'événement principal pour obtenir son ID
            $eventId = $eventRepository->insert($event);
            if ($eventId === 0) {
                throw new \Exception("La création de l'événement a échoué.");
            }

            // Lier les tarifs à l'événement
            $eventTarifRepository->replaceForEvent($eventId, $tarifIds);

            // Insérer les séances en liant l'ID de l'événement
            foreach ($sessions as $session) {
                $session->setEventId($eventId);
                $sessionRepository->insert($session);
            }

            // Insérer les périodes d'inscription en liant l'ID de l'événement
            foreach ($inscriptionDates as $inscriptionDate) {
                $inscriptionDate->setEventId($eventId);
                $inscriptionDateRepository->insert($inscriptionDate);
            }

            $eventRepository->commit();
            $this->flashMessageService->setFlashMessage('success', "L'événement '{$event->getName()}' a été ajouté avec succès.");
        } catch (\Throwable $e) {
            $eventRepository->rollBack();
            $this->flashMessageService->setFlashMessage('danger', "Une erreur est survenue lors de l'ajout de l'événement : " . $e->getMessage());
        }

        $this->redirect('/gestion/events');
    }


}