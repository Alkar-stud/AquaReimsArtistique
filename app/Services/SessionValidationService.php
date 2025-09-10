<?php

namespace app\Services;

use app\Utils\DurationHelper;
use Exception;

class SessionValidationService
{
    /**
     * Vérifie si une session est active en se basant sur sa dernière activité et un timeout.
     *
     * @param array|null $sessionData Le tableau de la session à vérifier.
     * @param string $activityKey La clé contenant le timestamp de la dernière activité (ex: 'last_activity').
     * @param string $timeoutConstant La constante contenant la durée du timeout au format ISO 8601 (ex: TIMEOUT_PLACE_RESERV).
     * @return bool True si la session est active, false sinon.
     * @throws Exception
     */
    public function isSessionActive(?array $sessionData, string $activityKey, string $timeoutConstant): bool
    {
        if (!$sessionData) {
            return false;
        }

        $lastActivity = $sessionData[$activityKey] ?? 0;
        $timeoutSeconds = DurationHelper::iso8601ToSeconds($timeoutConstant);

        return (time() - $lastActivity) <= $timeoutSeconds;
    }
}