<?php

namespace app\Models\Event;

use DateMalformedStringException;
use DateTime;
use DateTimeInterface;

class EventInscriptionDates
{
    private int $id;
    private int $event; // ID de l'événement associé
    private ?Events $eventObject = null; // Objet Event lié
    private string $libelle;
    private DateTimeInterface $start_registration_at;
    private DateTimeInterface $close_registration_at;
    private ?string $access_code = null;
    private DateTimeInterface $created_at;
    private ?DateTimeInterface $updated_at = null;

    // --- GETTERS ---

    public function getId(): int
    {
        return $this->id;
    }

    public function getEvent(): int
    {
        return $this->event;
    }

    public function getEventObject(): ?Events
    {
        return $this->eventObject;
    }

    public function getLibelle(): string
    {
        return $this->libelle;
    }

    public function getStartRegistrationAt(): DateTimeInterface
    {
        return $this->start_registration_at;
    }

    public function getCloseRegistrationAt(): DateTimeInterface
    {
        return $this->close_registration_at;
    }

    public function getAccessCode(): ?string
    {
        return $this->access_code;
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

    public function setEvent(int $event): self
    {
        $this->event = $event;
        return $this;
    }

    public function setEventObject(?Events $eventObject): self
    {
        $this->eventObject = $eventObject;
        if ($eventObject) {
            $this->event = $eventObject->getId();
        }
        return $this;
    }

    public function setLibelle(string $libelle): self
    {
        $this->libelle = $libelle;
        return $this;
    }

    /**
     * @throws DateMalformedStringException
     */
    public function setStartRegistrationAt(string $start_registration_at): self
    {
        $this->start_registration_at = new DateTime($start_registration_at);
        return $this;
    }

    /**
     * @throws DateMalformedStringException
     */
    public function setCloseRegistrationAt(string $close_registration_at): self
    {
        $this->close_registration_at = new DateTime($close_registration_at);
        return $this;
    }

    public function setAccessCode(?string $access_code): self
    {
        $this->access_code = ($access_code === '' ? null : $access_code);
        return $this;
    }

    /**
     * @throws DateMalformedStringException
     */
    public function setCreatedAt(string $created_at): self
    {
        $this->created_at = new DateTime($created_at);
        return $this;
    }

    /**
     * @throws DateMalformedStringException
     */
    public function setUpdatedAt(?string $updated_at): self
    {
        $this->updated_at = $updated_at ? new DateTime($updated_at) : null;
        return $this;
    }
}