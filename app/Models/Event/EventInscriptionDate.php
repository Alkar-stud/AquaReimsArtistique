<?php

namespace app\Models\Event;

use app\Models\AbstractModel;
use DateTime;

class EventInscriptionDate extends AbstractModel
{
    private int $eventId;
    private ?Event $eventObject = null;
    private string $name;
    private \DateTimeInterface $startRegistrationAt;
    private \DateTimeInterface $closeRegistrationAt;
    private ?string $accessCode = null;

    // --- GETTERS ---
    public function getEventId(): int { return $this->eventId; }
    public function getEventObject(): ?Event { return $this->eventObject; }
    public function getName(): string { return $this->name; }
    public function getStartRegistrationAt(): \DateTimeInterface { return $this->startRegistrationAt; }
    public function getCloseRegistrationAt(): \DateTimeInterface { return $this->closeRegistrationAt; }
    public function getAccessCode(): ?string { return $this->accessCode; }

    // --- SETTERS ---
    public function setEventId(int $eventId): self { $this->eventId = $eventId; return $this; }
    public function setEventObject(?Event $eventObject): self {
        $this->eventObject = $eventObject;
        if ($eventObject) { $this->eventId = $eventObject->getId(); }
        return $this;
    }
    public function setName(string $name): self { $this->name = $name; return $this; }
    public function setStartRegistrationAt(string $dt): self { $this->startRegistrationAt = new DateTime($dt); return $this; }
    public function setCloseRegistrationAt(string $dt): self { $this->closeRegistrationAt = new DateTime($dt); return $this; }
    public function setAccessCode(?string $code): self { $this->accessCode = ($code === '' ? null : $code); return $this; }
}
