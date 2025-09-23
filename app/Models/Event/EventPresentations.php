<?php

namespace app\Models\Event;

use app\Models\AbstractModel;
use DateTime;
use DateTimeInterface;

class EventPresentations extends AbstractModel
{
    private int $event;
    private ?Event $eventObject = null;
    private bool $is_displayed = false;
    private DateTimeInterface $display_until;
    private ?string $content = null;

    // --- GETTERS ---
    public function getEvent(): int { return $this->event; }
    public function getEventObject(): ?Event { return $this->eventObject; }
    public function getIsDisplayed(): bool { return $this->is_displayed; }
    public function getDisplayUntil(): DateTimeInterface { return $this->display_until; }
    public function getContent(): ?string { return $this->content; }

    // --- SETTERS ---
    public function setEvent(int $event): self { $this->event = $event; return $this; }
    public function setEventObject(?Event $eventObject): self {
        $this->eventObject = $eventObject;
        if ($eventObject) { $this->event = $eventObject->getId(); }
        return $this;
    }
    public function setIsDisplayed(bool $is_displayed): self { $this->is_displayed = $is_displayed; return $this; }
    public function setDisplayUntil(string $display_until): self {
        $this->display_until = new DateTime($display_until); return $this;
    }
    public function setContent(?string $content): self {
        $this->content = ($content === '' ? null : $content); return $this;
    }
}
