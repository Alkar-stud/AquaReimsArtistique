<?php
namespace app\Services\Reservation;

use app\Services\Log\Logger;
use app\Services\Mongo\MongoReservationStorage;
use app\Services\Sleek\SleekReservationStorage;
use SleekDB\Exceptions\InvalidArgumentException;
use SleekDB\Exceptions\IOException;
use SleekDB\Exceptions\JsonException;
use Throwable;

/**
 * Orchestre l'écriture sur plusieurs systèmes de stockage.
 * Le premier stockage de la liste est considéré comme le "primaire" (source de vérité).
 * Les suivants sont des "secondaires" synchronisés en "best effort".
 */
final class ReservationTempWriter implements ReservationStorageInterface
{
    private ReservationStorageInterface $primaryStorage;
    /** @var ReservationStorageInterface[] */
    private array $secondaryStorages;
    private ReservationSessionService $reservationSessionService;

    public function __construct(
        ReservationSessionService $reservationSessionService,
    ) {
        $this->reservationSessionService = $reservationSessionService;
        // SleekDB est le primaire, MongoDB est le secondaire.
        $this->primaryStorage = new SleekReservationStorage();
        // Désactiver les secondaires en prod / preprod
        $env = strtolower($_ENV['APP_ENV'] ?? getenv('APP_ENV') ?: '');
        if (in_array($env, ['production', 'preprod'], true)) {
            $this->secondaryStorages = [];
        } else {
            $this->secondaryStorages = [new MongoReservationStorage()];
        }
    }

    /**
     * Persiste en BDD la réservation temporaire
     * @param array $reservation
     * @return string
     */
    public function saveReservation(array $reservation): string
    {
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
        $this->reservationSessionService->setReservationSession('primary_id', $primaryId );

        return $primaryId;
    }

    /**
     * @param string $id
     * @return array|null
     */
    public function findReservationById(string $id): ?array
    {
        // On cherche d'abord dans le primaire
        $doc = $this->primaryStorage->findReservationById($id);
        if ($doc !== null) {
            // On s'assure que le document retourné contient toujours le primary_id.
            // Pour le stockage primaire, son propre _id EST le primary_id.
            $doc['primary_id'] = (string)($doc['_id'] ?? $id);

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

    /**
     * @param string $id
     * @param array $fields
     * @return int
     * @throws IOException
     * @throws InvalidArgumentException
     * @throws JsonException
     */
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

    /**
     * @param string $primaryId
     * @param array $fields
     * @return int
     */
    public function updateReservationByPrimaryId(string $primaryId, array $fields): int
    {
        // On met à jour le primaire
        $count = $this->primaryStorage->updateReservationByPrimaryId($primaryId, $fields);

        // On réplique la mise à jour sur les secondaires
        foreach ($this->secondaryStorages as $index => $storage) {
            try {
                $storage->updateReservationByPrimaryId($primaryId, $fields);
            } catch (Throwable $e) {
                Logger::get()->error('storage_replication', 'Failed to update by primary_id on secondary storage #' . $index, ['primary_id' => $primaryId, 'exception' => $e]);
            }
        }
        return $count;
    }

    /**
     * Supprimer la réservation temporaire dans les bases
     *
     * @param string $id
     * @return int
     * @throws InvalidArgumentException
     */
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
