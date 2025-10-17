<?php
namespace app\Models\Reservation;

use app\Models\AbstractModel;
use DateTime;
use DateTimeInterface;

class ReservationPayment extends AbstractModel
{
    private int $reservation;
    private ?Reservation $reservationObject = null;
    private string $type;
    private int $amount_paid;
    private ?int $part_of_donation = null;
    private int $checkout_id;
    private int $order_id;
    private int $payment_id;
    private ?string $status_payment = null;

    // --- GETTERS ---
    public function getReservation(): int { return $this->reservation; }
    public function getReservationObject(): ?Reservation { return $this->reservationObject; }
    public function getType(): string { return $this->type; }
    public function getAmountPaid(): int { return $this->amount_paid; }
    public function getPartOfDonation(): ?int { return $this->part_of_donation; }
    public function getCheckoutId(): int { return $this->checkout_id; }
    public function getOrderId(): int { return $this->order_id; }
    public function getPaymentId(): int { return $this->payment_id; }
    public function getStatusPayment(): ?string { return $this->status_payment; }

    // --- SETTERS ---
    public function setReservation(int $reservation): self { $this->reservation = $reservation; return $this; }
    public function setReservationObject(?Reservation $reservationObject): self
    {
        $this->reservationObject = $reservationObject;
        if ($reservationObject) { $this->reservation = $reservationObject->getId(); }
        return $this;
    }
    public function setType(string $type): self { $this->type = $type; return $this; }
    public function setAmountPaid(int $amount_paid): self { $this->amount_paid = $amount_paid; return $this; }
    public function setPartOfDonation(?int $part_of_donation): self { $this->part_of_donation = $part_of_donation; return $this; }
    public function setCheckoutId(int $checkout_id): self { $this->checkout_id = $checkout_id; return $this; }
    public function setOrderId(int $order_id): self { $this->order_id = $order_id; return $this; }
    public function setPaymentId(int $payment_id): self { $this->payment_id = $payment_id; return $this; }
    public function setStatusPayment(?string $status_payment): self { $this->status_payment = $status_payment; return $this; }

    /**
     * Convertit l'objet en tableau pour la réponse JSON.
     * @return array
     */
    public function toArray(): array
    {
        return [
            'id' => $this->getId(),
            'type' => $this->getType(),
            'amountPaid' => $this->getAmountPaid(),
            'partOfDonation' => $this->getPartOfDonation(),
            'status' => $this->getStatusPayment(),
            'createdAt' => $this->getCreatedAt()?->format(DateTimeInterface::ATOM),
        ];
    }

}
