<?php
namespace app\Models\Reservation;

use app\Models\AbstractModel;

class ReservationComplement extends AbstractModel
{
    private int $reservation;
    private ?Reservation $reservationObject = null;
    private int $tarif;
    private ?object $tarifObject = null;
    private ?string $tarif_access_code = null;
    private int $qty;

    // --- GETTERS ---
    public function getReservation(): int { return $this->reservation; }
    public function getReservationObject(): ?Reservation { return $this->reservationObject; }
    public function getTarif(): int { return $this->tarif; }
    public function getTarifObject(): ?object { return $this->tarifObject; }
    public function getTarifAccessCode(): ?string { return $this->tarif_access_code; }
    public function getQty(): int { return $this->qty; }

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
    public function setQty(int $qty): self
    {
        $this->qty = $qty;
        return $this;
    }
}
