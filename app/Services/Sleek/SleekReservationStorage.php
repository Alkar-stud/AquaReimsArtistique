<?php
namespace app\Services\Sleek;

use app\Core\DatabaseSleekDB;
use app\Services\Reservation\ReservationStorageInterface;
use app\Utils\NsqlIdGenerator;
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
        // Assure un identifiant logique commun si absent, pour la synchronisation.
        if (empty($reservation['nsql_id'])) {
            $reservation['nsql_id'] = NsqlIdGenerator::new();
        }

        try {
            $newReservation = $this->store->insert($reservation);
        } catch (IOException|IdNotAllowedException|InvalidArgumentException|JsonException $e) {

        }

        // SleekDB retourne le document complet après insertion. On extrait son _id.
        return (string) $newReservation['_id'];
    }

    /**
     * Trouve une réservation par son identifiant SleekDB (_id).
     *
     * @param string $id L'identifiant SleekDB.
     * @return array|null La réservation ou null si non trouvée.
     * @throws InvalidArgumentException
     */
    public function findReservationById(string $id): ?array
    {
        // SleekDB utilise des entiers pour les _id par défaut.
        return $this->store->findById((int) $id);
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
     * @param string $nsqlId
     * @return array|null
     * @throws IOException
     * @throws InvalidArgumentException
     */
    public function findReservationByNsqlId(string $nsqlId): ?array
    {
        return $this->store->findOneBy(['nsql_id', '=', $nsqlId]);
    }
}
