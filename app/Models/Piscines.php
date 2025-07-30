<?php

namespace app\Models;

use DateMalformedStringException;
use DateTime;
use DateTimeInterface;

class Piscines
{
    private int $id;
    private string $libelle;
    private string $adresse;
    private int $max_places;
    private bool $numbered_seats;
    private DateTimeInterface $created_at;
    private ?DateTimeInterface $updated_at = null;

    // --- GETTERS ---

    public function getId(): int
    {
        return $this->id;
    }

    public function getLibelle(): string
    {
        return $this->libelle;
    }

    public function getAdresse(): string
    {
        return $this->adresse;
    }

    public function getMaxPlaces(): int
    {
        return $this->max_places;
    }

    public function getNumberedSeats(): bool
    {
        return $this->numbered_seats;
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

    public function setLibelle(string $libelle): self
    {
        $this->libelle = $libelle;
        return $this;
    }

    public function setAdresse(string $adresse): self
    {
        $this->adresse = $adresse;
        return $this;
    }

    public function setMaxPlaces(int $max_places): self
    {
        $this->max_places = $max_places;
        return $this;
    }

    public function setNumberedSeats($numbered_seats): self
    {
        $this->numbered_seats = (bool)$numbered_seats;
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