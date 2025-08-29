<?php

namespace app\Models\Reservation;

use DateTime;
use DateTimeInterface;

class ReservationsPlacesTemp
{
    private string $session;
    private int $place_id;
    private int $index;
    private DateTimeInterface $created_at;
    private DateTimeInterface $timeout;

    // --- GETTERS ---

    public function getSession(): string
    {
        return $this->session;
    }

    public function getPlaceId(): int
    {
        return $this->place_id;
    }

    public function getIndex(): int
    {
        return $this->index;
    }

    public function getCreatedAt(): DateTimeInterface
    {
        return $this->created_at;
    }

    public function getTimeout(): DateTimeInterface
    {
        return $this->timeout;
    }

    // --- SETTERS ---

    public function setSession(string $session): self
    {
        $this->session = $session;
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

    public function setCreatedAt(string $created_at): self
    {
        $this->created_at = new DateTime($created_at);
        return $this;
    }

    public function setTimeout(string $timeout): self
    {
        $this->timeout = new DateTime($timeout);
        return $this;
    }
}