<?php

namespace app\Models\Event;

use app\Models\AbstractModel;
use DateTime;

class EventSession extends AbstractModel
{
    private int $eventId;
    private ?Event $eventObject = null;
    private ?string $sessionName = null;
    private \DateTimeInterface $openingDoorsAt;
    private \DateTimeInterface $eventStartAt;

    // --- GETTERS ---
    public function getEventId(): int { return $this->eventId; }
    public function getEventObject(): ?Event { return $this->eventObject; }
    public function getSessionName(): ?string { return $this->sessionName; }
    public function getOpeningDoorsAt(): \DateTimeInterface { return $this->openingDoorsAt; }
    public function getEventStartAt(): \DateTimeInterface { return $this->eventStartAt; }

    // --- SETTERS ---
    public function setEventId(int $eventId): self { $this->eventId = $eventId; return $this; }
    public function setEventObject(?Event $event): self {
        $this->eventObject = $event;
        if ($event) { $this->eventId = $event->getId(); }
        return $this;
    }
    public function setSessionName(?string $sessionName): self { $this->sessionName = ($sessionName === '' ? null : $sessionName); return $this; }
    public function setOpeningDoorsAt(string $openingDoorsAt): self {
        $this->openingDoorsAt = new DateTime($openingDoorsAt); return $this;
    }
    public function setEventStartAt(string $eventStartAt): self {
        $this->eventStartAt = new DateTime($eventStartAt); return $this;
    }
}
