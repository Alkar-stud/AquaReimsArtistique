<?php
namespace app\Models\Reservation;

use app\Models\AbstractModel;
use app\Utils\StringHelper;
use app\Models\Piscine\PiscineGradinsPlaces;
use DateTime;
use DateTimeInterface;

class ReservationDetailTemp extends AbstractModel
{
    private int $reservation_temp;
    private ?string $name = null;
    private ?string $firstname = null;
    private int $tarif;
    private ?string $tarif_access_code = null;
    private ?string $justificatif_name = null;
    private ?string $justificatif_original_name = null;
    private ?string $place_number = null;
    private ?PiscineGradinsPlaces $placeObject = null;

    // --- GETTERS ---
    public function getReservationTemp(): int { return $this->reservation_temp; }
    public function getName(): ?string { return $this->name; }
    public function getFirstName(): ?string { return $this->firstname; }
    public function getTarif(): int { return $this->tarif; }
    public function getTarifAccessCode(): ?string { return $this->tarif_access_code; }
    public function getJustificatifName(): ?string { return $this->justificatif_name; }
    public function getJustificatifOriginalName(): ?string { return $this->justificatif_original_name; }
    public function getPlaceNumber(): ?string { return $this->place_number; }
    public function getPlaceObject(): ?PiscineGradinsPlaces { return $this->placeObject; }
    public function getCreatedAt(): DateTimeInterface { return $this->created_at; }
    public function getUpdatedAt(): ?DateTimeInterface { return $this->updated_at; }

    // --- SETTERS ---
    public function setReservationTemp(int $reservation_temp): self { $this->reservation_temp = $reservation_temp; return $this; }
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
    public function setTarif(int $tarif): self { $this->tarif = $tarif; return $this; }
    public function setTarifObject(?object $tarifObject): self { $this->tarifObject = $tarifObject; return $this; }
    public function setTarifAccessCode(?string $tarif_access_code): self { $this->tarif_access_code = $tarif_access_code; return $this; }
    public function setJustificatifName(?string $justificatif_name): self { $this->justificatif_name = $justificatif_name; return $this; }
    public function setJustificatifOriginalName(?string $justificatif_original_name): self { $this->justificatif_original_name = $justificatif_original_name; return $this; }
    public function setPlaceNumber(?string $place_number): self { $this->place_number = $place_number; return $this; }
    public function setPlaceObject(?PiscineGradinsPlaces $placeObject): self { $this->placeObject = $placeObject; return $this; }

    public function toArray(): array
    {
        $placeObject = $this->getPlaceObject();

        return [
            'id' => $this->getId(),
            'reservationTempId' => $this->getReservationTemp(),
            'name' => $this->getName(),
            'firstname' => $this->getFirstName(),
            'tarifId' => $this->getTarif(),
            'tarifAccessCode' => $this->getTarifAccessCode(),
            'justificatifName' => $this->getJustificatifName(),
            'justificatifOriginalName' => $this->getJustificatifOriginalName(),
            'placeNumber' => $this->getPlaceNumber(),
            'fullPlaceName' => $placeObject?->getFullPlaceName(),
            'createdAt' => $this->getCreatedAt()->format(DateTime::ATOM),
            'updatedAt' => $this->getUpdatedAt()?->format(DateTime::ATOM),
        ];
    }
}
