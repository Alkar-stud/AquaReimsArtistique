<?php
namespace app\Repository\Reservation;

use app\Repository\AbstractRepository;
use app\Repository\Event\EventRepository;
use app\Models\Reservation\ReservationTemp;
use DateTime;

class ReservationTempRepository extends AbstractRepository
{
    private ?EventRepository $eventRepository;

    public function __construct(?EventRepository $eventRepository = null)
    {
        parent::__construct('reservation_temp');
        $this->eventRepository = $eventRepository;
    }

    /**
     * Méthode lazy pour instancier le repository Event uniquement si nécessaire.
     * @return EventRepository
     */
    private function getEventRepository(): EventRepository
    {
        if ($this->eventRepository === null) {
            $this->eventRepository = new EventRepository();
        }
        return $this->eventRepository;
    }

    /**
     * Récupère une réservation temporaire par son identifiant.
     *
     * @param int $id Identifiant de la réservation temporaire.
     * @return ReservationTemp|null Instance mappée si trouvée, sinon null.
     */
    public function findById(int $id): ?ReservationTemp
    {
        $rows = $this->query("SELECT * FROM {$this->tableName} WHERE id = :id", ['id' => $id]);
        if (empty($rows)) return null;

        $reservation = $this->hydrate($rows[0]);
        $this->hydrateRelations([$reservation]);
        return $reservation;
    }

    /**
     * Récupère la première réservation temporaire correspondant à un identifiant de session.
     *
     * @param string $sessionId Identifiant de session (session_id).
     * @return ReservationTemp|null Instance mappée si trouvée, sinon null.
     */
    public function findBySessionId(string $sessionId): ?ReservationTemp
    {
        $rows = $this->query("SELECT * FROM {$this->tableName} WHERE session_id = :session_id LIMIT 1", ['session_id' => $sessionId]);
        if (empty($rows)) return null;

        $reservation = $this->hydrate($rows[0]);
        $this->hydrateRelations([$reservation]);
        return $reservation;
    }

    /**
     * Insère une nouvelle réservation temporaire en base.
     *
     * Le modèle doit contenir les valeurs nécessaires. Les dates sont formatées
     * avant insertion. En cas de succès, l'identifiant auto-incrémenté est affecté
     * au modèle via setId.
     *
     * @param ReservationTemp $m Modèle à insérer.
     * @return bool True si l'insertion a réussi, false sinon.
     */
    public function insert(ReservationTemp $m): bool
    {
        $sql = "INSERT INTO {$this->tableName}
            (event, event_session, session_id, name, firstname, email, phone, swimmer_if_limitation, access_code, created_at)
            VALUES (:event, :event_session, :session_id, :name, :firstname, :email, :phone, :swimmer_if_limitation, :access_code, :created_at)";
        $params = [
            'event' => $m->getEvent(),
            'event_session' => $m->getEventSession(),
            'session_id' => $m->getSessionId(),
            'name' => $m->getName(),
            'firstname' => $m->getFirstName(),
            'email' => $m->getEmail(),
            'phone' => $m->getPhone(),
            'swimmer_if_limitation' => $m->getSwimmerId(),
            'access_code' => $m->getAccessCode(),
            'created_at' => $m->getCreatedAt()->format('Y-m-d H:i:s'),
        ];
        $ok = $this->execute($sql, $params);
        if ($ok) {
            $m->setId($this->getLastInsertId());
        }
        return $ok;
    }


    /**
     * Update l'objet
     *
     * @param ReservationTemp $reservationTemp
     * @return bool
     */
    public function update(ReservationTemp $reservationTemp): bool
    {
        $params = [
            'event' => $reservationTemp->getEvent(),
            'event_session' => $reservationTemp->getEventSession(),
            'session_id' => $reservationTemp->getSessionId(),
            'name' => $reservationTemp->getName(),
            'firstname' => $reservationTemp->getFirstName(),
            'email' => $reservationTemp->getEmail(),
            'phone' => $reservationTemp->getPhone(),
            'swimmer_if_limitation' => $reservationTemp->getSwimmerId(),
            'access_code' => $reservationTemp->getAccessCode(),
        ];
        return $this->updateById($reservationTemp->getId(), $params);
    }

    /**
     * Pour supprimer tous les éléments par session_id
     *
     * @param string $sessionId
     * @return bool
     */
    public function deleteBySession(string $sessionId): bool
    {
        $sql = "DELETE FROM $this->tableName WHERE session_id = :session_id";
        return $this->execute($sql, ['session_id' => $sessionId]);
    }

    /**
     * Trouve les réservations temporaires expirées.
     *
     * @param int $timeoutSeconds Le timeout en secondes.
     * @return ReservationTemp[]
     */
    public function findExpired(int $timeoutSeconds): array
    {
        $sql = "SELECT * FROM {$this->tableName} WHERE created_at < NOW() - INTERVAL :seconds SECOND AND is_locked = 0";
        $rows = $this->query($sql, ['seconds' => $timeoutSeconds]);

        if (empty($rows)) {
            return [];
        }

        $reservations = array_map(fn($row) => $this->hydrate($row), $rows);
        $this->hydrateRelations($reservations);
        return $reservations;
    }

    /**
     * Supprime une liste de réservations temporaires par leurs IDs.
     *
     * @param int[] $ids
     * @return bool
     */
    public function deleteByIds(array $ids): bool
    {
        if (empty($ids)) {
            return true;
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $sql = "DELETE FROM {$this->tableName} WHERE id IN ($placeholders)";

        return $this->execute($sql, $ids);
    }

    /**
     * Convertit une ligne de résultat SQL en instance de ReservationTemp.
     *
     * Gère les conversions de types pour les champs numériques et les dates.
     *
     * @param array $row Ligne associative provenant de la base de données.
     * @return ReservationTemp Modèle peuplé à partir de la ligne fournie.
     */
    protected function hydrate(array $row): ReservationTemp
    {
        $m = new ReservationTemp();
        $m->setId((int)$row['id']);
        $m->setEvent((int)$row['event']);
        $m->setEventSession((int)$row['event_session']);
        $m->setSessionId($row['session_id']);
        $m->setName($row['name']);
        $m->setFirstName($row['firstname']);
        $m->setEmail($row['email']);
        $m->setPhone($row['phone']);
        $m->setSwimmerId($row['swimmer_if_limitation'] !== null ? (int)$row['swimmer_if_limitation'] : null);
        $m->setAccessCode($row['access_code']);
        $m->setCreatedAt($row['created_at']);
        if ($row['updated_at'] !== null) {
            $m->setUpdatedAt($row['updated_at']);
        }
        return $m;
    }

    /**
     * Hydrate les relations (ici, l'objet Event) pour une liste de réservations temporaires.
     *
     * @param ReservationTemp[] $reservations
     * @return void
     */
    private function hydrateRelations(array $reservations): void
    {
        if (empty($reservations)) {
            return;
        }

        $eventIds = array_unique(array_map(fn($r) => $r->getEvent(), $reservations));
        if (empty($eventIds)) {
            return;
        }

        $events = $this->getEventRepository()->findByIds($eventIds, true, true, true, true);

        $eventsById = [];
        foreach ($events as $event) {
            $eventsById[$event->getId()] = $event;
        }

        foreach ($reservations as $reservation) {
            $reservation->setEventObject($eventsById[$reservation->getEvent()] ?? null);
        }
    }
}
