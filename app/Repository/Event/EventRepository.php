<?php

namespace app\Repository\Event;

use app\Models\Event\Event;
use app\Models\Piscine\Piscine;
use app\Repository\AbstractRepository;
use app\Repository\Piscine\PiscineRepository;
use app\Repository\Tarif\TarifRepository;

class EventRepository extends AbstractRepository
{
    public function __construct()
    {
        parent::__construct('event');
    }

    /**
     * Retourne tous les événements ordonnés par date de création
     * @return Event[]
     */
    public function findAll(): array
    {
        $sql = "SELECT * FROM $this->tableName ORDER BY created_at DESC, name";
        $rows = $this->query($sql);
        return array_map([$this, 'hydrate'], $rows);
    }

    /**
     * Retourne un événement par son ID
     * @param int $id
     * @param bool $withPiscine
     * @param bool $withSessions
     * @param bool $withInscriptionDates
     * @param bool $withTarifs
     * @param bool $withPresentations
     * @return Event|null
     */
    public function findById(
        int $id,
        bool $withPiscine = false,
        bool $withSessions = false,
        bool $withInscriptionDates = false,
        bool $withTarifs = true,
        bool $withPresentations = false
    ): ?Event {
        $sql = "SELECT * FROM $this->tableName WHERE id = :id";
        $rows = $this->query($sql, ['id' => $id]);
        if (!$rows) return null;

        $row = $rows[0];
        // On hydrate l'objet Event de base en premier
        $event = $this->hydrate($row);

        $piscine = null;
        $sessions = [];
        $inscDates = [];
        $tarifs = [];
        $presentations = [];

        if ($withPiscine && $event->getPlace()) {
            $piscineRepo = new PiscineRepository();
            $piscine = $piscineRepo->findById($event->getPlace());
        }
        if ($withSessions) {
            $sessionRepo = new EventSessionRepository();
            $sessions = $sessionRepo->findByEventId($event->getId(), false, $event);
        }
        if ($withInscriptionDates) {
            $inscRepo = new EventInscriptionDateRepository();
            $inscDates = $inscRepo->findByEventId($event->getId(), false, $event);
        }
        if ($withTarifs) {
            $tarifRepo = new TarifRepository();
            $tarifs = $tarifRepo->findByEventId($event->getId());
        }
        if ($withPresentations) {
            $presRepo = new EventPresentationsRepository();
            $presentations = $presRepo->findByEventId($event->getId(), false, $event);
        }

        return $this->hydrate($row, $piscine, $sessions, $inscDates, $tarifs, $presentations);
    }

    /**
     * Retourne tous les événements à venir ou passés ordonnés par date de début
     * @param bool|null $isUpComing
     * @return array
     */
    public function findAllSortByDate(?bool $isUpComing = null): array
    {
        $sql = "SELECT e.*  FROM $this->tableName e
                 LEFT JOIN (
                     SELECT event, MAX(event_start_at) as last_session_date 
                     FROM event_session 
                     GROUP BY event
                 ) s ON s.event = e.id";

        $params = [];
        if ($isUpComing === true) {
            // Événements à venir : ceux dont la dernière session n'est pas passée,
            $sql .= " WHERE s.last_session_date >= NOW()";
        } elseif ($isUpComing === false) {
            // Événements passés : ceux dont la dernière session est passée.
            $sql .= " WHERE s.last_session_date < NOW()";
        }

        $sql .= " ORDER BY s.last_session_date";
        $rows = $this->query($sql, $params);
        return array_map([$this, 'hydrate'], $rows);
    }

    /**
     * Ajoute un événement
     * @param Event $event
     * @return int
     */
    public function insert(Event $event): int
    {
        $sql = "INSERT INTO $this->tableName
            (name, place, limitation_per_swimmer, created_at)
            VALUES (:name, :place, :limitation_per_swimmer, :created_at)";
        $ok = $this->execute($sql, [
            'name' => $event->getName(),
            'place' => $event->getPlace(),
            'limitation_per_swimmer' => $event->getLimitationPerSwimmer(),
            'created_at' => $event->getCreatedAt()->format('Y-m-d H:i:s'),
        ]);
        return $ok ? $this->getLastInsertId() : 0;
    }

    /**
     * Met à jour un événement
     * @param Event $event
     * @return bool
     */
    public function update(Event $event): bool
    {
        $sql = "UPDATE $this->tableName SET
            name = :name,
            place = :place,
            limitation_per_swimmer = :limitation_per_swimmer,
            updated_at = NOW()
            WHERE id = :id";
        return $this->execute($sql, [
            'id' => $event->getId(),
            'name' => $event->getName(),
            'place' => $event->getPlace(),
            'limitation_per_swimmer' => $event->getLimitationPerSwimmer(),
        ]);
    }

    /**
     * Hydrate un événement
     * @param array<string,mixed> $data
     * @param Piscine|null $piscine
     * @param array $sessions
     * @param array $inscriptionDates
     * @param array $tarifs
     * @param array $presentations
     * @return Event
     */
    protected function hydrate(
        array $data,
        ?Piscine $piscine = null,
        array $sessions = [],
        array $inscriptionDates = [],
        array $tarifs = [],
        array $presentations = []
    ): Event {
        $e = new Event();
        $e->setId((int)$data['id'])
            ->setName($data['name'])
            ->setPlace((int)$data['place']) // place est NOT NULL
            ->setLimitationPerSwimmer(isset($data['limitation_per_swimmer']) ? (int)$data['limitation_per_swimmer'] : null) // Correction du nom
            ->setCreatedAt($data['created_at']);

        if (!empty($data['updated_at'])) { $e->setUpdatedAt($data['updated_at']); }
        if ($piscine !== null) { $e->setPiscine($piscine); }
        if (!empty($sessions)) { $e->setSessions($sessions); }
        if (!empty($tarifs)) { $e->setTarifs($tarifs); }
        if (!empty($inscriptionDates)) { $e->setInscriptionDates($inscriptionDates); }
        if (!empty($presentations)) { $e->setPresentations($presentations); }

        return $e;
    }
}
