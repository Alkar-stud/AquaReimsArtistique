<?php
namespace app\Services\Mongo;

use app\Services\ReservationStorageInterface;
use MongoDB\BSON\ObjectId;
use MongoDB\BSON\UTCDateTime;

class MongoReservationStorage implements ReservationStorageInterface
{
    private MongoService $mongo;

    public function __construct(string $collection = 'reservations')
    {
        $this->mongo = new MongoService($collection);
    }

    public function saveReservation(array $reservation): string
    {
        // Enregistre la réservation et retourne l'ID inséré
        return $this->mongo->create($reservation);
    }

    public function findReservationById(string $id): ?array
    {
        // Recherche une réservation par son ID MongoDB
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
        // Supprime une réservation par son ID MongoDB
        return $this->mongo->deleteOne(['_id' => new ObjectId($id)]);
    }

    /**
     * Sauvegarde ou met à jour une réservation et retourne son ID.
     * @param array $reservationData
     * @return string L'ID de la réservation.
     */
    public function saveOrUpdateReservation(array &$reservationData): string
    {
        $reservationId = $reservationData['reservationId'] ?? null;
        if ($reservationId) {
            $reservationData['updatedAt'] = new UTCDateTime(time() * 1000);
            $this->updateReservation($reservationId, $reservationData);
        } else {
            $reservationData['createdAt'] = new UTCDateTime(time() * 1000);
            $reservationId = $this->saveReservation($reservationData);
        }
        return $reservationId;
    }
}
