<?php
namespace app\Models\Reservation;

use app\Models\AbstractModel;
use DateMalformedStringException;
use DateTime;
use DateTimeInterface;

class ReservationPlaceTemp extends AbstractModel
{
    private string $session;
    private int $event_session_id;
    private int $place_id;
    private int $index;
    private DateTimeInterface $expire_at;

    // --- GETTERS ---
    public function getSession(): string { return $this->session; }
    public function getEventSessionId(): int { return $this->event_session_id; }
    public function getPlaceId(): int { return $this->place_id; }
    public function getIndex(): int { return $this->index; }
    public function getExpireAt(): DateTimeInterface { return $this->expire_at; }

    // --- SETTERS ---
    public function setSession(string $session): self
    {
        $this->session = $session;
        return $this;
    }

    public function setEventSessionId(int $event_session_id): self
    {
        $this->event_session_id = $event_session_id;
        return $this;
    }

    public function setPlaceId(int $place_id): self
    {
        $this->place_id = $place_id;
        return $this;
    }

    public function setIndex(int $index): self
    {
        $this->index = $index;
        return $this;
    }

    public function setExpireAt(string $expire_at): self
    {
        try {
            $this->expire_at = new DateTime($expire_at);
        } catch (DateMalformedStringException $e) {

        }
        return $this;
    }
}
