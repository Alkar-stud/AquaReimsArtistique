<?php

namespace app\Repository\Event;

use app\Models\Event\Event;
use app\Models\Event\EventPresentations;
use app\Repository\AbstractRepository;

class EventPresentationsRepository extends AbstractRepository
{
    public function __construct()
    {
        parent::__construct('event_presentations');
    }

    /**
     * Retourne toutes les présentations ordonnées par date de début
     * @param bool $upComing
     * @param bool $withEvent
     * @return EventPresentations[]
     */
    public function findAll(bool $upComing = false, bool $withEvent = false): array
    {
        if ($upComing === true) { $where = " WHERE display_until >= NOW()"; }
        else { $where = " WHERE display_until <= NOW()"; }

        $sql = "SELECT * FROM $this->tableName" . $where . " ORDER BY display_until";
        $rows = $this->query($sql);

        return array_map(function (array $r) use ($withEvent) {
            $event = null;
            if ($withEvent && !empty($r['event'])) {
                $eventRepo = new EventRepository();
                $event = $eventRepo->findById((int)$r['event']);
            }
            return $this->hydrate($r, $event);
        }, $rows);
    }

    /**
     * Retourne une présentation par son ID
     * @param int $id
     * @param bool $withEvent
     * @return EventPresentations|null
     */
    public function findById(int $id, bool $withEvent = false): ?EventPresentations
    {
        $sql = "SELECT * FROM $this->tableName WHERE id = :id";
        $rows = $this->query($sql, ['id' => $id]);
        if (!$rows) return null;

        $event = null;
        if ($withEvent && !empty($rows[0]['event'])) {
            $eventRepo = new EventRepository();
            $event = $eventRepo->findById((int)$rows[0]['event']);
        }
        return $this->hydrate($rows[0], $event);
    }

    /**
     * Retourne la/les présentations par event
     * @return EventPresentations[]
     */
    public function findByEventId(int $eventId, bool $withEvent = false, ?Event $eventObject = null): array
    {
        $sql = "SELECT * FROM $this->tableName WHERE event = :event ORDER BY display_until";
        $rows = $this->query($sql, ['event' => $eventId]);

        $event = $eventObject;
        if ($withEvent) {
            $eventRepo = new EventRepository();
            $event = $eventRepo->findById($eventId);
        }

        return array_map(fn (array $r) => $this->hydrate($r, $event), $rows);
    }

    /**
     * Ajoute une présentation
     * @param EventPresentations $p
     * @return int
     */
    public function insert(EventPresentations $p): int
    {
        $sql = "INSERT INTO $this->tableName
            (event, is_displayed, display_until, content, created_at)
            VALUES (:event, :is_displayed, :display_until, :content, :created_at)";
        $ok = $this->execute($sql, [
            'event' => $p->getEventId(),
            'is_displayed' => $p->getIsDisplayed() ? 1 : 0,
            'display_until' => $p->getDisplayUntil()->format('Y-m-d H:i:s'),
            'content' => $p->getContent(),
            'created_at' => $p->getCreatedAt()->format('Y-m-d H:i:s'),
        ]);
        return $ok ? $this->getLastInsertId() : 0;
    }

    /**
     * Met à jour une présentation
     * @param EventPresentations $p
     * @return bool
     */
    public function update(EventPresentations $p): bool
    {
        $sql = "UPDATE $this->tableName SET
            event = :event,
            is_displayed = :is_displayed,
            display_until = :display_until,
            content = :content,
            updated_at = NOW()
            WHERE id = :id";
        return $this->execute($sql, [
            'id' => $p->getId(),
            'event' => $p->getEventId(),
            'is_displayed' => $p->getIsDisplayed() ? 1 : 0,
            'display_until' => $p->getDisplayUntil()->format('Y-m-d H:i:s'),
            'content' => $p->getContent(),
        ]);
    }

    /**
     * Met à jour le statut d’une présentation
     * @param int $id
     * @param bool $isDisplayed
     * @return bool
     */
    public function updateStatus(int $id, bool $isDisplayed): bool
    {
        $sql = "UPDATE $this->tableName SET is_displayed = :is_displayed, updated_at = NOW() WHERE id = :id";
        return $this->execute($sql, [
            'id' => $id,
            'is_displayed' => $isDisplayed ? 1 : 0,
        ]);
    }

    /**
     * Supprime toutes les présentations d’un événement
     * @param int $eventId
     * @return bool
     */
    public function deleteAllForEvent(int $eventId): bool
    {
        $sql = "DELETE FROM $this->tableName WHERE event = :event";
        return $this->execute($sql, ['event' => $eventId]);
    }

    /**
     * Hydrate une présentation
     * @param array<string,mixed> $data
     */
    protected function hydrate(array $data, ?Event $event = null): EventPresentations
    {
        $p = new EventPresentations();
        $p->setId((int)$data['id'])
            ->setEventId((int)$data['event'])
            ->setIsDisplayed((bool)$data['is_displayed'])
            ->setDisplayUntil($data['display_until'])
            ->setContent($data['content'] ?? null)
            ->setCreatedAt($data['created_at']);

        if (!empty($data['updated_at'])) { $p->setUpdatedAt($data['updated_at']); }
        if ($event) { $p->setEventObject($event); }

        return $p;
    }
}
