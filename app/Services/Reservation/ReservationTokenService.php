<?php
namespace app\Services\Reservation;

use app\Models\Reservation\Reservations;
use DateTime;
use DateTimeInterface;
use Random\RandomException;

class ReservationTokenService
{

    /**
     * Pour générer un token avec date de validité au jour de l'event
     *
     * @throws RandomException
     */
    public static function createReservationToken(int $nbCaractereToken, DateTimeInterface $dateEvent, ?DateTimeInterface $dateFinInscriptionsEvent = null): array
    {
        if ($dateFinInscriptionsEvent === null) {
            $dateFinInscriptionsEvent = $dateEvent;
        }
        //On génère le token et la date de validité en fonction de la date de l'event
        $token = bin2hex(random_bytes($nbCaractereToken));

        // La date de validité est formatée directement depuis l'objet DateTimeInterface,
        // et la ligne précédente qui était écrasée a été supprimée.
        $token_valid = $dateFinInscriptionsEvent->format('Y-m-d H:i:s');

        return [$token, $token_valid];
    }

    /**
     * Pour vérifier si le token est toujours valide
     *
     * @param Reservations $reservation
     * @param string $token
     * @return bool
     */
    public function checkReservationToken(Reservations $reservation, string $token): bool
    {
        // On vérifie que le token est toujours valide
        if ($token !== $reservation->getToken() || new DateTime() >= $reservation->getTokenExpireAt()) {
            return false;
        } else {
            return true;
        }
    }


}

