<?php
namespace app\Utils;

use DateInterval;
use DateTimeImmutable;
use Exception;

class DurationHelper
{
    /**
     * Converts an ISO 8601 duration string (e.g., "PT60M") into the total number of seconds.
     *
     * @param string $iso8601Duration The duration string in ISO 8601 format.
     * @return int The total number of seconds.
     * @throws Exception If the duration string is invalid.
     */
    public static function iso8601ToSeconds(string $iso8601Duration): int
    {
        $interval = new DateInterval($iso8601Duration);
        $now = new DateTimeImmutable();
        $future = $now->add($interval);
        return $future->getTimestamp() - $now->getTimestamp();
    }
}