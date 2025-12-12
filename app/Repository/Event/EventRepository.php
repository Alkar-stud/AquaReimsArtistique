<?php

namespace app\Repository\Event;

use app\Models\Event\Event;
use app\Models\Event\EventSession;
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
     * Retourne une liste d'événements par leurs IDs
     * @param int[] $ids
     * @param bool $withPiscine
     * @param bool $withSessions
     * @param bool $withInscriptionDates
     * @param bool $withTarifs
     * @param bool $withPresentations
     * @return Event[]
     */
    public function findByIds(
        array $ids,
        bool $withPiscine = false,
        bool $withSessions = false,
        bool $withInscriptionDates = false,
        bool $withTarifs = true,
        bool $withPresentations = false
    ): array {
        if (empty($ids)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $sql = "SELECT * FROM {$this->tableName} WHERE id IN ($placeholders)";
        $rows = $this->query($sql, $ids);
        if (!$rows) {
            return [];
        }

        $events = array_map(fn($row) => $this->hydrate($row), $rows);
        $eventsById = [];
        foreach ($events as $event) {
            $eventsById[$event->getId()] = $event;
        }

        if ($withPiscine) {
            $piscineRepo = new PiscineRepository();
            $piscineIds = array_unique(array_map(fn(Event $e) => $e->getPlace(), $events));
            $piscinesResult = $piscineRepo->findByIds($piscineIds);

            // On ré-indexe le tableau de piscines par leur ID pour une association facile.
            $piscinesById = [];
            foreach ($piscinesResult as $piscine) {
                $piscinesById[$piscine->getId()] = $piscine;
            }

            foreach ($events as $event) {
                $event->setPiscine($piscinesById[$event->getPlace()] ?? null);
            }
        }

        if ($withSessions) {
            $sessionRepo = new EventSessionRepository();
            // On récupère toutes les sessions pour cette liste d'événements
            $eventsSessions = $sessionRepo->findByEventIds(array_keys($eventsById));

            // On groupe les sessions par id d'événement
            $sessionsByEventId = [];
            foreach ($eventsSessions as $sessions) {
                foreach ($sessions as $session) {
                    /** @var EventSession $session */
                    $sessionsByEventId[$session->getEventId()][] = $session;
                }
            }

            // On attache les sessions à chaque événement
            foreach ($eventsById as $eventId => $event) {
                $event->setSessions($sessionsByEventId[$eventId] ?? []);
            }
        }

        if ($withTarifs) {
            $tarifRepo = new TarifRepository();
            $eventTarifRepo = new EventTarifRepository($tarifRepo);

            // On récupère tous les tarifs (avec ou sans places selon votre besoin, ici avec places par défaut)
            $eventsTarifs = $eventTarifRepo->findTarifsByEvents(array_keys($eventsById), null);

            // On associe les tarifs à leurs Events
            foreach ($eventsById as $eventId => $event) {
                $eventTarifs = $eventsTarifs[$eventId] ?? [];

                // On ne garde que la liste d'objets Tarif (sans les clés id si vous n'en avez pas besoin)
                $event->setTarifs(array_values($eventTarifs));
            }
        }

        if ($withInscriptionDates) {
            $inscRepo = new EventInscriptionDateRepository();
            $inscDatesByEvent = $inscRepo->findByEventIds($ids);
            foreach ($events as $event) {
                $event->setInscriptionDates($inscDatesByEvent[$event->getId()] ?? []);
            }
        }

        if ($withPresentations) {
            $presentationRepo = new EventPresentationsRepository();
            $presentationsByEvent = $presentationRepo->findByEventIds($ids);
            foreach ($events as $event) {
                $event->setPresentations($presentationsByEvent[$event->getId()] ?? []);
            }
        }

        return array_values($events);
    }

    /**
     * Retourne tous les événements à venir ou passés ordonnés par date de début
     *
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
     * Récupère les informations pour les event à venir, leurs sessions et périodes d'inscription.
     * Pour toutes les sessions à venir.
     *
     * @return array Un tableau d'objets contenant les statistiques pour chaque event.
     *               Chaque objet a les propriétés : sessionId, sessionName, sessionDate, eventName, periodId, periodName, periodStart, periodEnd.
     */
    public function getUpcomingEventsSessions(): array
    {
        $sql = "
            SELECT
                e.id AS eventId,
                e.name AS eventName,
                es.id AS sessionId,
                es.session_name AS sessionName,
                es.event_start_at AS sessionDate,
                eid.id AS periodId,
                eid.name AS periodName,
                eid.start_registration_at AS periodStart,
                eid.close_registration_at AS periodEnd
            FROM `event`
                e
                -- On joint les sessions à leur événement
            JOIN event_session es ON
                e.id = es.event
                -- On joint les périodes d'inscription à leur événement
            JOIN event_inscription_date eid ON
                e.id = eid.event
            WHERE
                -- On ne sélectionne que les sessions qui n'ont pas encore eu lieu
                es.event_start_at >= NOW()
            ORDER BY
                -- On ordonne pour faciliter le traitement en PHP
                e.id, es.event_start_at, eid.start_registration_at
        ";

        return $this->query($sql);
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
