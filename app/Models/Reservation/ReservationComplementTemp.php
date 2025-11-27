<?php
namespace app\Models\Reservation;

use app\Models\AbstractModel;
use DateTime;
use DateTimeInterface;

class ReservationComplementTemp extends AbstractModel
{
    private int $reservation_temp;
    private ?ReservationTemp $reservationObject = null;
    private int $tarif;
    private ?object $tarifObject = null;
    private ?string $tarif_access_code = null;
    private int $qty;

    // --- GETTERS ---
    public function getReservationTemp(): int { return $this->reservation_temp; }
    public function getReservationObject(): ?ReservationTemp { return $this->reservationObject; }
    public function getTarif(): int { return $this->tarif; }
    public function getTarifObject(): ?object { return $this->tarifObject; }
    public function getTarifAccessCode(): ?string { return $this->tarif_access_code; }
    public function getQty(): int { return $this->qty; }
    public function getCreatedAt(): DateTimeInterface { return $this->created_at; }
    public function getUpdatedAt(): ?DateTimeInterface { return $this->updated_at; }

    // --- SETTERS ---
    public function setReservationTemp(int $reservation_temp): self { $this->reservation_temp = $reservation_temp; return $this; }
    public function setReservationObject(?ReservationTemp $reservationObject): self { $this->reservationObject = $reservationObject; if ($reservationObject) $this->reservation_temp = $reservationObject->getId(); return $this; }
    public function setTarif(int $tarif): self { $this->tarif = $tarif; return $this; }
    public function setTarifObject(?object $tarifObject): self { $this->tarifObject = $tarifObject; return $this; }
    public function setTarifAccessCode(?string $tarif_access_code): self { $this->tarif_access_code = $tarif_access_code; return $this; }
    public function setQty(int $qty): self { $this->qty = $qty; return $this; }

    public function toArray(): array
    {
        $tarifObject = $this->getTarifObject();

        return [
            'id' => $this->getId(),
            'reservationTempId' => $this->getReservationTemp(),
            'tarifId' => $this->getTarif(),
            'tarifName' => $tarifObject?->getName(),
            'tarifDescription' => $tarifObject?->getDescription(),
            'tarifPrice' => $tarifObject?->getPrice(),
            'tarifAccessCode' => $this->getTarifAccessCode(),
            'quantity' => $this->getQty(),
            'createdAt' => $this->getCreatedAt()->format(DateTime::ATOM),
            'updatedAt' => $this->getUpdatedAt()?->format(DateTime::ATOM),
        ];
    }
}
