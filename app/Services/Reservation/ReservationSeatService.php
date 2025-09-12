<?php

namespace app\Services\Reservation;

use app\Repository\Piscine\PiscineGradinsPlacesRepository;
use app\Repository\Reservation\ReservationsDetailsRepository;
use app\Repository\Reservation\ReservationsPlacesTempRepository;
use DateMalformedStringException;
use DateTime;

class ReservationSeatService
{
    private PiscineGradinsPlacesRepository $placesRepository;
    private ReservationsDetailsRepository $reservationsDetailsRepository;
    private ReservationsPlacesTempRepository $tempRepo;

    public function __construct()
    {
        $this->placesRepository = new PiscineGradinsPlacesRepository();
        $this->reservationsDetailsRepository = new ReservationsDetailsRepository();
        $this->tempRepo = new ReservationsPlacesTempRepository();
    }


    /**
     * Indique le statut de la place dans la session de l'event en paramètre
     *
     * @param int $seatId
     * @param int $eventSessionId
     * @return array
     * @throws DateMalformedStringException
     */
    public function seatStatus(int $seatId, int $eventSessionId): array
    {
        // Vérifier que la place existe et est ouverte
        // 1. La place existe-t-elle et est-elle ouverte à la réservation ?
        $place = $this->placesRepository->findById($seatId);
        if (!$place || !$place->isOpen()) {
            return ['success' => false, 'error' => "Cette place n'est plus disponible.", 'reason' => 'closed', 'seat_id' => $seatId];
        }

        // 2. La place est-elle déjà réservée de manière définitive ?
        $placesReservees = $this->reservationsDetailsRepository->findReservedSeatsForSession($eventSessionId);
        if (in_array($seatId, $placesReservees)) {
            return ['success' => false, 'error' => "Cette place vient d'être réservée.", 'reason' => 'taken_definitively', 'seat_id' => $seatId];
        }

        // 3. La place est-elle déjà en cours de réservation par un autre utilisateur ?
        // On supprime d'abord les sessions expirées pour un contrôle fiable.
        $this->tempRepo->deleteExpired((new DateTime())->format('Y-m-d H:i:s'));
        $tempSeats = $this->tempRepo->findByEventSession($eventSessionId);
        foreach ($tempSeats as $t) {
            if ($t->getPlaceId() == $seatId && $t->getSession() !== session_id()) {
                return ['success' => false, 'error' => "Cette place est actuellement en cours de réservation par un autre utilisateur.", 'reason' => 'taken_temporarily', 'seat_id' => $seatId];
            }
        }

        return ['success' => true, 'error' => null, 'reason' => null, 'place' => $place];
    }

}