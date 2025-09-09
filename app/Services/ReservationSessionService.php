<?php

namespace app\Services;

class ReservationSessionService
{
    private string $sessionId;

    public function __construct()
    {
        $this->sessionId = session_id();
        if (!isset($_SESSION['reservation'][$this->sessionId])) {
            $_SESSION['reservation'][$this->sessionId] = [];
        }
    }

    /**
     * Récupère les données de la session de réservation en cours.
     * @return array|null
     */
    public function getReservationSession(): ?array
    {
        return $_SESSION['reservation'][$this->sessionId] ?? null;
    }

    /**
     * Met à jour une valeur spécifique dans la session de réservation.
     * @param string $key
     * @param mixed $value
     */
    public function setReservationSession(string $key, mixed $value): void
    {
        $_SESSION['reservation'][$this->sessionId][$key] = $value;
    }

    /**
     * Efface complètement la session de réservation en cours.
     */
    public function clearReservationSession(): void
    {
        unset($_SESSION['reservation'][$this->sessionId]);
    }
}