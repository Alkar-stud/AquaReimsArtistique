<?php

namespace app\Repository\Reservation;

use app\Models\Reservation\ReservationsComplements;
use app\Repository\AbstractRepository;
use DateMalformedStringException;

class ReservationsComplementsRepository extends AbstractRepository
{
    public function __construct()
    {
        parent::__construct('reservations_complements');
    }

    /**
     * Trouve tous les compléments de réservations
     * @return ReservationsComplements[]
     */
    public function findAll(): array
    {
        $sql = "SELECT * FROM $this->tableName ORDER BY created_at DESC";
        $results = $this->query($sql);
        return array_map([$this, 'hydrate'], $results);
    }

    /**
     * Trouve un complément de réservation par son ID
     * @param int $id
     * @return ReservationsComplements|null
     * @throws DateMalformedStringException
     */
    public function findById(int $id): ?ReservationsComplements
    {
        $sql = "SELECT * FROM $this->tableName WHERE id = :id";
        $result = $this->query($sql, ['id' => $id]);

        if (!$result) {
            return null;
        }

        return $this->hydrate($result[0]);
    }

    /**
     * Trouve tous les compléments pour une réservation
     * @param int $reservationId
     * @return ReservationsComplements[]
     */
    public function findByReservation(int $reservationId): array
    {
        $sql = "SELECT * FROM $this->tableName WHERE reservation = :reservationId ORDER BY created_at";
        $results = $this->query($sql, ['reservationId' => $reservationId]);
        return array_map([$this, 'hydrate'], $results);
    }

    /**
     * Trouve tous les compléments pour une réservation
     *
     * @param int $reservationId
     * @param $tarifId
     * @return ReservationsComplements[]
     */
    public function findByReservationAndTarif(int $reservationId, $tarifId): array
    {
        $sql = "SELECT * FROM $this->tableName WHERE reservation = :reservationId AND tarif = :tarifId ORDER BY created_at";
        $results = $this->query($sql, ['reservationId' => $reservationId, 'tarifId' => $tarifId]);
        return array_map([$this, 'hydrate'], $results);
    }

    /**
     * Insère un nouveau complément de réservation
     * @param ReservationsComplements $complement
     * @return int ID du complément inséré
     */
    public function insert(ReservationsComplements $complement): int
    {
        $sql = "INSERT INTO $this->tableName
            (reservation, tarif, tarif_access_code, qty, created_at)
            VALUES (:reservation, :tarif, :tarif_access_code, :qty, :created_at)";

        $this->execute($sql, [
            'reservation' => $complement->getReservation(),
            'tarif' => $complement->getTarif(),
            'tarif_access_code' => $complement->getTarifAccessCode(),
            'qty' => $complement->getQty(),
            'created_at' => $complement->getCreatedAt()->format('Y-m-d H:i:s')
        ]);

        return $this->getLastInsertId();
    }

    /**
     * Met à jour un complément de réservation
     * @param ReservationsComplements $complement
     * @return bool Succès de la mise à jour
     */
    public function update(ReservationsComplements $complement): bool
    {
        $sql = "UPDATE $this->tableName SET 
            reservation = :reservation,
            tarif = :tarif,
            tarif_access_code = :tarif_access_code,
            qty = :qty,
            updated_at = NOW()
            WHERE id = :id";

        return $this->execute($sql, [
            'id' => $complement->getId(),
            'reservation' => $complement->getReservation(),
            'tarif' => $complement->getTarif(),
            'tarif_access_code' => $complement->getTarifAccessCode(),
            'qty' => $complement->getQty()
        ]);
    }

    /**
     * Met à jour un seul champ d'un détail de réservation.
     *
     * @param int $id L'ID du détail
     * @param string|null $tarif_access_code
     * @param int $qty
     * @return bool
     */
    public function updateQuantity(int $id, ?string $tarif_access_code, int $qty): bool
    {
        // la liste blanche ci-dessus sert de protection.
        $sql = "UPDATE $this->tableName SET `qty` = :qty, `tarif_access_code` = :tarif_access_code WHERE id = :id";

        return $this->execute($sql, ['id' => $id, 'qty' => $qty, 'tarif_access_code' => $tarif_access_code]);
    }

    /**
     * Supprime un complément de réservation
     * @param int $id
     * @return bool
     */
    public function delete(int $id): bool
    {
        $sql = "DELETE FROM $this->tableName WHERE id = :id";
        return $this->execute($sql, ['id' => $id]);
    }

    /**
     * Supprime tous les compléments d'une réservation
     * @param int $reservationId
     * @return bool
     */
    public function deleteByReservation(int $reservationId): bool
    {
        $sql = "DELETE FROM $this->tableName WHERE reservation = :reservationId";
        return $this->execute($sql, ['reservationId' => $reservationId]);
    }

    /**
     * Hydrate un objet ReservationsComplements à partir d'un tableau de données
     * @param array $data
     * @return ReservationsComplements
     * @throws DateMalformedStringException
     */
    protected function hydrate(array $data): ReservationsComplements
    {
        $complement = new ReservationsComplements();
        $complement->setId($data['id'])
            ->setReservation($data['reservation'])
            ->setTarif($data['tarif'])
            ->setTarifAccessCode($data['tarif_access_code'])
            ->setQty($data['qty'])
            ->setCreatedAt($data['created_at'])
            ->setUpdatedAt($data['updated_at']);

        return $complement;
    }
}