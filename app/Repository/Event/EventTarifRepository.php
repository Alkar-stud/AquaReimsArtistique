<?php
// php
namespace app\Repository\Event;

use app\Models\Tarif\Tarif;
use app\Repository\AbstractRepository;
use app\Repository\Tarif\TarifRepository;

class EventTarifRepository extends AbstractRepository
{
    private TarifRepository $tarifRepository;

    public function __construct(
        TarifRepository $tarifRepository,
    )
    {
        parent::__construct('event_tarif');
        $this->tarifRepository = $tarifRepository;
    }

    /**
     * Attache un tarif à un événement
     * @param int $eventId
     * @param int $tarifId
     * @return bool
     */
    public function attach(int $eventId, int $tarifId): bool
    {
        $sql = "INSERT INTO $this->tableName (`event`, `tarif`) VALUES (:event, :tarif)";
        return $this->execute($sql, ['event' => $eventId, 'tarif' => $tarifId]);
    }

    /**
     * Détache un tarif d’un événement
     * @param int $eventId
     * @param int $tarifId
     * @return bool
     */
    public function detach(int $eventId, int $tarifId): bool
    {
        $sql = "DELETE FROM $this->tableName WHERE `event` = :event AND `tarif` = :tarif";
        return $this->execute($sql, ['event' => $eventId, 'tarif' => $tarifId]);
    }

    /**
     * Détache tous les tarifs d’un événement
     * @param int $eventId
     * @return bool
     */
    public function detachAllForEvent(int $eventId): bool
    {
        $sql = "DELETE FROM $this->tableName WHERE `event` = :event";
        return $this->execute($sql, ['event' => $eventId]);
    }

    /**
     * Retourne la liste des tarifs pour un événement
     * @return int[]
     */
    public function listTarifIdsForEvent(int $eventId): array
    {
        $sql = "SELECT `tarif` FROM $this->tableName WHERE `event` = :event ORDER BY `tarif`";
        $rows = $this->query($sql, ['event' => $eventId]);
        return array_map(fn($r) => (int)$r['tarif'], $rows);
    }

    /**
     * Retourne les tarifs avec places ou sans pour un événement.
     * Retourne une liste (array indexé numériquement).
     *
     * @param int $eventId
     * @param bool $withSeat
     * @return Tarif[]|array<int,Tarif>
     */
    public function findTarifsByEvent(int $eventId, bool $withSeat = true): array
    {
        $withSeat === true ? $sqlWithSeat = ' AND t.seat_count IS NOT NULL':$sqlWithSeat = ' AND t.seat_count IS NULL';
        $sql = "SELECT t.*
                FROM tarif t
                INNER JOIN event_tarif et ON et.tarif = t.id
                WHERE et.event = :event_id AND t.is_active = 1" . $sqlWithSeat . "
                ORDER BY t.seat_count DESC, t.name";

        $rows = $this->query($sql, ['event_id' => $eventId]);

        //On indexe le tableau par ID
        $rowsMapped = array();
        foreach ($rows as $row) {
            $rowsMapped[$row['id']] = $row;
        }

        // Hydratation centralisée via TarifRepository
        return array_map([$this->tarifRepository, 'hydrateFromRow'], $rowsMapped);
    }

    /**
     * Vérifie si un tarif est déjà associé à un événement
     * @param int $eventId
     * @param int $tarifId
     * @return bool
     */
    public function exists(int $eventId, int $tarifId): bool
    {
        $sql = "SELECT 1 FROM $this->tableName WHERE `event` = :event AND `tarif` = :tarif";
        return !empty($this->query($sql, ['event' => $eventId, 'tarif' => $tarifId]));
    }

    /**
     * Remplace la liste des tarifs d’un événement (transactionnel)
     *
     * @param int $eventId
     * @param array $tarifIds
     * @return bool
     */
    public function replaceForEvent(int $eventId, array $tarifIds): bool
    {
        $tarifIds = array_values(array_unique(array_map('intval', $tarifIds)));

        // Purger les anciens tarifs pour cet événement.
        $this->detachAllForEvent($eventId);

        // Ré-attacher les nouveaux tarifs.
        if (!empty($tarifIds)) {
            $sql = "INSERT INTO $this->tableName (`event`, `tarif`) VALUES (:event, :tarif)";
            $stmt = $this->pdo->prepare($sql);
            foreach ($tarifIds as $tid) {
                $stmt->execute(['event' => $eventId, 'tarif' => $tid]);
            }
        }
        return true;
    }
}
