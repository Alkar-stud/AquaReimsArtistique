<?php

namespace app\Services;

use app\Models\Reservation\ReservationSession;

class ReservationSessionService
{
    public function createReservationSession(): ReservationSession
    {
        return $_SESSION['reservation'][session_id()] = [];
    }

    public function getReservationSession(): ?ReservationSession
    {
        if (!isset($_SESSION['reservation'])) {
            return null;
        }

        $session = ReservationSession::deserialize($_SESSION['reservation']);

        if ($session->isExpired()) {
            $this->clearSession();
            return null;
        }

        return $session;
    }

    public function updateReservationSession(ReservationSession $session): void
    {
        $_SESSION['reservation'] = $session->serialize();
    }

    public function clearReservationSession(): void
    {
        unset($_SESSION['reservation']);
    }

    public function getReservationFromSession() {
        $session = $this->getReservationSession();
        return $session ? $session->getReservation() : null;
    }
}