<?php

namespace app\Repository;

use app\Models\ReservationPayments;
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
     * Somme des paiements pour une réservation
     * @param int $reservationId
     * @return float
     */
    public function getTotalAmountPaidForReservation(int $reservationId): float
    {
        $sql = "SELECT SUM(amount_paid) as total FROM $this->tableName WHERE reservation = :reservationId";
        $result = $this->query($sql, ['reservationId' => $reservationId]);

        if (!$result || !isset($result[0]['total'])) {
            return 0.0;
        }

        return (float)$result[0]['total'];
    }

    /**
     * Insère un nouveau paiement
     * @param ReservationPayments $payment
     * @return int ID du paiement inséré
     */
    public function insert(ReservationPayments $payment): int
    {
        $sql = "INSERT INTO $this->tableName
            (reservation, amount_paid, checkout_id, status_payment, created_at)
            VALUES (:reservation, :amount_paid, :checkout_id, :status_payment, :created_at)";

        $this->execute($sql, [
            'reservation' => $payment->getReservation(),
            'amount_paid' => $payment->getAmountPaid(),
            'checkout_id' => $payment->getCheckoutId(),
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
        amount_paid = :amount_paid,
        checkout_id = :checkout_id,
        status_payment = :status_payment,
        updated_at = NOW()
        WHERE id = :id";

        return $this->execute($sql, [
            'id' => $payment->getId(),
            'reservation' => $payment->getReservation(),
            'amount_paid' => $payment->getAmountPaid(),
            'checkout_id' => $payment->getCheckoutId(),
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
            ->setAmountPaid($data['amount_paid'])
            ->setCheckoutId($data['checkout_id'])
            ->setStatusPayment($data['status_payment'])
            ->setCreatedAt($data['created_at']);

        if (isset($data['updated_at'])) {
            $payment->setUpdatedAt($data['updated_at']);
        }

        return $payment;
    }
}