<?php
// app/Utils/DurationHelper.php
namespace app\Utils;

use DateInterval;
use DateTimeImmutable;
use Throwable;

class DurationHelper
{
    /**
     * Convertit une durée ISO 8601 en secondes.
     * Retourne null si invalide ou secondes
     * Ne doit pas dépasser 24h PT24h
     * @param string $iso8601Duration
     * @return int|null
     */
    public static function iso8601ToSeconds(string $iso8601Duration): ?int
    {
        if ($iso8601Duration === '' || $iso8601Duration[0] !== 'P') {
            return null;
        }

        try {
            $interval = new DateInterval($iso8601Duration);
        } catch (Throwable) {
            return null;
        }

        $now = new DateTimeImmutable();
        $future = $now->add($interval);
        $seconds = $future->getTimestamp() - $now->getTimestamp();

        return max($seconds, 0);
    }

    /**
     * Pour savoir si le timeout est dépassé.
     * @param string $dateTime au format ISO 8601
     * @return bool
     */
    public function timeoutIsExpired(string$dateTime): bool
    {
        try {
            $timeoutDate = new DateTimeImmutable($dateTime);
            $now = new DateTimeImmutable();
            // Retourne vrai si la date actuelle est postérieure à la date du timeout
            return $now > $timeoutDate;
        } catch (Throwable) {
            // Si le format de la date est invalide, on peut considérer le timeout
            // comme expiré pour des raisons de sécurité, ou retourner false.
            // Retourner true est souvent plus sûr pour ne pas laisser une action se poursuivre indéfiniment.
            return true;
        }
    }

}
