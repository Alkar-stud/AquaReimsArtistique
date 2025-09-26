<?php

namespace app\Repository\Event;

use app\Models\Event\Event;
use app\Models\Event\EventInscriptionDate;
use app\Repository\AbstractRepository;

class EventInscriptionDateRepository extends AbstractRepository
{
    public function __construct()
    {
        parent::__construct('event_inscription_date');
    }

    /**
     * Retourne toutes les périodes de dates d'inscription d'un événement ordonnées par date de début
     * @return EventInscriptionDate[]
     */
    public function findAll(): array
    {
        $sql = "SELECT * FROM $this->tableName ORDER BY start_registration_at";
        $rows = $this->query($sql);
        return array_map([$this, 'hydrate'], $rows);
    }

    /**
     * Retourne une période de date d'inscription par son ID
     * @param int $id
     * @param bool $withEvent
     * @return EventInscriptionDate|null
     */
    public function findById(int $id, bool $withEvent = false): ?EventInscriptionDate
    {
        $sql = "SELECT * FROM $this->tableName WHERE id = :id";
        $rows = $this->query($sql, ['id' => $id]);
        if (!$rows) return null;

        $event = null;
        if ($withEvent) {
            $eventRepo = new EventRepository();
            $event = $eventRepo->findById((int)$rows[0]['event']);
        }
        return $this->hydrate($rows[0], $event);
    }

    /**
     * Retourne toutes les périodes de dates d'inscription d'un événement ordonnées par date de début
     * @return EventInscriptionDate[]
     */
    public function findByEventId(int $eventId, bool $withEvent = false, ?Event $eventObject = null): array
    {
        $sql = "SELECT * FROM $this->tableName WHERE event = :event ORDER BY start_registration_at";
        $rows = $this->query($sql, ['event' => $eventId]);

        $event = $eventObject;
        if ($withEvent) {
            $eventRepo = new EventRepository();
            $event = $eventRepo->findById($eventId);
        }

        return array_map(fn(array $r) => $this->hydrate($r, $event), $rows);
    }

    /**
     * Retourne toutes les périodes d'inscription pour une liste d'IDs d'événements, groupées par event
     * @param int[] $eventIds
     * @return array<int, EventInscriptionDate[]>
     */
    public function findByEventIds(array $eventIds): array
    {
        if (empty($eventIds)) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($eventIds), '?'));

        $sql = "SELECT * FROM $this->tableName WHERE event IN ($placeholders) ORDER BY event, start_registration_at";
        $rows = $this->query($sql, $eventIds);

        $result = [];
        foreach ($rows as $row) {
            $eventId = (int)$row['event'];
            if (!isset($result[$eventId])) {
                $result[$eventId] = [];
            }
            $result[$eventId][] = $this->hydrate($row);
        }

        return $result;
    }

    /**
     * Ajoute une période de dates d'inscription
     * @param EventInscriptionDate $d
     * @return int
     */
    public function insert(EventInscriptionDate $d): int
    {
        $sql = "INSERT INTO $this->tableName
            (event, name, start_registration_at, close_registration_at, access_code, created_at)
            VALUES (:event, :name, :start_registration_at, :close_registration_at, :access_code, :created_at)";
        $ok = $this->execute($sql, [
            'event' => $d->getEventId(),
            'name' => $d->getName(),
            'start_registration_at' => $d->getStartRegistrationAt()->format('Y-m-d H:i:s'),
            'close_registration_at' => $d->getCloseRegistrationAt()->format('Y-m-d H:i:s'),
            'access_code' => $d->getAccessCode(),
            'created_at' => $d->getCreatedAt()->format('Y-m-d H:i:s'),
        ]);
        return $ok ? $this->getLastInsertId() : 0;
    }

    /**
     * Met à jour une période de dates d'inscription
     * @param EventInscriptionDate $d
     * @return bool
     */
    public function update(EventInscriptionDate $d): bool
    {
        $sql = "UPDATE $this->tableName SET
            event = :event,
            name = :name,
            start_registration_at = :start_registration_at,
            close_registration_at = :close_registration_at,
            access_code = :access_code,
            updated_at = NOW()
            WHERE id = :id";
        return $this->execute($sql, [
            'id' => $d->getId(),
            'event' => $d->getEventId(),
            'name' => $d->getName(),
            'start_registration_at' => $d->getStartRegistrationAt()->format('Y-m-d H:i:s'),
            'close_registration_at' => $d->getCloseRegistrationAt()->format('Y-m-d H:i:s'),
            'access_code' => $d->getAccessCode(),
        ]);
    }


    /**
     * Supprime toutes les périodes d'inscription associées à un événement.
     * @param int $eventId
     * @return bool
     */
    public function deleteAllForEvent(int $eventId): bool
    {
        $sql = "DELETE FROM $this->tableName WHERE `event` = :event";
        return $this->execute($sql, ['event' => $eventId]);
    }

    /**
     * Hydrate une période de dates d'inscription
     * @param array $data
     * @param Event|null $event
     * @return EventInscriptionDate
     */
    protected function hydrate(array $data, ?Event $event = null): EventInscriptionDate
    {
        $d = new EventInscriptionDate();
        $d->setId((int)$data['id'])
            ->setEventId((int)$data['event'])
            ->setName($data['name'])
            ->setStartRegistrationAt($data['start_registration_at'])
            ->setCloseRegistrationAt($data['close_registration_at'])
            ->setAccessCode($data['access_code'] ?? null)
            ->setCreatedAt($data['created_at']);

        if (!empty($data['updated_at'])) { $d->setUpdatedAt($data['updated_at']); }
        if ($event) { $d->setEventObject($event); }

        return $d;
    }
}
