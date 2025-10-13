<?php
namespace app\Services\Sleek;

use app\Core\DatabaseSleekDB;
use app\Services\Reservation\ReservationStorageInterface;
use SleekDB\Exceptions\IdNotAllowedException;
use SleekDB\Exceptions\InvalidArgumentException;
use SleekDB\Exceptions\IOException;
use SleekDB\Exceptions\JsonException;
use SleekDB\Store;

/**
 * Implémentation du stockage de réservations utilisant SleekDB comme base de données de documents.
 * Cette classe est le seul point d'interaction avec SleekDB pour les réservations.
 */
final class SleekReservationStorage implements ReservationStorageInterface
{
    /** @var Store */
    private Store $store;

    public function __construct(string $collectionName = 'reservations_temp')
    {
        // Utilise notre factory pour obtenir le store SleekDB, en lisant la configuration depuis .env
        $this->store = DatabaseSleekDB::getStore($collectionName);
    }

    /**
     * Sauvegarde une réservation dans le store SleekDB.
     *
     * @param array $reservation Les données de la réservation.
     * @return string L'identifiant unique (_id) généré par SleekDB.
     */
    public function saveReservation(array $reservation): string
    {
        try {
            $newReservation = $this->store->insert($reservation);
        } catch (IOException|IdNotAllowedException|InvalidArgumentException|JsonException) {

        }

        // SleekDB retourne le document complet après insertion. On extrait son _id.
        return (string) $newReservation['_id'];
    }

    /**
     * Trouve une réservation par son identifiant SleekDB (_id).
     *
     * @param string $id L'identifiant SleekDB.
     * @return array|null La réservation ou null si non trouvée.
     */
    public function findReservationById(string $id): ?array
    {
        // SleekDB utilise des entiers pour les _id par défaut.
        try {
            return $this->store->findById((int)$id);
        } catch (InvalidArgumentException) {
            return null;
        }
    }

    /**
     * Met à jour une réservation identifiée par son _id.
     *
     * @param string $id L'identifiant SleekDB.
     * @param array $fields Les champs à mettre à jour.
     * @return int Le nombre de documents mis à jour (0 ou 1).
     * @throws IOException
     * @throws InvalidArgumentException
     * @throws JsonException
     */
    public function updateReservation(string $id, array $fields): int
    {
        return $this->store->updateById((int) $id, $fields) ? 1 : 0;
    }

    /**
     * Met à jour une réservation identifiée par son primary_id.
     *
     * @param string $primaryId L'identifiant logique partagé.
     * @param array $fields Les champs à mettre à jour.
     * @return int Le nombre de documents mis à jour.
     */
    public function updateReservationByPrimaryId(string $primaryId, array $fields): int
    {
        // Trouver le document correspondant au primary_id
        // Pour le stockage primaire (SleekDB), le primary_id est son propre _id.
        try {
            $document = $this->store->findById((int)$primaryId);
        } catch (InvalidArgumentException) {
            return 0;
        }

        // Mettre à jour le document en utilisant son _id natif
        try {
            $wasUpdated = $this->store->updateById((int)$primaryId, $fields);
        } catch (IOException|InvalidArgumentException|JsonException) {
            return 0;
        }

        return $wasUpdated ? 1 : 0;
    }

    /**
     * Supprime une réservation par son _id.
     *
     * @param string $id L'identifiant SleekDB.
     * @return int Le nombre de documents supprimés (0 ou 1).
     * @throws InvalidArgumentException
     */
    public function deleteReservation(string $id): int
    {
        return $this->store->deleteById((int) $id) ? 1 : 0;
    }

    /**
     * @param string $primaryId
     * @return array|null
     * @throws IOException
     * @throws InvalidArgumentException
     */
    public function findReservationByPirmaryId(string $primaryId): ?array
    {
        return $this->store->findOneBy(['primary_id', '=', $primaryId]);
    }
}
