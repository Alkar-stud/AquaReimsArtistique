<?php
namespace app\Services;

class MongoReservationStorage implements ReservationStorageInterface
{
    private MongoService $mongo;

    public function __construct(string $collection = 'reservations')
    {
        $this->mongo = new MongoService($collection);
    }

    public function saveReservation(array $reservation,): string
    {
        // Enregistre la réservation et retourne l'ID inséré
        return $this->mongo->create($reservation);
    }

    public function findReservationById(string $id): ?array
    {
        // Recherche une réservation par son ID MongoDB
        return $this->mongo->findOne(['_id' => new \MongoDB\BSON\ObjectId($id)]);
    }

    public function updateReservation(string $id, array $fields = []): int
    {
        return $this->mongo->update(
            ['_id' => new \MongoDB\BSON\ObjectId($id)],
            ['$set' => $fields]
        );
    }

    public function deleteReservation(string $id): int
    {
        // Supprime une réservation par son ID MongoDB
        return $this->mongo->deleteOne(['_id' => new \MongoDB\BSON\ObjectId($id)]);
    }
}
