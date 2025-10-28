<?php

namespace app\Services\Reservation;

use app\Models\Reservation\Reservation;
use app\Repository\Reservation\ReservationRepository;
use app\Services\Security\TokenGenerateService;

class ReservationTokenService
{
    private ReservationRepository $reservationRepository;
    private TokenGenerateService $tokenGenerateService;

    public function __construct(
        ReservationRepository $reservationRepository,
        TokenGenerateService $tokenGenerateService,
    )
    {
        $this->reservationRepository = $reservationRepository;
        $this->tokenGenerateService = $tokenGenerateService;
    }


    /**
     * Pour modifier le token et/ou l'heure d'expiration du token d'une réservation
     *
     * @param Reservation $reservation
     * @param bool $newToken
     * @param string|null $newDate
     * @return Reservation
     */
    public function updateToken(Reservation $reservation, bool $newToken, ?string $newDate = null): Reservation
    {
        if ($newToken) {
            $newToken = $this->tokenGenerateService->generateToken(32);
            $reservation->setToken($newToken['token']);
        }
        if ($newDate) {
            $reservation->setTokenExpireAt($newDate);
        }

        //On met à jour Reservation
        $this->reservationRepository->update($reservation);

        return $reservation;
    }

}