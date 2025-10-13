<?php
namespace app\Services\Mongo;

use app\Services\Reservation\ReservationStorageInterface;
use MongoDB\BSON\ObjectId;

class MongoReservationStorage implements ReservationStorageInterface
{
    private MongoService $mongo;

    public function __construct(string $collection = 'reservations_temp')
    {
        $this->mongo = new MongoService($collection);
    }

    /**
     * @param array $reservation
     * @return string
     */
    public function saveReservation(array $reservation): string
    {
        return $this->mongo->create($reservation);
    }

    /**
     * @param string $id
     * @return array|null
     */
    public function findReservationById(string $id): ?array
    {
        return $this->mongo->findOne(['_id' => new ObjectId($id)]);
    }

    /**
     * @param string $id
     * @param array $fields
     * @return int
     */
    public function updateReservation(string $id, array $fields = []): int
    {
        return $this->mongo->update(
            ['_id' => new ObjectId($id)],
            ['$set' => $fields]
        );
    }

    /**
     * @param string $primaryId
     * @param array $fields
     * @return int
     */
    public function updateReservationByPrimaryId(string $primaryId, array $fields): int
    {
        return $this->mongo->update(
            ['primary_id' => $primaryId],
            ['$set' => $fields]
        );
    }

    /**
     * @param string $id
     * @return int
     */
    public function deleteReservation(string $id): int
    {
        return $this->mongo->deleteOne(['_id' => new ObjectId($id)]);
    }

    // Optionnel: faciliter la comparaison par primary_id

    /**
     * @param string $primaryId
     * @return array|null
     */
    public function findReservationByPrimaryId(string $primaryId): ?array
    {
        return $this->mongo->findOne(['primary_id' => $primaryId]);
    }
}
