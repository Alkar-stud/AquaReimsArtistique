<?php

namespace app\Services\Reservation;

use app\Repository\Reservation\ReservationRepository;

class ReservationQueryService
{
    private ReservationRepository $reservationRepository;
    public function __construct(
        ReservationRepository $reservationRepository,
    )
    {
        $this->reservationRepository = $reservationRepository;
    }

    /**
     * Compte le nombre de réservations actives pour un événement
     * @param int $eventId L'ID de l'événement
     * @param int|null $swimmerId Si fourni, compte uniquement les réservations pour cette nageuse.
     * @return int
     */
    public function countActiveReservationsForThisEventAndThisSwimmer(int $eventId, ?int $swimmerId = null): int
    {
        return $this->reservationRepository->countReservationsForSwimmer($eventId, $swimmerId);



    }

}