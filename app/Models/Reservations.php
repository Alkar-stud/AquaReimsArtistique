<?php

namespace app\Models;

use DateMalformedStringException;
use DateTime;
use DateTimeInterface;

class Reservations
{
    private int $id;
    private int $event; // ID de l'événement
    private ?Events $eventObject = null; // Objet Events lié
    private string $nom;
    private string $prenom;
    private string $email;
    private string $phone;
    private ?int $nageuse_si_limitation = null; // ID de la nageuse (si limitation)
    private ?Nageuses $nageuse = null; // Objet Nageuses lié
    private float $total_amount;
    private float $total_amount_paid;
    private string $token;
    private DateTimeInterface $token_expire_at;
    private bool $is_canceled = false;
    private ?string $comments = null;
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

    public function getNom(): string
    {
        return $this->nom;
    }

    public function getPrenom(): string
    {
        return $this->prenom;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function getPhone(): string
    {
        return $this->phone;
    }

    public function getNageuseId(): ?int
    {
        return $this->nageuse_si_limitation;
    }

    public function getNageuse(): ?Nageuses
    {
        return $this->nageuse;
    }

    public function getTotalAmount(): float
    {
        return $this->total_amount;
    }

    public function getTotalAmountPaid(): float
    {
        return $this->total_amount_paid;
    }

    public function getToken(): string
    {
        return $this->token;
    }

    public function getTokenExpireAt(): DateTimeInterface
    {
        return $this->token_expire_at;
    }

    public function isCanceled(): bool
    {
        return $this->is_canceled;
    }

    public function getComments(): ?string
    {
        return $this->comments;
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

    public function setNom(string $nom): self
    {
        $this->nom = $nom;
        return $this;
    }

    public function setPrenom(string $prenom): self
    {
        $this->prenom = $prenom;
        return $this;
    }

    public function setEmail(string $email): self
    {
        $this->email = $email;
        return $this;
    }

    public function setPhone(string $phone): self
    {
        $this->phone = $phone;
        return $this;
    }

    public function setNageuseId(?int $nageuse_si_limitation): self
    {
        $this->nageuse_si_limitation = $nageuse_si_limitation;
        return $this;
    }

    public function setNageuse(?Nageuses $nageuse): self
    {
        $this->nageuse = $nageuse;
        if ($nageuse) {
            $this->nageuse_si_limitation = $nageuse->getId();
        }
        return $this;
    }

    public function setTotalAmount(float $total_amount): self
    {
        $this->total_amount = $total_amount;
        return $this;
    }

    public function setTotalAmountPaid(float $total_amount_paid): self
    {
        $this->total_amount_paid = $total_amount_paid;
        return $this;
    }

    public function setToken(string $token): self
    {
        $this->token = $token;
        return $this;
    }

    /**
     * @throws DateMalformedStringException
     */
    public function setTokenExpireAt(string $token_expire_at): self
    {
        $this->token_expire_at = new DateTime($token_expire_at);
        return $this;
    }

    public function setIsCanceled(bool $is_canceled): self
    {
        $this->is_canceled = $is_canceled;
        return $this;
    }

    public function setComments(?string $comments): self
    {
        $this->comments = $comments;
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