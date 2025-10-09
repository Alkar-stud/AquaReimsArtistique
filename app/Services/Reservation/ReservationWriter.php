<?php
namespace app\Services\Reservation;

use app\Services\Log\Logger;
use app\Services\Mongo\MongoReservationStorage;
use app\Services\Sleek\SleekReservationStorage;
use app\Utils\NsqlIdGenerator;
use Throwable;

/**
 * Orchestre l'écriture sur plusieurs systèmes de stockage.
 * Le premier stockage de la liste est considéré comme le "primaire" (source de vérité).
 * Les suivants sont des "secondaires" synchronisés en "best effort".
 */
final class ReservationWriter implements ReservationStorageInterface
{
    private ReservationStorageInterface $primaryStorage;
    /** @var ReservationStorageInterface[] */
    private array $secondaryStorages;

    public function __construct() {
        // SleekDB est le primaire, MongoDB est le secondaire.
        $this->primaryStorage = new SleekReservationStorage();
        $this->secondaryStorages = [new MongoReservationStorage()];
    }

    public function saveReservation(array $reservation): string
    {
        // Assure un identifiant logique commun aux deux backends
        $reservation['nsql_id'] = $reservation['nsql_id'] ?? NsqlIdGenerator::new();

        // Écriture sur le stockage primaire (source de vérité)
        $primaryId = $this->primaryStorage->saveReservation($reservation);

        // Réplication sur les stockages secondaires (best effort)
        foreach ($this->secondaryStorages as $index => $storage) {
            try {
                // On s'assure que les données répliquées contiennent l'ID du primaire si nécessaire
                $replicatedData = $reservation;
                // Certains systèmes pourraient vouloir connaître l'ID du primaire
                $replicatedData['primary_id'] = $primaryId;
                $storage->saveReservation($replicatedData);
            } catch (Throwable $e) {
                Logger::get()->error('storage_replication', 'Failed to save on secondary storage #' . $index, ['exception' => $e]);
            }
        }

        return $primaryId;
    }

    public function findReservationById(string $id): ?array
    {
        // On cherche d'abord dans le primaire
        $doc = $this->primaryStorage->findReservationById($id);
        if ($doc !== null) {
            return $doc;
        }

        // Fallback: si non trouvé, on tente de chercher dans les secondaires.
        // Utile en cas de panne du primaire.
        foreach ($this->secondaryStorages as $index => $storage) {
            try {
                $doc = $storage->findReservationById($id);
                if ($doc !== null) {
                    Logger::get()->warning('storage_fallback', 'Found document on secondary storage #' . $index . ' after primary failed.', ['id' => $id]);
                    return $doc;
                }
            } catch (Throwable $e) {
                Logger::get()->error('storage_replication', 'Failed to find on secondary storage #' . $index, ['exception' => $e]);
            }
        }

        return null;
    }

    public function updateReservation(string $id, array $fields): int
    {
        $count = $this->primaryStorage->updateReservation($id, $fields);
        foreach ($this->secondaryStorages as $index => $storage) {
            try {
                $storage->updateReservation($id, $fields);
            } catch (Throwable $e) {
                Logger::get()->error('storage_replication', 'Failed to update on secondary storage #' . $index, ['id' => $id, 'exception' => $e]);
            }
        }
        return $count;
    }

    public function deleteReservation(string $id): int
    {
        $count = $this->primaryStorage->deleteReservation($id);
        foreach ($this->secondaryStorages as $index => $storage) {
            try {
                $storage->deleteReservation($id);
            } catch (Throwable $e) {
                Logger::get()->error('storage_replication', 'Failed to delete on secondary storage #' . $index, ['id' => $id, 'exception' => $e]);
            }
        }
        return $count;
    }
}
