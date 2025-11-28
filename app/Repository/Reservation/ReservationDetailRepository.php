<?php
// PHP
namespace app\Repository\Reservation;

use app\Models\Reservation\ReservationDetail;
use app\Repository\AbstractRepository;
use app\Repository\Piscine\PiscineGradinsPlacesRepository;
use app\Repository\Tarif\TarifRepository;
use app\Repository\Reservation\ReservationRepository as ResRepo;

class ReservationDetailRepository extends AbstractRepository
{
    public function __construct()
    {
        parent::__construct('reservation_detail');
    }

    /**
     * Retourne tous les détails = places assises
     * @param bool $withReservation
     * @param bool $withTarif
     * @param bool $withPlace
     * @return ReservationDetail[]
     */
    public function findAll(
        bool $withReservation = false,
        bool $withTarif = false,
        bool $withPlace = false
    ): array {
        $sql = "SELECT * FROM $this->tableName ORDER BY created_at DESC";
        $rows = $this->query($sql);
        $details = array_map([$this, 'hydrate'], $rows);
        return $this->hydrateRelations($details, $withReservation, $withTarif, $withPlace);
    }

    /**
     * Trouve un détail par son ID
     * @param int $id
     * @param bool $withReservation
     * @param bool $withTarif
     * @param bool $withPlace
     * @return ReservationDetail|null
     */
    public function findById(
        int $id,
        bool $withReservation = false,
        bool $withTarif = false,
        bool $withPlace = false
    ): ?ReservationDetail {
        $sql = "SELECT * FROM $this->tableName WHERE id = :id";
        $rows = $this->query($sql, ['id' => $id]);
        if (!$rows) return null;

        $d = $this->hydrate($rows[0]);
        return $this->hydrateRelations([$d], $withReservation, $withTarif, $withPlace)[0];
    }

    /**
     * Tous les détails d'une réservation
     * @param int $reservationId
     * @param bool $withReservation
     * @param bool $withTarif
     * @param bool $withPlace
     * @return ReservationDetail[]
     */
    public function findByReservation(
        int $reservationId,
        bool $withReservation = false,
        bool $withTarif = false,
        bool $withPlace = false
    ): array {
        $sql = "SELECT * FROM $this->tableName WHERE reservation = :reservationId ORDER BY created_at";
        $rows = $this->query($sql, ['reservationId' => $reservationId]);
        $details = array_map([$this, 'hydrate'], $rows);
        return $this->hydrateRelations($details, $withReservation, $withTarif, $withPlace);
    }

    /**
     * Trouve les détails par ID de place (colonne place_number contenant l'ID de la place)
     * @param int $placeId
     * @param bool $withReservation
     * @param bool $withTarif
     * @param bool $withPlace
     * @return ReservationDetail|null
     */
    public function findByPlaceNumber(
        int $placeId,
        bool $withReservation = false,
        bool $withTarif = false,
        bool $withPlace = false
    ): ?ReservationDetail {
        $sql = "SELECT * FROM $this->tableName WHERE place_number = :placeNumber ORDER BY created_at DESC LIMIT 1";
        $rows = $this->query($sql, ['placeNumber' => $placeId]);
        if (!$rows) return null;

        $d = $this->hydrate($rows[0]);
        $hydrated = $this->hydrateRelations([$d], $withReservation, $withTarif, $withPlace);
        return $hydrated[0] ?? null;
    }

    /**
     * Tous les IDs de places déjà réservées pour une session
     * Retourne un tableau plat d'IDs de places (colonne place_number).
     * @param int $sessionId
     * @return array
     */
    public function findReservedSeatsForSession(int $sessionId): array
    {
        $sql = "SELECT rd.place_number
                 FROM reservation_detail rd
                 INNER JOIN reservation r ON rd.reservation = r.id
                 WHERE r.event_session = :sessionId
                   AND r.is_canceled = 0
                   AND rd.place_number IS NOT NULL";
        $results = $this->query($sql, ['sessionId' => $sessionId]);
        return array_column($results, 'place_number');
    }

