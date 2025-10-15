<?php
// PHP
namespace app\Repository\Reservation;

use app\Models\Reservation\ReservationComplement;
use app\Repository\AbstractRepository;
use app\Repository\Reservation\ReservationRepository as ResRepo;
use app\Repository\Tarif\TarifRepository;

/**
 * Repository pour la table reservation_complement.
 */
class ReservationComplementRepository extends AbstractRepository
{
    public function __construct()
    {
        parent::__construct('reservation_complement');
    }

    /**
     * Retourne tous les compléments (DESC par date de création).
     * @param bool $withReservation Charger la réservation associée
     * @param bool $withTarif Charger le tarif associé
     * @return ReservationComplement[]
     */
    public function findAll(bool $withReservation = false, bool $withTarif = false): array
    {
        $sql = "SELECT * FROM $this->tableName ORDER BY created_at DESC";
        $rows = $this->query($sql);

        $list = array_map([$this, 'hydrate'], $rows);
        return $this->hydrateRelations($list, $withReservation, $withTarif);
    }

    /**
     * Trouve un complément par son ID.
     * @param int $id
     * @param bool $withReservation
     * @param bool $withTarif
     * @return ReservationComplement|null
     */
    public function findById(int $id, bool $withReservation = false, bool $withTarif = false): ?ReservationComplement
    {
        $sql = "SELECT * FROM $this->tableName WHERE id = :id";
        $rows = $this->query($sql, ['id' => $id]);
        if (!$rows) return null;

        $c = $this->hydrate($rows[0]);
        return $this->hydrateRelations([$c], $withReservation, $withTarif)[0];
    }

    /**
     * Tous les compléments d'une réservation.
     * @param int $reservationId
     * @param bool $withReservation
     * @param bool $withTarif
     * @return ReservationComplement[]
     */
    public function findByReservation(int $reservationId, bool $withReservation = false, bool $withTarif = false): array
    {
        $sql = "SELECT * FROM $this->tableName WHERE reservation = :reservationId ORDER BY created_at";
        $rows = $this->query($sql, ['reservationId' => $reservationId]);

        $list = array_map([$this, 'hydrate'], $rows);
        return $this->hydrateRelations($list, $withReservation, $withTarif);
    }

    /**
     * Trouve un complément par son ID de réservation et son ID de tarif.
     * @param int $reservationId
     * @param int $tarifId
     * @return ReservationComplement|null
     */
    public function findByReservationAndTarif(int $reservationId, int $tarifId): ?ReservationComplement
    {
        $sql = "SELECT * FROM $this->tableName WHERE reservation = :reservationId AND tarif = :tarifId";
        $rows = $this->query($sql, ['reservationId' => $reservationId, 'tarifId' => $tarifId]);
        if (!$rows) {
            return null;
        }
        return $this->hydrate($rows[0]);
    }

    /**
     * Compléments pour une liste d'IDs de réservations.
     * @param int[] $reservationIds
     * @param bool $withReservation
     * @param bool $withTarif
     * @return ReservationComplement[]
     */
    public function findByReservations(array $reservationIds, bool $withReservation = false, bool $withTarif = false): array
    {
        if (empty($reservationIds)) return [];

        $placeholders = implode(',', array_fill(0, count($reservationIds), '?'));
        $sql = "SELECT * FROM $this->tableName WHERE reservation IN ($placeholders) ORDER BY created_at";
        $rows = $this->query($sql, $reservationIds);

        $list = array_map([$this, 'hydrate'], $rows);
        return $this->hydrateRelations($list, $withReservation, $withTarif);
    }

    /**
     * Insère un nouveau complément.
     * @param ReservationComplement $complement
     * @return int ID inséré (0 si échec)
     */
    public function insert(ReservationComplement $complement): int
    {
        $sql = "INSERT INTO $this->tableName
            (reservation, tarif, tarif_access_code, qty, created_at)
            VALUES (:reservation, :tarif, :tarif_access_code, :qty, :created_at)";

        $ok = $this->execute($sql, [
            'reservation' => $complement->getReservation(),
            'tarif' => $complement->getTarif(),
            'tarif_access_code' => $complement->getTarifAccessCode(),
            'qty' => $complement->getQty(),
            'created_at' => $complement->getCreatedAt()->format('Y-m-d H:i:s'),
        ]);

        return $ok ? $this->getLastInsertId() : 0;
    }

    /**
     * Met à jour un complément.
     * @param ReservationComplement $complement
     * @return bool
     */
    public function update(ReservationComplement $complement): bool
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
            'qty' => $complement->getQty(),
        ]);
    }

    /**
     * Supprime tous les compléments d'une réservation.
     * @param int $reservationId
     * @return bool
     */
    public function deleteByReservation(int $reservationId): bool
    {
        $sql = "DELETE FROM $this->tableName WHERE reservation = :reservationId";
        return $this->execute($sql, ['reservationId' => $reservationId]);
    }

    /**
     * Hydrate un complément (sans relations).
     * @param array<string,mixed> $data
     * @return ReservationComplement
     */
    protected function hydrate(array $data): ReservationComplement
    {
        $c = new ReservationComplement();
        $c->setId((int)$data['id'])
            ->setReservation((int)$data['reservation'])
            ->setTarif((int)$data['tarif'])
            ->setTarifAccessCode($data['tarif_access_code'] ?? null)
            ->setQty((int)$data['qty'])
            ->setCreatedAt($data['created_at']);

        if (!empty($data['updated_at'])) {
            $c->setUpdatedAt($data['updated_at']);
        }
        return $c;
    }

    /**
     * Hydrate relations optionnelles en masse (évite N+1).
     * @param ReservationComplement[] $complements
     * @param bool $withReservation
     * @param bool $withTarif
     * @return ReservationComplement[]
     */
    private function hydrateRelations(array $complements, bool $withReservation, bool $withTarif): array
    {
        if (empty($complements)) return [];

        $reservationIds = $withReservation
            ? array_values(array_unique(array_map(fn($c) => $c->getReservation(), $complements)))
            : [];
        $tarifIds = $withTarif
            ? array_values(array_unique(array_map(fn($c) => $c->getTarif(), $complements)))
            : [];

        $reservationsById = [];
        if ($withReservation && $reservationIds) {
            $resRepo = new ResRepo();
            foreach ($reservationIds as $rid) {
                $r = $resRepo->findById($rid, false, false, false);
                if ($r) $reservationsById[$rid] = $r;
            }
        }

        $tarifsById = [];
        if ($withTarif && $tarifIds) {
            $tarifRepo = new TarifRepository();
            if (method_exists($tarifRepo, 'findByIds')) {
                foreach ($tarifRepo->findByIds($tarifIds) as $t) {
                    $tarifsById[$t->getId()] = $t;
                }
            } else {
                foreach ($tarifIds as $tid) {
                    $t = $tarifRepo->findById($tid);
                    if ($t) $tarifsById[$tid] = $t;
                }
            }
        }

        foreach ($complements as $c) {
            if ($withReservation && isset($reservationsById[$c->getReservation()])) {
                $c->setReservationObject($reservationsById[$c->getReservation()]);
            }
            if ($withTarif && isset($tarifsById[$c->getTarif()])) {
                $c->setTarifObject($tarifsById[$c->getTarif()]);
            }
        }

        return $complements;
    }
}
