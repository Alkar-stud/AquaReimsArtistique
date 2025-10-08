<?php
namespace app\Services\Sleek;

use app\Services\Reservation\ReservationStorageInterface;
use app\Utils\NsqlIdGenerator;

/**
 * Implémentation de ReservationStorageInterface en SleekDB
 */
class SleekReservationStorage implements ReservationStorageInterface
{
    private SleekService $s;

    public function __construct(string $collection = 'reservation')
    {
        $this->s = new SleekService($collection);
    }

    public function saveReservation(array $reservation): string
    {
        // Forcer un nsql_id commun si absent
        if (empty($reservation['nsql_id'])) {
            $reservation['nsql_id'] = NsqlIdGenerator::new();
        }
        return $this->s->create($reservation);
    }

    public function findReservationById(string $id): ?array
    {
        $byId = $this->s->findOne(['id' => $id]) ?? $this->s->findOne(['_id' => $id]);
        return $byId;
    }

    public function updateReservation(string $id, array $fields = []): int
    {
        $updated = $this->s->update(['id' => $id], ['$merge' => $fields]);
        if ($updated === 0) {
            $updated = $this->s->update(['_id' => $id], ['$merge' => $fields]);
        }
        return $updated;
    }

    public function deleteReservation(string $id): int
    {
        $deleted = $this->s->delete(['id' => $id]);
        if ($deleted === 0) {
            $deleted = $this->s->delete(['_id' => $id]);
        }
        return $deleted;
    }

    // Optionnel: faciliter la comparaison par nsql_id
    public function findReservationByNsqlId(string $nsqlId): ?array
    {
        return $this->s->findOne(['nsql_id' => $nsqlId]);
    }
}
