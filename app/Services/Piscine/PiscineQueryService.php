<?php

namespace app\Services\Piscine;

use app\Models\Piscine\Piscine;
use app\Repository\Reservation\ReservationDetailRepository;

class PiscineQueryService
{

    /**
     * Pour récupérer le niveau de remplissement de la piscine
     * @param int $sessionId
     * @param Piscine $piscine
     * @return array
     */
    public function checkTotalCapacityLimit(int $sessionId, Piscine $piscine): array
    {
        $reservationDetailRepository = new ReservationDetailRepository();

        //On récupère le nombre de places actuellement réservées (et validées).
        $NbSpectator = $reservationDetailRepository->countBySession($sessionId);

        if ($NbSpectator['total'] >= $piscine->getMaxPlaces()) {
            return ['success' => false, 'limitReached' => true, 'limit' => 0];
        }

        return ['success' => true, 'limitReached' => false, 'limit' => $piscine->getMaxPlaces() - $NbSpectator['total']];
    }



}