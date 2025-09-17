<?php

namespace app\Repository\Reservation;

use app\Models\Reservation\ReservationPayments;
use app\Repository\AbstractRepository;
use DateMalformedStringException;

class ReservationPaymentsRepository extends AbstractRepository
{
    public function __construct()
    {
        parent::__construct('reservations_payments');
    }

    /**
     * Trouve tous les paiements
     * @return ReservationPayments[]
     */
    public function findAll(): array
    {
        $sql = "SELECT * FROM $this->tableName ORDER BY created_at DESC";
        $results = $this->query($sql);
        return array_map([$this, 'hydrate'], $results);
    }

    /**
     * Trouve un paiement par son ID
     * @param int $id
     * @return ReservationPayments|null
     * @throws DateMalformedStringException
     */
    public function findById(int $id): ?ReservationPayments
    {
        $sql = "SELECT * FROM $this->tableName WHERE id = :id";
        $result = $this->query($sql, ['id' => $id]);

        if (!$result) {
            return null;
        }

        return $this->hydrate($result[0]);
    }

    /**
     * Trouve tous les paiements pour une réservation
     * @param int $reservationId
     * @return ReservationPayments[]
     */
    public function findByReservation(int $reservationId): array
    {
        $sql = "SELECT * FROM $this->tableName WHERE reservation = :reservationId ORDER BY created_at DESC";
        $results = $this->query($sql, ['reservationId' => $reservationId]);
        return array_map([$this, 'hydrate'], $results);
    }

    /**
     * Trouve un paiement par son checkoutIntentId
     * @param int $checkoutId
     * @return ReservationPayments|null
     * @throws DateMalformedStringException
     */
    public function findByCheckoutId(int $checkoutId): ?ReservationPayments
    {
        $sql = "SELECT * FROM $this->tableName WHERE checkout_id = :checkoutId LIMIT 1";
        $result = $this->query($sql, ['checkoutId' => $checkoutId]);

        if (empty($result)) {
            return null;
        }
        return $this->hydrate($result[0]);
    }

    /**
     * Trouve un paiement par son id de paiement
     * @param int $paymentId
     * @return ReservationPayments|null
     * @throws DateMalformedStringException
     */
    public function findByPaymentId(int $paymentId): ?ReservationPayments
    {
        $sql = "SELECT * FROM $this->tableName WHERE payment_id = :payment_id LIMIT 1";
        $result = $this->query($sql, ['payment_id' => $paymentId]);

        if (empty($result)) {
            return null;
        }
        return $this->hydrate($result[0]);
    }


    /**
     * Trouve un paiement par son order id
     * @param int $orderId
     * @return ReservationPayments|null
     * @throws DateMalformedStringException
     */
    public function findByOrderId(int $orderId): ?ReservationPayments
    {
        $sql = "SELECT * FROM $this->tableName WHERE order_id = :order_id LIMIT 1";
        $result = $this->query($sql, ['order_id' => $orderId]);

        if (empty($result)) {
            return null;
        }
        return $this->hydrate($result[0]);
    }


    /**
     * Somme des paiements pour une réservation
     * @param int $reservationId
     * @return int
     */
    public function getTotalAmountPaidForReservation(int $reservationId): int
    {
        $sql = "SELECT SUM(amount_paid) as total FROM $this->tableName WHERE reservation = :reservationId";
        $result = $this->query($sql, ['reservationId' => $reservationId]);

        if (!$result || !isset($result[0]['total'])) {
            return 0.0;
        }

        return (int)$result[0]['total'];
    }

    /**
     * Insère un nouveau paiement
     * @param ReservationPayments $payment
     * @return int ID du paiement inséré
     */
    public function insert(ReservationPayments $payment): int
    {
        $sql = "INSERT INTO $this->tableName
            (reservation, type, amount_paid, part_of_donation, checkout_id, order_id, payment_id, status_payment, created_at)
            VALUES (:reservation, :type, :amount_paid, :part_of_donation, :checkout_id, :order_id, :payment_id, :status_payment, :created_at)";

        $this->execute($sql, [
            'reservation' => $payment->getReservation(),
            'type' => $payment->getType(),
            'amount_paid' => $payment->getAmountPaid(),
            'part_of_donation' => $payment->getPartOfDonation(),
            'checkout_id' => $payment->getCheckoutId(),
            'order_id' => $payment->getOrderId(),
            'payment_id' => $payment->getPaymentId(),
            'status_payment' => $payment->getStatusPayment(),
            'created_at' => $payment->getCreatedAt()->format('Y-m-d H:i:s')
        ]);

        return $this->getLastInsertId();
    }

    /**
     * Met à jour un paiement
     * @param ReservationPayments $payment
     * @return bool Succès de la mise à jour
     */
    public function update(ReservationPayments $payment): bool
    {
        $sql = "UPDATE $this->tableName SET
        reservation = :reservation,
        type = :type,
        amount_paid = :amount_paid,
        part_of_donation = :part_of_donation,
        checkout_id = :checkout_id,
        order_id = :order_id,
        payment_id = :payment_id,
        status_payment = :status_payment,
        updated_at = NOW()
        WHERE id = :id";

        return $this->execute($sql, [
            'id' => $payment->getId(),
            'reservation' => $payment->getReservation(),
            'type' => $payment->getType(),
            'amount_paid' => $payment->getAmountPaid(),
            'part_of_donation' => $payment->getAmountPaid(),
            'checkout_id' => $payment->getCheckoutId(),
            'order_id' => $payment->getOrderId(),
            'payment_id' => $payment->getPaymentId(),
            'status_payment' => $payment->getStatusPayment()
        ]);
    }

    /**
     * Met à jour le statut d'un paiement
     * @param int $id
     * @param string $status
     * @return bool
     */
    public function updateStatus(int $id, string $status): bool
    {
        $sql = "UPDATE $this->tableName SET status_payment = :status, updated_at = NOW() WHERE id = :id";
        return $this->execute($sql, ['id' => $id, 'status' => $status]);
    }

    /**
     * Hydrate un objet ReservationPayments à partir d'un tableau de données
     * @param array $data
     * @return ReservationPayments
     * @throws DateMalformedStringException
     */
    protected function hydrate(array $data): ReservationPayments
    {
        $payment = new ReservationPayments();
        $payment->setId($data['id'])
            ->setReservation($data['reservation'])
            ->setType($data['type'])
            ->setOrderId($data['order_id'])
            ->setPaymentId($data['payment_id'])
            ->setAmountPaid($data['amount_paid'])
            ->setPartOfDonation($data['part_of_donation'])
            ->setCheckoutId($data['checkout_id'])
            ->setStatusPayment($data['status_payment'])
            ->setCreatedAt($data['created_at']);

        if (isset($data['updated_at'])) {
            $payment->setUpdatedAt($data['updated_at']);
        }

        return $payment;
    }
}