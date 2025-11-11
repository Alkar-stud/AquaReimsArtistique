<?php

namespace app\Models\Event;

use app\Models\AbstractModel;
use DateTime;
use DateTimeInterface;

class EventPresentations extends AbstractModel
{
    private int $eventId;
    private ?Event $eventObject = null;
    private bool $isDisplayed = false;
    private DateTimeInterface $displayUntil;
    private ?string $content = null;

    // --- GETTERS ---
    public function getEventId(): int { return $this->eventId; }
    public function getEventObject(): ?Event { return $this->eventObject; }
    public function getIsDisplayed(): bool { return $this->isDisplayed; }
    public function getDisplayUntil(): DateTimeInterface { return $this->displayUntil; }
    public function getContent(): ?string { return $this->content; }

    // --- SETTERS ---
    public function setEventId(int $eventId): self { $this->eventId = $eventId; return $this; }
    public function setEventObject(?Event $eventObject): self {
        $this->eventObject = $eventObject;
        if ($eventObject) { $this->eventId = $eventObject->getId(); }
        return $this;
    }
    public function setIsDisplayed(bool $isDisplayed): self { $this->isDisplayed = $isDisplayed; return $this; }
    public function setDisplayUntil(string $displayUntil): self {
        $this->displayUntil = new DateTime($displayUntil); return $this;
    }
    public function setContent(?string $content): self {
        $this->content = ($content === '' ? null : $content); return $this;
    }
}
