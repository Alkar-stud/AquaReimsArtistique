<?php
// app/Utils/DurationHelper.php
namespace app\Utils;

use DateInterval;
use DateTimeImmutable;
use Throwable;

class DurationHelper
{
    /**
     * Convertit une durée ISO 8601 en secondes totales.
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
}