    /**
     * Compte le nombre de détails pour une réservation
     * @param int $reservationId
     * @return int
     */
    public function countByReservation(int $reservationId): int
    {
        $sql = "SELECT COUNT(*) as count FROM $this->tableName WHERE reservation = :reservationId";
        $result = $this->query($sql, ['reservationId' => $reservationId]);
        return (int)($result[0]['count'] ?? 0);
    }

    /**
     * Compte le nombre de personnes (de détails) pour une session
     * @param int $sessionId
     * @return int
     */
    public function countBySession(int $sessionId): int
    {
        $sql = "SELECT COUNT(*) as count 
            FROM $this->tableName rd
            INNER JOIN reservation r ON rd.reservation = r.id
            WHERE r.event_session = :sessionId
              AND r.is_canceled = 0;";
        $result = $this->query($sql, ['sessionId' => $sessionId]);
        return (int)($result[0]['count'] ?? 0);
    }

    /**
     * Détails pour une liste d'IDs de réservation.
     * @param int[] $reservationIds
     * @param bool $withReservation
     * @param bool $withTarif
     * @param bool $withPlace
     * @return ReservationDetail[]
     */
    public function findByReservations(
        array $reservationIds,
        bool $withReservation = false,
        bool $withTarif = false,
        bool $withPlace = false
    ): array {
        if (empty($reservationIds)) return [];

        $placeholders = implode(',', array_fill(0, count($reservationIds), '?'));
        $sql = "SELECT * FROM $this->tableName WHERE reservation IN ($placeholders) ORDER BY created_at";
        $rows = $this->query($sql, $reservationIds);

        $details = array_map([$this, 'hydrate'], $rows);
        return $this->hydrateRelations($details, $withReservation, $withTarif, $withPlace);
    }

    /**
     * Insère un nouveau détail
     * @return int ID inséré
     */
    public function insert(ReservationDetail $detail): int
    {
        $placeId = $detail->getPlaceObject()?->getId()
            ?? (is_numeric($detail->getPlaceNumber() ?? null) ? (int)$detail->getPlaceNumber() : null);

        $sql = "INSERT INTO $this->tableName
            (reservation, name, firstname, tarif, tarif_access_code, justificatif_name, place_number, created_at)
            VALUES (:reservation, :name, :firstname, :tarif, :tarif_access_code, :justificatif_name, :place_number, :created_at)";

        $ok = $this->execute($sql, [
            'reservation' => $detail->getReservation(),
            'name' => $detail->getName(),
            'firstname' => $detail->getFirstName(),
            'tarif' => $detail->getTarif(),
            'tarif_access_code' => $detail->getTarifAccessCode(),
            'justificatif_name' => $detail->getJustificatifName(),
            'place_number' => $placeId,
            'created_at' => $detail->getCreatedAt()->format('Y-m-d H:i:s'),
        ]);

        return $ok ? $this->getLastInsertId() : 0;
    }

    /**
     * Met à jour un détail
     */
    public function update(ReservationDetail $detail): bool
    {
        $placeId = $detail->getPlaceObject()?->getId()
            ?? (is_numeric($detail->getPlaceNumber() ?? null) ? (int)$detail->getPlaceNumber() : null);

        $sql = "UPDATE $this->tableName SET 
            reservation = :reservation,
            name = :name,
            firstname = :firstname,
            tarif = :tarif,
            tarif_access_code = :tarif_access_code,
            justificatif_name = :justificatif_name,
            place_number = :place_number,
            updated_at = NOW()
            WHERE id = :id";

        return $this->execute($sql, [
            'id' => $detail->getId(),
            'reservation' => $detail->getReservation(),
            'name' => $detail->getName(),
            'firstname' => $detail->getFirstName(),
            'tarif' => $detail->getTarif(),
            'tarif_access_code' => $detail->getTarifAccessCode(),
            'justificatif_name' => $detail->getJustificatifName(),
            'place_number' => $placeId,
        ]);
    }

