<?php
namespace app\Repository\Reservation;

use app\Models\Reservation\ReservationPayment;
use app\Repository\AbstractRepository;
use app\Repository\Reservation\ReservationRepository as ResRepo;

class ReservationPaymentRepository extends AbstractRepository
{
    public function __construct()
    {
        parent::__construct('reservation_payment');
    }

    /**
     * Retourne tous les paiements (DESC par date de création).
     * @param bool $withReservation
     * @return ReservationPayment[]
     */
    public function findAll(bool $withReservation = false): array
    {
        $sql = "SELECT * FROM $this->tableName ORDER BY created_at DESC";
        $rows = $this->query($sql);

        $list = array_map([$this, 'hydrate'], $rows);
        return $this->hydrateRelations($list, $withReservation);
    }

    /**
     * Trouve un paiement par son ID.
     * @param int $id
     * @param bool $withReservation
     * @return ReservationPayment|null
     */
    public function findById(int $id, bool $withReservation = false): ?ReservationPayment
    {
        $sql = "SELECT * FROM $this->tableName WHERE id = :id";
        $rows = $this->query($sql, ['id' => $id]);
        if (!$rows) return null;

        $p = $this->hydrate($rows[0]);
        return $this->hydrateRelations([$p], $withReservation)[0];
    }

    /**
     * Tous les paiements d'une réservation.
     * @param int $reservationId
     * @param bool $withReservation
     * @return ReservationPayment[]
     */
    public function findByReservation(int $reservationId, bool $withReservation = false): array
    {
        $sql = "SELECT * FROM $this->tableName WHERE reservation = :reservationId ORDER BY created_at";
        $rows = $this->query($sql, ['reservationId' => $reservationId]);

        $list = array_map([$this, 'hydrate'], $rows);
        return $this->hydrateRelations($list, $withReservation);
    }

    /**
     * Paiements pour une liste d'IDs de réservations.
     * @param int[] $reservationIds
     * @param bool $withReservation
     * @return ReservationPayment[]
     */
    public function findByReservations(array $reservationIds, bool $withReservation = false): array
    {
        if (empty($reservationIds)) return [];
        $placeholders = implode(',', array_fill(0, count($reservationIds), '?'));
        $sql = "SELECT * FROM $this->tableName WHERE reservation IN ($placeholders) ORDER BY created_at";
        $rows = $this->query($sql, $reservationIds);

        $list = array_map([$this, 'hydrate'], $rows);
        return $this->hydrateRelations($list, $withReservation);
    }

    /**
     * Insère un nouveau paiement.
     * @return int ID inséré (0 si échec)
     */
    public function insert(ReservationPayment $payment): int
    {
        $sql = "INSERT INTO $this->tableName
            (reservation, type, amount_paid, part_of_donation, checkout_id, order_id, payment_id, status_payment, created_at)
            VALUES (:reservation, :type, :amount_paid, :part_of_donation, :checkout_id, :order_id, :payment_id, :status_payment, :created_at)";

        $ok = $this->execute($sql, [
            'reservation' => $payment->getReservation(),
            'type' => $payment->getType(),
            'amount_paid' => $payment->getAmountPaid(),
            'part_of_donation' => $payment->getPartOfDonation(),
            'checkout_id' => $payment->getCheckoutId(),
            'order_id' => $payment->getOrderId(),
            'payment_id' => $payment->getPaymentId(),
            'status_payment' => $payment->getStatusPayment(),
            'created_at' => $payment->getCreatedAt()->format('Y-m-d H:i:s'),
        ]);

        return $ok ? $this->getLastInsertId() : 0;
    }

    /**
     * Met à jour un paiement.
     * @param ReservationPayment $payment
     * @return bool
     */
    public function update(ReservationPayment $payment): bool
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
            'part_of_donation' => $payment->getPartOfDonation(),
            'checkout_id' => $payment->getCheckoutId(),
            'order_id' => $payment->getOrderId(),
            'payment_id' => $payment->getPaymentId(),
            'status_payment' => $payment->getStatusPayment(),
        ]);
    }

    /**
     * Supprime les paiements d'une réservation.
     * @param int $reservationId
     * @return bool
     */
    public function deleteByReservation(int $reservationId): bool
    {
        $sql = "DELETE FROM $this->tableName WHERE reservation = :reservationId";
        return $this->execute($sql, ['reservationId' => $reservationId]);
    }

    /**
     * Hydrate un paiement (sans relations).
     * @param array $data
     * @return ReservationPayment
     */
    protected function hydrate(array $data): ReservationPayment
    {
        $p = new ReservationPayment();
        $p->setId((int)$data['id'])
            ->setReservation((int)$data['reservation'])
            ->setType($data['type'])
            ->setAmountPaid((int)$data['amount_paid'])
            ->setPartOfDonation(isset($data['part_of_donation']) ? (int)$data['part_of_donation'] : null)
            ->setCheckoutId((int)$data['checkout_id'])
            ->setOrderId((int)$data['order_id'])
            ->setPaymentId((int)$data['payment_id'])
            ->setStatusPayment($data['status_payment'] ?? null)
            ->setCreatedAt($data['created_at']);

        if (!empty($data['updated_at'])) {
            $p->setUpdatedAt($data['updated_at']);
        }
        return $p;
    }

    /**
     * Relations optionnelles
     * @param ReservationPayment[] $payments
     * @return ReservationPayment[]
     */
    private function hydrateRelations(array $payments, bool $withReservation): array
    {
        if (empty($payments)) return [];

        if ($withReservation) {
            $reservationIds = array_values(array_unique(array_map(fn($p) => $p->getReservation(), $payments)));
            $reservationsById = [];
            if ($reservationIds) {
                $resRepo = new ResRepo();
                foreach ($reservationIds as $rid) {
                    $r = $resRepo->findById($rid, false, false, false);
                    if ($r) $reservationsById[$rid] = $r;
                }
            }
            foreach ($payments as $p) {
                if (isset($reservationsById[$p->getReservation()])) {
                    $p->setReservationObject($reservationsById[$p->getReservation()]);
                }
            }
        }

        return $payments;
    }
}
