<?php

namespace app\Services;

use app\Models\Reservation\ReservationSession;

class ReservationSessionService
{
    public function createSession(): ReservationSession
    {
        $session = new ReservationSession();
        $_SESSION['reservation_token'] = $session->getToken();
        $_SESSION['reservation_session'] = $session->serialize();
        return $session;
    }

    public function getSession(): ?ReservationSession
    {
        if (!isset($_SESSION['reservation_session'])) {
            return null;
        }

        $session = ReservationSession::deserialize($_SESSION['reservation_session']);

        if ($session->isExpired()) {
            $this->clearSession();
            return null;
        }

        return $session;
    }

    public function updateSession(ReservationSession $session): void
    {
        $_SESSION['reservation_session'] = $session->serialize();
    }

    public function clearSession(): void
    {
        unset($_SESSION['reservation_token'], $_SESSION['reservation_session']);
    }
}