<?php

namespace app\Services\Event;

use app\Repository\Event\EventInscriptionDateRepository;
use app\Repository\Event\EventPresentationsRepository;
use app\Repository\Event\EventRepository;
use app\Repository\Event\EventSessionRepository;
use app\Repository\Event\EventTarifRepository;
use app\Repository\Reservation\ReservationRepository;
use app\Services\DataValidation\EventDataValidationService;
use Exception;
use Throwable;

class EventUpdateService
{
    private EventRepository $eventRepository;
    private EventInscriptionDateRepository $eventInscriptionDateRepository;
    private EventSessionRepository $eventSessionRepository;
    private EventTarifRepository $eventTarifRepository;
    private EventDataValidationService $eventDataValidationService;
    private ReservationRepository $reservationRepository;

    public function __construct(
        EventRepository $eventRepository,
        EventInscriptionDateRepository $eventInscriptionDateRepository,
        EventSessionRepository $eventSessionRepository,
        EventTarifRepository $eventTarifRepository,
        EventDataValidationService $eventDataValidationService,
        ReservationRepository $reservationRepository
    ) {
        $this->eventRepository = $eventRepository;
        $this->eventInscriptionDateRepository = $eventInscriptionDateRepository;
        $this->eventSessionRepository = $eventSessionRepository;
        $this->eventTarifRepository = $eventTarifRepository;
        $this->eventDataValidationService = $eventDataValidationService;
        $this->reservationRepository = $reservationRepository;
    }

    /**
     * @throws Exception
     */
    public function updateEvent($data): EventResult
    {
        //On récupère l'event
        $eventId = (int)($data['event_id'] ?? 0);
        $event = $this->eventRepository->findById($eventId, true, true, true, true, true);

        if (!$event) {
            throw new Exception("Événement non trouvé.");
        }

        $error = $this->eventDataValidationService->checkData($_POST);
        if ($error) {
            throw new Exception($error);
        }

        // Récupération des données validées
        $validatedEventData = $this->eventDataValidationService->getValidatedEvent();
        $tarifIds = $this->eventDataValidationService->getValidatedTarifs();
        $sessions = $this->eventDataValidationService->getValidatedSessions();
        $inscriptionDates = $this->eventDataValidationService->getValidatedInscriptionDates();

        // On met à jour l'objet Event existant avec les nouvelles données validées
        $event->setName($validatedEventData->getName());
        $event->setPlace($validatedEventData->getPlace());
        $event->setLimitationPerSwimmer($validatedEventData->getLimitationPerSwimmer());


        // Début de la transaction pour tout mettre à jour
        $this->eventRepository->beginTransaction();
        try {
            // Mettre à jour l'événement principal
            $this->eventRepository->update($event);

            // Remplacer les tarifs liés à l'événement
            $this->eventTarifRepository->replaceForEvent($event->getId(), $tarifIds);

            // --- GESTION DES SÉANCES ---
            $undeletableSessionNames = [];
            $existingSessions = $this->eventSessionRepository->findByEventId($event->getId());
            $existingSessionsMap = [];
            foreach ($existingSessions as $s) {
                $existingSessionsMap[$s->getId()] = $s;
            }
            $existingSessionIds = array_keys($existingSessionsMap);
            $submittedSessionIds = [];

            // Parcourir les séances soumises pour AJOUTER ou METTRE À JOUR
            foreach ($sessions as $index => $submittedSession) {
                $submittedSession->setEventId($event->getId());
                //$sessionId = (int)($_POST['sessions'][array_search($submittedSession, $sessions, true)]['id'] ?? 0);
                $sessionId = (int)($_POST['sessions'][$index]['id'] ?? 0);

                if ($sessionId > 0 && in_array($sessionId, $existingSessionIds, true)) {
                    // C'est une MISE À JOUR
                    $submittedSession->setId($sessionId);
                    $this->eventSessionRepository->update($submittedSession);
                    $submittedSessionIds[] = $sessionId;
                } else {
                    // C'est un AJOUT
                    $newId = $this->eventSessionRepository->insert($submittedSession);
                    $submittedSessionIds[] = $newId;
                }
            }

            // Parcourir les séances existantes pour trouver celles à SUPPRIMER
            $sessionsToDeleteIds = array_diff($existingSessionIds, $submittedSessionIds);
            foreach ($sessionsToDeleteIds as $sessionIdToDelete) {
                // Vérification s'il y a des réservations existantes à cette session
                if ($this->reservationRepository->hasReservationsForSession($sessionIdToDelete)) {
                    // On ne peut pas supprimer, on stocke le nom pour le message d'avertissement
                    $undeletableSessionNames[] = $existingSessionsMap[$sessionIdToDelete]->getSessionName();
                } else {
                    // Suppression sécurisée
                    $this->eventSessionRepository->delete($sessionIdToDelete);
                }
            }


            // Remplacer les périodes d'inscription (supprimer les anciennes, insérer les nouvelles)
            $this->eventInscriptionDateRepository->deleteAllForEvent($event->getId());
            foreach ($inscriptionDates as $inscriptionDate) {
                $inscriptionDate->setEventId($event->getId()); // Assurer que l'ID de l'événement est bien là
                $this->eventInscriptionDateRepository->insert($inscriptionDate);
            }

            // Finalisation
            $this->eventRepository->commit();

            if (empty($undeletableSessionNames)) {
                return new EventResult(EventResult::SUCCESS, $event->getName());
            } else {
                return new EventResult(EventResult::PARTIAL_SUCCESS, $event->getName(), $undeletableSessionNames);
            }

        } catch (Throwable $e) {
            $this->eventRepository->rollBack();
            throw new Exception("Une erreur est survenue lors de la modification de l'événement : " . $e->getMessage());
        }
    }
}