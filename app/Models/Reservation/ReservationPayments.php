<?php

namespace app\Models\Reservation;

use DateMalformedStringException;
use DateTime;
use DateTimeInterface;

class ReservationPayments
{
    private int $id;
    private int $reservation; // ID de la réservation
    private ?Reservations $reservationObject = null; // Objet Reservations lié
    private float $amount_paid;
    private int $checkout_id;
    private ?string $status_payment = null;
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

    public function getAmountPaid(): float
    {
        return $this->amount_paid;
    }

    public function getCheckoutId(): int
    {
        return $this->checkout_id;
    }

    public function getStatusPayment(): ?string
    {
        return $this->status_payment;
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

    public function setAmountPaid(float $amount_paid): self
    {
        $this->amount_paid = $amount_paid;
        return $this;
    }

    public function setCheckoutId(int $checkout_id): self
    {
        $this->checkout_id = $checkout_id;
        return $this;
    }

    public function setStatusPayment(?string $status_payment): self
    {
        $this->status_payment = $status_payment;
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