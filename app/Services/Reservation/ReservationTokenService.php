<?php

namespace app\Services\Reservation;

use app\Models\Reservation\Reservation;
use app\Repository\Reservation\ReservationRepository;
use app\Services\Mails\MailService;
use app\Services\Security\TokenGenerateService;
use PHPMailer\PHPMailer\Exception;
use RuntimeException;

class ReservationTokenService
{
    private ReservationRepository $reservationRepository;
    private TokenGenerateService $tokenGenerateService;
    private MailService $mailService;

    public function __construct(
        ReservationRepository $reservationRepository,
        TokenGenerateService $tokenGenerateService,
        MailService $mailService,
    )
    {
        $this->reservationRepository = $reservationRepository;
        $this->tokenGenerateService = $tokenGenerateService;
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
     * @throws Exception
     */
    public function updateToken(Reservation $reservation, bool $newToken, ?string $newDate = null, bool $sendEmail = false): Reservation
    {
        //Si on doit modifier le token, on le génère
        if ($newToken) {
            $newToken = $this->tokenGenerateService->generateToken((int)NB_CARACTERE_TOKEN);
            $reservation->setToken($newToken['token']);
        }

        //Si on a besoin d'une nouvelle date
        if ($newDate) {
            $reservation->setTokenExpireAt($newDate);
        }

        //S'il faut envoyer un nouveau mail de confirmation suite au changement de token pour le lien modifData
        if ($sendEmail) {
            //Envoyer le mail de réservation
            if (!$this->mailService->send(
                'summary',
                ['reservation' => $reservation],
                $reservation->getEmail(),
                'reservation.summary'
            )) {
                throw new RuntimeException('Échec de l\'envoi de l\'email');
            }
        }

        //On met à jour Reservation
        $this->reservationRepository->update($reservation);

        return $reservation;
    }

}