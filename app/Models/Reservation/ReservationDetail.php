<?php
namespace app\Models\Reservation;

use app\Models\AbstractModel;
use app\Models\Piscine\PiscineGradinsPlaces;
use app\Utils\StringHelper;
use DateTime;
use DateTimeInterface;

class ReservationDetail extends AbstractModel
{
    private int $reservation;
    private ?Reservation $reservationObject = null;
    private ?string $name = null;
    private ?string $firstname = null;
    private int $tarif;
    private ?object $tarifObject = null;
    private ?string $tarif_access_code = null;
    private ?string $justificatif_name = null;
    private ?string $place_number = null;
    private ?PiscineGradinsPlaces $placeObject = null;
    private ?DateTimeInterface $entered_at = null;

    // --- GETTERS ---
    public function getReservation(): int { return $this->reservation; }
    public function getReservationObject(): ?Reservation { return $this->reservationObject; }
    public function getName(): ?string { return $this->name; }
    public function getFirstName(): ?string { return $this->firstname; }
    public function getTarif(): int { return $this->tarif; }
    public function getTarifObject(): ?object { return $this->tarifObject; }
    public function getTarifAccessCode(): ?string { return $this->tarif_access_code; }
    public function getJustificatifName(): ?string { return $this->justificatif_name; }
    public function getPlaceNumber(): ?string { return $this->place_number; }
    public function getPlaceObject(): ?PiscineGradinsPlaces { return $this->placeObject; }
    public function getEnteredAt(): ?DateTimeInterface { return $this->entered_at; }


    // --- SETTERS ---
    public function setReservation(int $reservation): self
    {
        $this->reservation = $reservation;
        return $this;
    }

    public function setReservationObject(?Reservation $reservationObject): self
    {
        $this->reservationObject = $reservationObject;
        if ($reservationObject) {
            $this->reservation = $reservationObject->getId();
        }
        return $this;
    }

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

    public function setPlaceNumber(?string $place_number): self
    {
        $this->place_number = $place_number;
        return $this;
    }

    public function setPlaceObject(?PiscineGradinsPlaces $placeObject): self
    {
        $this->placeObject = $placeObject;
        return $this;
    }

    public function setEnteredAt(?string $enteredAt): self {
        $this->entered_at = $enteredAt ? new DateTime($enteredAt) : null;
        return $this;
    }

    /**
     * Convertit l'objet en tableau pour la réponse JSON.
     * @return array
     */
    public function toArray(): array
    {
        $tarifObject = $this->getTarifObject();
        $placeObject = $this->getPlaceObject();

        return [
            'id' => $this->getId(),
            'name' => $this->getName(),
            'firstname' => $this->getFirstName(),
            'tarifId' => $this->getTarif(),
            'tarifName' => $tarifObject?->getName(),
            'tarifDescription' => $tarifObject?->getDescription(),
            'tarifPrice' => $tarifObject?->getPrice(),
            'placeNumber' => $this->getPlaceNumber(),
            'placeId' => $this->getPlaceObject()?->getId(),
            'fullPlaceName' => $placeObject?->getFullPlaceName(),
            'enteredAt' => $this->getEnteredAt()?->format(DateTimeInterface::ATOM), // ISO 8601
        ];
    }

}
