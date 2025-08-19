<?php

namespace app\Models\Event;

use DateMalformedStringException;
use DateTime;
use DateTimeInterface;

class EventSession
{
    private int $id;
    private int $event_id;
    private ?string $session_name = null;
    private DateTimeInterface $opening_doors_at;
    private DateTimeInterface $event_start_at;
    private DateTimeInterface $created_at;
    private ?DateTimeInterface $updated_at = null;

    // --- GETTERS ---

    public function getId(): int
    {
        return $this->id;
    }

    public function getEventId(): int
    {
        return $this->event_id;
    }

    public function getSessionName(): ?string
    {
        return $this->session_name;
    }

    public function getOpeningDoorsAt(): DateTimeInterface
    {
        return $this->opening_doors_at;
    }

    public function getEventStartAt(): DateTimeInterface
    {
        return $this->event_start_at;
    }

    public function getCreatedAt(): DateTimeInterface
    {
        return $this->created_at;
    }

    public function getUpdatedAt(): ?DateTimeInterface
    {
        return $this->updated_at;
    }

    // --- SETTERS ---

    public function setId(int $id): self
    {
        $this->id = $id;
        return $this;
    }

    public function setEventId(int $event_id): self
    {
        $this->event_id = $event_id;
        return $this;
    }

    public function setSessionName(?string $session_name): self
    {
        $this->session_name = $session_name;
        return $this;
    }

    public function setOpeningDoorsAt(string $opening_doors_at): self
    {
        $this->opening_doors_at = new DateTime($opening_doors_at);
        return $this;
    }

    public function setEventStartAt(string $event_start_at): self
    {
        $this->event_start_at = new DateTime($event_start_at);
        return $this;
    }

    public function setCreatedAt(string $created_at): self
    {
        $this->created_at = new DateTime($created_at);
        return $this;
    }

    public function setUpdatedAt(?string $updated_at): self
    {
        $this->updated_at = $updated_at ? new DateTime($updated_at) : null;
        return $this;
    }
}