    /**
     * Met à jour un seul champ (liste blanche)
     * @param int $id
     * @param string $field
     * @param string|null $value
     * @return bool
     */
    public function updateSingleField(int $id, string $field, ?string $value): bool
    {
        $sql = "UPDATE $this->tableName SET `$field` = :value, updated_at = NOW() WHERE id = :id";
        return $this->execute($sql, ['id' => $id, 'value' => $value]);
    }

    /**
     * Met les places à NULL lors d'une annulation
     * @param int $reservationId
     * @return bool
     */
    public function cancelByReservation(int $reservationId): bool
    {
        $sql = "UPDATE $this->tableName SET place_number = NULL, updated_at = NOW() WHERE reservation = :reservationId";
        return $this->execute($sql, ['reservationId' => $reservationId]);
    }

    /**
     * Supprime tous les détails d'une réservation
     * @param int $reservationId
     * @return bool
     */
    public function deleteByReservation(int $reservationId): bool
    {
        $sql = "DELETE FROM $this->tableName WHERE reservation = :reservationId";
        return $this->execute($sql, ['reservationId' => $reservationId]);
    }

    /**
     * Hydrate un détail (sans relations)
     * @param array $data
     * @return ReservationDetail
     */
    protected function hydrate(array $data): ReservationDetail
    {
        $d = new ReservationDetail();
        $d->setId((int)$data['id'])
            ->setReservation((int)$data['reservation'])
            ->setName($data['name'] ?? null)
            ->setFirstName($data['firstname'] ?? null)
            ->setTarif((int)$data['tarif'])
            ->setTarifAccessCode($data['tarif_access_code'] ?? null)
            ->setJustificatifName($data['justificatif_name'] ?? null)
            // Par défaut, on stocke l'ID de la place (remplacé par le numéro réel si withPlace=true).
            ->setPlaceNumber(isset($data['place_number']) ? (string)$data['place_number'] : null)
            ->setEnteredAt($data['entered_at'] ?? null)
            ->setCreatedAt($data['created_at']);

        if (!empty($data['updated_at'])) {
            $d->setUpdatedAt($data['updated_at']);
        }
        return $d;
    }

    /**
     * Hydrate les relations en masse pour éviter le N+1.
     * @param ReservationDetail[] $details
     * @param bool $withReservation
     * @param bool $withTarif
     * @param bool $withPlace
     * @return ReservationDetail[]
     */
    private function hydrateRelations(
        array $details,
        bool $withReservation,
        bool $withTarif,
        bool $withPlace
    ): array {
        if (empty($details)) return [];

        // Indexation par IDs
        $reservationIds = $withReservation ? array_values(array_unique(array_map(fn($d) => $d->getReservation(), $details))) : [];
        $tarifIds = $withTarif ? array_values(array_unique(array_map(fn($d) => $d->getTarif(), $details))) : [];
        $placeIds = $withPlace ? array_values(array_unique(array_filter(array_map(
            fn($d) => is_numeric($d->getPlaceNumber() ?? null) ? (int)$d->getPlaceNumber() : null,
            $details
        )))) : [];

        // Chargements
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
            // Suppose l'existence de findByIds(). Si indisponible, fallback naïf:
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

        $placesById = [];
        if ($withPlace && $placeIds) {
            $placesRepo = new PiscineGradinsPlacesRepository();
            foreach ($placeIds as $pid) {
                $p = $placesRepo->findById($pid, true);
                if ($p) $placesById[$pid] = $p;
            }
        }

        // Attachements
        foreach ($details as $d) {
            if ($withReservation && isset($reservationsById[$d->getReservation()])) {
                $d->setReservationObject($reservationsById[$d->getReservation()]);
            }
            if ($withTarif && isset($tarifsById[$d->getTarif()])) {
                $d->setTarifObject($tarifsById[$d->getTarif()]);
            }
            if ($withPlace) {
                $pid = is_numeric($d->getPlaceNumber() ?? null) ? (int)$d->getPlaceNumber() : null;
                if ($pid && isset($placesById[$pid])) {
                    $place = $placesById[$pid];
                    $d->setPlaceObject($place);
                    // Remplace la valeur par le numéro de place lisible
                    $d->setPlaceNumber($place->getPlaceNumber());
                }
            }
        }

        return $details;
    }
}
