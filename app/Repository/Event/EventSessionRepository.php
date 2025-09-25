<?php

namespace app\Repository\Event;

use app\Models\Event\Event;
use app\Models\Event\EventSession;
use app\Repository\AbstractRepository;

class EventSessionRepository extends AbstractRepository
{
    public function __construct()
    {
        parent::__construct('event_session');
    }

    /**
     * Retourne une session d'événement par son ID
     * @param int $id
     * @param bool $withEvent
     * @return EventSession|null
     */
    public function findById(int $id, bool $withEvent = false): ?EventSession
    {
        $sql = "SELECT * FROM $this->tableName WHERE id = :id";
        $rows = $this->query($sql, ['id' => $id]);
        if (!$rows) return null;

        $event = null;
        if ($withEvent) {
            $eventRepo = new EventRepository();
            $event = $eventRepo->findById((int)$rows[0]['event_id']);
        }
        return $this->hydrate($rows[0], $event);
    }

    /**
     * Retourne toutes les sessions par événement, ordonnées par date de début
     * @return EventSession[]
     */
    public function findByEventId(int $eventId, bool $withEvent = false, ?Event $eventObject = null): array
    {
        $sql = "SELECT * FROM $this->tableName WHERE event_id = :event_id ORDER BY event_start_at";
        $rows = $this->query($sql, ['event_id' => $eventId]);

        $event = $eventObject;
        if ($withEvent) {
            $eventRepo = new EventRepository();
            $event = $eventRepo->findById($eventId);
        }

        return array_map(fn(array $r) => $this->hydrate($r, $event), $rows);
    }

    /**
     * Retourne toutes les sessions pour une liste d'IDs d'événements, groupées par event_id
     * @param int[] $eventIds
     * @return array<int, EventSession[]>
     */
    public function findByEventIds(array $eventIds): array
    {
        if (empty($eventIds)) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($eventIds), '?'));

        $sql = "SELECT * FROM $this->tableName WHERE event_id IN ($placeholders) ORDER BY event_id, event_start_at";
        $rows = $this->query($sql, $eventIds);

        $result = [];
        foreach ($rows as $row) {
            $eventId = (int)$row['event_id'];
            if (!isset($result[$eventId])) {
                $result[$eventId] = [];
            }
            $result[$eventId][] = $this->hydrate($row);
        }

        return $result;
    }

    /**
     * Retourne la dernière session d'événement d'un événement par sa date de début
     * @param int $eventId
     * @return array|null
     */
    public function findLastSessionByEventId(int $eventId): ?array
    {
        $sql = "SELECT * FROM $this->tableName WHERE event_id = :event_id ORDER BY event_start_at DESC LIMIT 1";
        $rows = $this->query($sql, ['event_id' => $eventId]);
        return $rows[0] ?? null;
    }

    /**
     * Ajoute une session d'événement
     * @param EventSession $session
     * @return int
     */
    public function insert(EventSession $session): int
    {
        $sql = "INSERT INTO $this->tableName
            (event_id, session_name, opening_doors_at, event_start_at, created_at)
            VALUES (:event_id, :session_name, :opening_doors_at, :event_start_at, :created_at)";
        $ok = $this->execute($sql, [
            'event_id' => $session->getEventId(),
            'session_name' => $session->getSessionName(),
            'opening_doors_at' => $session->getOpeningDoorsAt()->format('Y-m-d H:i:s'),
            'event_start_at' => $session->getEventStartAt()->format('Y-m-d H:i:s'),
            'created_at' => $session->getCreatedAt()->format('Y-m-d H:i:s'),
        ]);
        return $ok ? $this->getLastInsertId() : 0;
    }

    /**
     * Mise à jour d'une session d'événement
     * @param EventSession $session
     * @return bool
     */
    public function update(EventSession $session): bool
    {
        $sql = "UPDATE $this->tableName SET
            session_name = :session_name,
            opening_doors_at = :opening_doors_at,
            event_start_at = :event_start_at,
            updated_at = NOW()
            WHERE id = :id";
        return $this->execute($sql, [
            'id' => $session->getId(),
            'session_name' => $session->getSessionName(),
            'opening_doors_at' => $session->getOpeningDoorsAt()->format('Y-m-d H:i:s'),
            'event_start_at' => $session->getEventStartAt()->format('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Supprime toutes les sessions d'un événement
     * @param int $eventId
     * @return bool
     */
    public function deleteByEventId(int $eventId): bool
    {
        $sql = "DELETE FROM $this->tableName WHERE event_id = :event_id";
        return $this->execute($sql, ['event_id' => $eventId]);
    }

    /**
     * Hydrate une session d'événement
     * @param array $data
     * @param Event|null $event
     * @return EventSession
     */
    protected function hydrate(array $data, ?Event $event = null): EventSession
    {
        $s = new EventSession();
        $s->setId((int)$data['id'])
            ->setEventId((int)$data['event_id'])
            ->setSessionName($data['session_name'])
            ->setOpeningDoorsAt($data['opening_doors_at'])
            ->setEventStartAt($data['event_start_at'])
            ->setCreatedAt($data['created_at']);

        if (!empty($data['updated_at'])) { $s->setUpdatedAt($data['updated_at']); }
        if ($event) { $s->setEventObject($event); }

        return $s;
    }
}
