<?php
// php
namespace app\Repository\Event;

use app\Repository\AbstractRepository;
use Throwable;

class EventTarifRepository extends AbstractRepository
{
    public function __construct()
    {
        parent::__construct('event_tarif');
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
