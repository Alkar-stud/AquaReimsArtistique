<?php

namespace app\Services\Event;

use app\Repository\Event\EventRepository;
use app\Repository\Reservation\ReservationRepository;
use Throwable;

class EventDeletionService
{
    private ReservationRepository $reservationRepository;
    private EventRepository $eventRepository;
    public function __construct()
    {
        $this->reservationRepository = new ReservationRepository();
        $this->eventRepository = new EventRepository();
    }

    public function deleteEvent($eventId): bool
    {
        // Début de la transaction pour tout mettre à jour
        $this->eventRepository->beginTransaction();
        try {
            //On vérifie s'il y a déjà des réservations non annulées, dans ce cas, on bloque la suppression.
            if ($this->reservationRepository->hasReservations($eventId)) {
                throw new \Exception("Suppression impossible : des réservations actives existent.");
            }



        } catch (Throwable $e) {
            $this->eventRepository->rollBack();
        }



    }


}