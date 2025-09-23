<?php

namespace app\Models\Event;

use app\Models\AbstractModel;
use DateTime;

class EventInscriptionDate extends AbstractModel
{
    private int $event;
    private ?Event $eventObject = null;
    private string $name;
    private \DateTimeInterface $start_registration_at;
    private \DateTimeInterface $close_registration_at;
    private ?string $access_code = null;

    // --- GETTERS ---
    public function getEvent(): int { return $this->event; }
    public function getEventObject(): ?Event { return $this->eventObject; }
    public function getName(): string { return $this->name; }
    public function getStartRegistrationAt(): \DateTimeInterface { return $this->start_registration_at; }
    public function getCloseRegistrationAt(): \DateTimeInterface { return $this->close_registration_at; }
    public function getAccessCode(): ?string { return $this->access_code; }

    // --- SETTERS ---
    public function setEvent(int $event): self { $this->event = $event; return $this; }
    public function setEventObject(?Event $eventObject): self {
        $this->eventObject = $eventObject;
        if ($eventObject) { $this->event = $eventObject->getId(); }
        return $this;
    }
    public function setName(string $name): self { $this->name = $name; return $this; }
    public function setStartRegistrationAt(string $dt): self { $this->start_registration_at = new DateTime($dt); return $this; }
    public function setCloseRegistrationAt(string $dt): self { $this->close_registration_at = new DateTime($dt); return $this; }
    public function setAccessCode(?string $code): self { $this->access_code = ($code === '' ? null : $code); return $this; }
}
