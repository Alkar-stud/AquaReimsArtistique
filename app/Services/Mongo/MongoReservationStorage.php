<?php
namespace app\Services\Mongo;

use app\Services\Reservation\ReservationStorageInterface;
use MongoDB\BSON\ObjectId;
use app\Utils\NsqlIdGenerator;

class MongoReservationStorage implements ReservationStorageInterface
{
    private MongoService $mongo;

    public function __construct(string $collection = 'reservation')
    {
        $this->mongo = new MongoService($collection);
    }

    public function saveReservation(array $reservation): string
    {
        // Forcer un nsql_id commun si absent
        if (empty($reservation['nsql_id'])) {
            $reservation['nsql_id'] = NsqlIdGenerator::new();
        }
        return $this->mongo->create($reservation);
    }

    public function findReservationById(string $id): ?array
    {
        return $this->mongo->findOne(['_id' => new ObjectId($id)]);
    }

    public function updateReservation(string $id, array $fields = []): int
    {
        return $this->mongo->update(
            ['_id' => new ObjectId($id)],
            ['$set' => $fields]
        );
    }

    public function deleteReservation(string $id): int
    {
        return $this->mongo->deleteOne(['_id' => new ObjectId($id)]);
    }

    // Optionnel: faciliter la comparaison par nsql_id
    public function findReservationByNsqlId(string $nsqlId): ?array
    {
        return $this->mongo->findOne(['nsql_id' => $nsqlId]);
    }
}
