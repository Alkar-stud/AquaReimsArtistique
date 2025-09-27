<?php

namespace app\Services\Event;

use app\Repository\Event\EventInscriptionDateRepository;
use app\Repository\Event\EventPresentationsRepository;
use app\Repository\Event\EventRepository;
use app\Repository\Event\EventSessionRepository;
use app\Repository\Event\EventTarifRepository;
use app\Services\DataValidation\EventDataValidationService;
use Exception;
use Throwable;

class EventCreateService
{
    private EventRepository $eventRepository;
    private EventInscriptionDateRepository $eventInscriptionDateRepository;
    private EventPresentationsRepository $eventPresentationsRepository;
    private EventSessionRepository $eventSessionRepository;
    private EventTarifRepository $eventTarifRepository;
    private EventDataValidationService $eventDataValidationService;

    public function __construct(
        EventRepository $eventRepository,
        EventInscriptionDateRepository $eventInscriptionDateRepository,
        EventPresentationsRepository $eventPresentationsRepository,
        EventSessionRepository $eventSessionRepository,
        EventTarifRepository $eventTarifRepository,
        EventDataValidationService $eventDataValidationService,
    ) {
        $this->eventRepository = $eventRepository;
        $this->eventInscriptionDateRepository = $eventInscriptionDateRepository;
        $this->eventPresentationsRepository = $eventPresentationsRepository;
        $this->eventSessionRepository = $eventSessionRepository;
        $this->eventTarifRepository = $eventTarifRepository;
        $this->eventDataValidationService = $eventDataValidationService;
    }

    /**
     * @throws Exception
     */
    public function createEvent($data): int
    {
        $error = $this->eventDataValidationService->checkData($data);
        if ($error) {
            throw new Exception($error);
        }

        // Récupération des données validées
        $event = $this->eventDataValidationService->getValidatedEvent();
        $tarifIds = $this->eventDataValidationService->getValidatedTarifs();
        $sessions = $this->eventDataValidationService->getValidatedSessions();
        $inscriptionDates = $this->eventDataValidationService->getValidatedInscriptionDates();

        // Début de la transaction pour tout insérer
        $this->eventRepository->beginTransaction();
        try {
            // Insérer l'événement principal pour obtenir son ID
            $eventId = $this->eventRepository->insert($event);
            if ($eventId === 0) {
                throw new Exception("La création de l'événement a échoué.");
            }

            // Lier les tarifs à l'événement
            $this->eventTarifRepository->replaceForEvent($eventId, $tarifIds);

            // Insérer les séances en liant l'ID de l'événement
            foreach ($sessions as $session) {
                $session->setEventId($eventId);
                $this->eventSessionRepository->insert($session);
            }

            // Insérer les périodes d'inscription en liant l'ID de l'événement
            foreach ($inscriptionDates as $inscriptionDate) {
                $inscriptionDate->setEventId($eventId);
                $this->eventInscriptionDateRepository->insert($inscriptionDate);
            }

            $this->eventRepository->commit();
            return $eventId;
        } catch (Throwable $e) {
            $this->eventRepository->rollBack();
            // On relance l'exception pour que le contrôleur puisse la gérer.
            throw new Exception("Une erreur est survenue lors de l'ajout de l'événement : " . $e->getMessage(), 0, $e);
        }
    }

    public function createPresentationForEVent($eventId): array
    {
        return ['messageType' => 'info', 'message' => "Ceci n'est pas encore implémenté."];
    }

}