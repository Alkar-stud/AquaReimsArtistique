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
    public function findByEventId(int $eventId, bool $withEvent = false): array
    {
        $sql = "SELECT * FROM $this->tableName WHERE event = :event_id ORDER BY start_registration_at";
        $rows = $this->query($sql, ['event_id' => $eventId]);

        $event = null;
        if ($withEvent) {
            $eventRepo = new EventRepository();
            $event = $eventRepo->findById($eventId);
        }

        return array_map(fn(array $r) => $this->hydrate($r, $event), $rows);
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
            'event' => $d->getEvent(),
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
            'event' => $d->getEvent(),
            'name' => $d->getName(),
            'start_registration_at' => $d->getStartRegistrationAt()->format('Y-m-d H:i:s'),
            'close_registration_at' => $d->getCloseRegistrationAt()->format('Y-m-d H:i:s'),
            'access_code' => $d->getAccessCode(),
        ]);
    }

    /**
     * Supprime toutes les périodes de dates d'inscription d'un événement
     * @param int $eventId
     * @return bool
     */
    public function deleteByEventId(int $eventId): bool
    {
        $sql = "DELETE FROM $this->tableName WHERE event = :event_id";
        return $this->execute($sql, ['event_id' => $eventId]);
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
            ->setEvent((int)$data['event'])
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
