<?php

namespace app\Services\Event;

use app\Repository\Event\EventInscriptionDateRepository;
use app\Repository\Event\EventPresentationsRepository;
use app\Repository\Event\EventRepository;
use app\Repository\Event\EventSessionRepository;
use app\Repository\Event\EventTarifRepository;
use app\Repository\Reservation\ReservationRepository;
use app\Services\Reservation\ReservationDeletionService;
use Exception;
use Throwable;

class EventDeletionService
{
    private EventRepository $eventRepository;
    private EventInscriptionDateRepository $eventInscriptionDateRepository;
    private EventPresentationsRepository $eventPresentationsRepository;
    private EventSessionRepository $eventSessionRepository;
    private EventTarifRepository $eventTarifRepository;
    private ReservationRepository $reservationRepository;
    private ReservationDeletionService $reservationDeletionService;

    public function __construct(
        EventRepository $eventRepository,
        EventInscriptionDateRepository $eventInscriptionDateRepository,
        EventPresentationsRepository $eventPresentationsRepository,
        EventSessionRepository $eventSessionRepository,
        EventTarifRepository $eventTarifRepository,
        ReservationRepository $reservationRepository,
        ReservationDeletionService $reservationDeletionService
    ) {
        $this->eventRepository = $eventRepository;
        $this->eventInscriptionDateRepository = $eventInscriptionDateRepository;
        $this->eventPresentationsRepository = $eventPresentationsRepository;
        $this->eventSessionRepository = $eventSessionRepository;
        $this->eventTarifRepository = $eventTarifRepository;
        $this->reservationRepository = $reservationRepository;
        $this->reservationDeletionService = $reservationDeletionService;
    }


    /**
     * Supprime un Event et tout ce qui y est associé (session, date inscription, présentation, tarifs, réservations)
     *
     * @param $eventId
     * @return void
     * @throws Throwable
     */
    public function deleteEvent($eventId): void
    {
        // Début de la transaction pour tout mettre à jour
        $this->eventRepository->beginTransaction();
        try {
            //On vérifie s'il y a déjà des réservations non annulées, dans ce cas, on bloque la suppression.
            if ($this->reservationRepository->hasReservations($eventId)) {
                throw new Exception("Suppression impossible, car des réservations actives existent pour cet événement.");
            }
            // Récupération de la liste des réservations annulées à supprimer
            $canceledReservationsCanBeDeleted = $this->reservationRepository->findCanceledByEvent($eventId);
            //On demande la suppression pour toutes et de tout ce qui y est rattaché
            foreach($canceledReservationsCanBeDeleted as $reservation)
            {
                $this->reservationDeletionService->deleteReservation($reservation->getId());
            }
            //On détache tous les tarifs
            $this->eventTarifRepository->detachAllForEvent($eventId);
            //On supprime toutes les dates d'inscription
            $this->eventInscriptionDateRepository->deleteAllForEvent($eventId);
            //On supprime toutes les sessions
            $this->eventSessionRepository->deleteAllForEvent($eventId);
            //On supprime toutes les présentations
            $this->eventPresentationsRepository->deleteAllForEvent($eventId);
            //On supprime l'événement
            $this->eventRepository->delete($eventId);
            //Fin de la transaction
            $this->eventRepository->commit();

        } catch (Throwable $e) {
            $this->eventRepository->rollBack();
            throw $e;
        }
    }

}