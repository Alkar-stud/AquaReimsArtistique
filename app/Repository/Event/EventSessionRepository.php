<?php

namespace app\Repository\Event;

use app\Models\Event\EventSession;
use app\Repository\AbstractRepository;

class EventSessionRepository extends AbstractRepository
{
    public function __construct()
    {
        parent::__construct('event_sessions');
    }

    /**
     * Trouve une session par son ID
     * @param int $sessionId
     * @return EventSession|null
     */
    public function findById(int $sessionId): ?EventSession
    {
        $sql = "SELECT * FROM $this->tableName WHERE id = :id";
        $result = $this->query($sql, ['id' => $sessionId]);
        if (empty($result)) {
            return null;
        }
        return $this->hydrate($result[0]);
    }

    /**
     * Trouve toutes les sessions d'un événement
     * @param int $eventId
     * @return array
     */
    public function findByEventId(int $eventId): array
    {
        $sql = "SELECT * FROM $this->tableName WHERE event_id = :event_id ORDER BY event_start_at ASC";
        $results = $this->query($sql, ['event_id' => $eventId]);
        return array_map([$this, 'hydrate'], $results);
    }

    /**
     * Trouve la dernière session d'un événement
     * @param int $eventId
     * @return array|null Tableau contenant les informations de la dernière session ou null
     */
    public function findLastSessionByEventId(int $eventId): ?array
    {
        $sql = "SELECT * FROM $this->tableName 
            WHERE event_id = :event_id 
            ORDER BY event_start_at DESC 
            LIMIT 1";

        $result = $this->query($sql, ['event_id' => $eventId]);

        if (empty($result)) {
            return null;
        }

        return $result[0];
    }


    public function insert(EventSession $session): int
    {
        $sql = "INSERT INTO $this->tableName 
            (event_id, session_name, opening_doors_at, event_start_at, created_at)
            VALUES (:event_id, :session_name, :opening_doors_at, :event_start_at, :created_at)";

        $this->execute($sql, [
            'event_id' => $session->getEventId(),
            'session_name' => $session->getSessionName(),
            'opening_doors_at' => $session->getOpeningDoorsAt()->format('Y-m-d H:i:s'),
            'event_start_at' => $session->getEventStartAt()->format('Y-m-d H:i:s'),
            'created_at' => $session->getCreatedAt()->format('Y-m-d H:i:s')
        ]);

        return $this->getLastInsertId();
    }

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
            'event_start_at' => $session->getEventStartAt()->format('Y-m-d H:i:s')
        ]);
    }

    public function delete(int $eventId): bool
    {
        $sql = "DELETE FROM $this->tableName WHERE event_id = :event_id";
        return $this->execute($sql, ['event_id' => $eventId]);
    }

    protected function hydrate(array $data): EventSession
    {
        $session = new EventSession();
        $session->setId($data['id'])
            ->setEventId($data['event_id'])
            ->setSessionName($data['session_name'])
            ->setOpeningDoorsAt($data['opening_doors_at'])
            ->setEventStartAt($data['event_start_at'])
            ->setCreatedAt($data['created_at'])
            ->setUpdatedAt($data['updated_at']);

        return $session;
    }
}