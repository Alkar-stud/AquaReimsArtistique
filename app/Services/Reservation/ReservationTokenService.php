<?php

namespace app\Services\Reservation;

use app\Models\Reservation\Reservation;
use app\Repository\Reservation\ReservationRepository;
use app\Services\Mails\MailPrepareService;
use app\Services\Mails\MailService;
use app\Services\Security\TokenGenerateService;

class ReservationTokenService
{
    private ReservationRepository $reservationRepository;
    private TokenGenerateService $tokenGenerateService;
    private MailPrepareService $mailPrepareService;
    private MailService $mailService;

    public function __construct(
        ReservationRepository $reservationRepository,
        TokenGenerateService $tokenGenerateService,
        MailPrepareService $mailPrepareService,
        MailService $mailService,
    )
    {
        $this->reservationRepository = $reservationRepository;
        $this->tokenGenerateService = $tokenGenerateService;
        $this->mailPrepareService = $mailPrepareService;
        $this->mailService = $mailService;
    }


    /**
     * Pour modifier le token et/ou l'heure d'expiration du token d'une réservation
     *
     * @param Reservation $reservation
     * @param bool $newToken
     * @param string|null $newDate
     * @param bool $sendEmail
     * @return Reservation
     */
    public function updateToken(Reservation $reservation, bool $newToken, ?string $newDate = null, bool $sendEmail = false): Reservation
    {
        //Si on doit modifier le token, on le génère
        if ($newToken) {
            $newToken = $this->tokenGenerateService->generateToken(32);
            $reservation->setToken($newToken['token']);
        }

        //Si on a besoin d'une nouvelle date
        if ($newDate) {
            $reservation->setTokenExpireAt($newDate);
        }

        //S'il faut envoyer un nouveau mail de confirmation suite au changement de token pour le lien modifData
        if ($sendEmail) {
            //On prépare le mail et on envoie le mail
            $this->mailPrepareService->sendReservationConfirmationEmail($reservation, 'summary');
            //On enregistre l'envoi du mail dans la BDD
            $this->mailService->recordMailSent($reservation, 'summary');
        }

        //On met à jour Reservation
        $this->reservationRepository->update($reservation);

        return $reservation;
    }

}