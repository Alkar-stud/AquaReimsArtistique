<?php

namespace app\Models\Reservation;

use DateMalformedStringException;
use DateTime;
use DateTimeInterface;

class ReservationsDetails
{
    private int $id;
    private int $reservation;
    private ?Reservations $reservationObject = null;
    private ?string $nom = null;
    private ?string $prenom = null;
    private int $tarif;
    private ?object $tarifObject = null;
    private ?string $tarif_access_code = null;
    private ?string $justificatif_name = null;
    private ?int $place_number = null;
    private ?object $placeObject = null;
    private DateTimeInterface $created_at;
    private ?DateTimeInterface $updated_at = null;

    // --- GETTERS ---

    public function getId(): int
    {
        return $this->id;
    }

    public function getReservation(): int
    {
        return $this->reservation;
    }

    public function getReservationObject(): ?Reservations
    {
        return $this->reservationObject;
    }

    public function getNom(): ?string
    {
        return $this->nom;
    }

    public function getPrenom(): ?string
    {
        return $this->prenom;
    }

    public function getTarif(): int
    {
        return $this->tarif;
    }

    public function getTarifObject(): ?object
    {
        return $this->tarifObject;
    }

    public function getTarifAccessCode(): ?string
    {
        return $this->tarif_access_code;
    }

    public function getJustificatifName(): ?string
    {
        return $this->justificatif_name;
    }

    public function getPlaceNumber(): ?int
    {
        return $this->place_number;
    }

    public function getPlaceObject(): ?object
    {
        return $this->placeObject;
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

    public function setReservation(int $reservation): self
    {
        $this->reservation = $reservation;
        return $this;
    }

    public function setReservationObject(?Reservations $reservationObject): self
    {
        $this->reservationObject = $reservationObject;
        if ($reservationObject) {
            $this->reservation = $reservationObject->getId();
        }
        return $this;
    }

    public function setNom(?string $nom): self
    {
        $this->nom = $nom;
        return $this;
    }

    public function setPrenom(?string $prenom): self
    {
        $this->prenom = $prenom;
        return $this;
    }

    public function setTarif(int $tarif): self
    {
        $this->tarif = $tarif;
        return $this;
    }

    public function setTarifObject(?object $tarifObject): self
    {
        $this->tarifObject = $tarifObject;
        return $this;
    }

    public function setTarifAccessCode(?string $tarif_access_code): self
    {
        $this->tarif_access_code = $tarif_access_code;
        return $this;
    }

    public function setJustificatifName(?string $justificatif_name): self
    {
        $this->justificatif_name = $justificatif_name;
        return $this;
    }

    public function setPlaceNumber(?int $place_number): self
    {
        $this->place_number = $place_number;
        return $this;
    }

    public function setPlaceObject(?object $placeObject): self
    {
        $this->placeObject = $placeObject;
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