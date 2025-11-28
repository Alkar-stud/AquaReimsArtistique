<?php
namespace app\Models\Reservation;

use app\Models\AbstractModel;
use app\Models\Event\Event;
use app\Models\Event\EventSession;
use app\Models\Swimmer\Swimmer;
use app\Utils\StringHelper;
use DateTime;
use DateTimeInterface;

class ReservationTemp extends AbstractModel
{
    // FK vers event
    private int $event;
    private ?Event $eventObject = null;
    // FK vers event_session
    private int $event_session;
    private ?EventSession $eventSessionObject = null;
    private string $session_id;
    private ?string $name = null;
    private ?string $firstname = null;
    private ?string $email = null;
    private ?string $phone = null;
    private ?int $swimmer_if_limitation = null;
    private ?Swimmer $swimmer = null;
    private ?string $access_code = null;

    // relations enfants (optionnel)
    private array $details = [];
    private array $complements = [];

    // --- GETTERS ---
    public function getEvent(): int { return $this->event; }
    public function getEventObject(): ?Event { return $this->eventObject; }
    public function getEventSession(): int { return $this->event_session; }
    public function getEventSessionObject(): ?EventSession { return $this->eventSessionObject; }
    public function getSessionId(): string { return $this->session_id; }
    public function getName(): ?string { return $this->name; }
    public function getFirstName(): ?string { return $this->firstname; }
    public function getEmail(): ?string { return $this->email; }
    public function getPhone(): ?string { return $this->phone; }
    public function getSwimmerId(): ?int { return $this->swimmer_if_limitation; }
    public function getSwimmer(): ?Swimmer { return $this->swimmer; }
    public function getAccessCode(): ?string { return $this->access_code; }
    public function getDetails(): array { return $this->details; }
    public function getComplements(): array { return $this->complements; }

    // --- SETTERS ---
    public function setEvent(int $event): self { $this->event = $event; return $this; }
    public function setEventObject(?Event $eventObject): self { $this->eventObject = $eventObject; if ($eventObject) $this->event = $eventObject->getId(); return $this; }
    public function setEventSession(int $event_session): self { $this->event_session = $event_session; return $this; }
    public function setEventSessionObject(?EventSession $eventSessionObject): self {
        $this->eventSessionObject = $eventSessionObject;
        if ($eventSessionObject) {
            $this->event_session = $eventSessionObject->getId();
            if (method_exists($eventSessionObject, 'getEventId')) {
                $this->event = $eventSessionObject->getEventId();
            }
        }
        return $this;
    }
    public function setSessionId(string $session_id): self { $this->session_id = $session_id; return $this; }
    public function setName(?string $name): self
    {
        $this->name = ($name === null || $name === '') ? null : StringHelper::toUpperCase($name);
        return $this;
    }
    public function setFirstName(?string $firstname): self
    {
        $this->firstname = ($firstname === null || $firstname === '') ? null : StringHelper::toTitleCase($firstname);
        return $this;
    }
    public function setEmail(?string $email): self { $this->email = ($email === '' ? null : $email); return $this; }
    public function setPhone(?string $phone): self { $this->phone = ($phone === '' ? null : $phone); return $this; }
    public function setSwimmerId(?int $swimmer_if_limitation): self { $this->swimmer_if_limitation = $swimmer_if_limitation; return $this; }
    public function setSwimmer(?Swimmer $swimmer): self { $this->swimmer = $swimmer; if ($swimmer) $this->swimmer_if_limitation = $swimmer->getId(); return $this; }
    public function setAccessCode(?string $access_code): self { $this->access_code = ($access_code === '' ? null : $access_code); return $this; }
    public function setDetails(array $details): self { $this->details = $details; return $this; }
    public function setComplements(array $complements): self { $this->complements = $complements; return $this; }

    public function toArray(): array
    {
        $eventObject = $this->getEventObject();
        $eventSessionObject = $this->getEventSessionObject();
        return [
            'id' => $this->getId(),
            'event' => $eventObject ? ['id' => $eventObject->getId(), 'name' => $eventObject->getName()] : null,
            'eventSession' => $eventSessionObject ? ['id' => $eventSessionObject->getId(), 'name' => $eventSessionObject->getSessionName()] : null,
            'sessionId' => $this->getSessionId(),
            'name' => $this->getName(),
            'firstName' => $this->getFirstName(),
            'email' => $this->getEmail(),
            'phone' => $this->getPhone(),
            'swimmer' => $this->getSwimmer() ? ['id' => $this->getSwimmer()->getId(), 'name' => $this->getSwimmer()->getName()] : null,
            'createdAt' => $this->getCreatedAt()->format(DateTime::ATOM),
            'updatedAt' => $this->getUpdatedAt()?->format(DateTime::ATOM),
            'details' => array_map(fn($d) => $d->toArray(), $this->getDetails()),
            'complements' => array_map(fn($c) => $c->toArray(), $this->getComplements()),
        ];
    }
}
