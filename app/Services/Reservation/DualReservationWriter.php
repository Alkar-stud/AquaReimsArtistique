<?php
namespace app\Services\Reservation;

use app\Utils\NsqlIdGenerator;
use Throwable;

/**
 * Écrit en double : primaire (ex. SleekDB) + secondaire (ex. MongoDB).
 * - save: génère un nsql_id si absent, écrit dans le primaire, puis tente le secondaire avec le même id et nsql_id.
 * - find: lit d'abord le primaire, puis fallback secondaire.
 * - update/delete: applique au primaire, tente le secondaire en "best effort".
 */
final class DualReservationWriter implements ReservationStorageInterface
{
    public function __construct(
        private ReservationStorageInterface $primaryStorage,
        private ReservationStorageInterface $secondaryStorage
    ) {}

    public function saveReservation(array $reservation): string
    {
        // Assure un identifiant logique commun aux deux backends
        $reservation['nsql_id'] = $reservation['nsql_id'] ?? NsqlIdGenerator::new();

        // 1) primaire (source of truth)
        $id = $this->primaryStorage->saveReservation($reservation);

        // 2) secondaire (best effort) avec le même id logique
        $replicated = $reservation;
        $replicated['id'] = $id;
        try {
            $this->secondaryStorage->saveReservation($replicated);
        } catch (Throwable) {
            // Option: logger l'erreur ici
        }

        return $id;
    }

    public function findReservationById(string $id): ?array
    {
        $doc = $this->primaryStorage->findReservationById($id);
        if ($doc !== null) {
            return $doc;
        }
        return $this->secondaryStorage->findReservationById($id);
    }

    public function updateReservation(string $id, array $fields): int
    {
        $count = $this->primaryStorage->updateReservation($id, $fields);
        try {
            $this->secondaryStorage->updateReservation($id, $fields);
        } catch (Throwable) {
            // Option: logger l'erreur ici
        }
        return $count;
    }

    public function deleteReservation(string $id): int
    {
        $count = $this->primaryStorage->deleteReservation($id);
        try {
            $this->secondaryStorage->deleteReservation($id);
        } catch (Throwable) {
            // Option: logger l'erreur ici
        }
        return $count;
    }
}
