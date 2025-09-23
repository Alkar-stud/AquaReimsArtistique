<?php

namespace app\Models\Event;

use app\Models\AbstractModel;
use DateTime;

class EventSession extends AbstractModel
{
    private int $event_id;
    private ?Event $eventObject = null;
    private ?string $session_name = null;
    private \DateTimeInterface $opening_doors_at;
    private \DateTimeInterface $event_start_at;

    // --- GETTERS ---
    public function getEventId(): int { return $this->event_id; }
    public function getEventObject(): ?Event { return $this->eventObject; }
    public function getSessionName(): ?string { return $this->session_name; }
    public function getOpeningDoorsAt(): \DateTimeInterface { return $this->opening_doors_at; }
    public function getEventStartAt(): \DateTimeInterface { return $this->event_start_at; }

    // --- SETTERS ---
    public function setEventId(int $event_id): self { $this->event_id = $event_id; return $this; }
    public function setEventObject(?Event $event): self {
        $this->eventObject = $event;
        if ($event) { $this->event_id = $event->getId(); }
        return $this;
    }
    public function setSessionName(?string $session_name): self { $this->session_name = $session_name; return $this; }
    public function setOpeningDoorsAt(string $opening_doors_at): self {
        $this->opening_doors_at = new DateTime($opening_doors_at); return $this;
    }
    public function setEventStartAt(string $event_start_at): self {
        $this->event_start_at = new DateTime($event_start_at); return $this;
    }
}
