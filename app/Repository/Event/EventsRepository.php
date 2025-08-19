<?php

namespace app\Repository\Event;

use app\Models\Event\Events;
use app\Repository\Piscine\PiscinesRepository;
use app\Repository\AbstractRepository;
use DateMalformedStringException;

class EventsRepository extends AbstractRepository
{
    public function __construct()
    {
        parent::__construct('events');
    }

    /**
     * Trouve tous les événements
     * @return Events[]
     */
    public function findAll(): array
    {
        $sql = "SELECT * FROM $this->tableName ORDER BY `event_start_at` DESC, `libelle`;";
        $results = $this->query($sql);
        return array_map([$this, 'hydrate'], $results);
    }

    /**
     * Trouve un événement par son ID
     * @param int $id
     * @return Events|null
     * @throws DateMalformedStringException
     */
    public function findById(int $id): ?Events
    {
        $sql = "SELECT * FROM $this->tableName WHERE id = :id";
        $result = $this->query($sql, ['id' => $id]);

        if (!$result) {
            return null;
        }

        return $this->hydrate($result[0]);
    }

    /**
     * Trouve tous les événements à venir ou passés
     * @param bool $upComing Si true, retourne les événements à venir, sinon les événements passés
     * @return Events[]
     */
    public function findSortByDate($upComing = true): array
    {
        $sql = "SELECT * FROM $this->tableName 
                WHERE event_start_at " . ($upComing === true ? ">" : "<=") . " NOW()
                ORDER BY event_start_at ASC";
        $results = $this->query($sql);
        return array_map([$this, 'hydrate'], $results);
    }

    /**
     * Retourne les événements à venir.
     *
     * @return Events[]
     */
    public function findUpcoming(): array
    {
        $today = date('Y-m-d');

        $sql = "SELECT * FROM $this->tableName 
            WHERE event_start_at >= :today 
            ORDER BY event_start_at";

        $results = $this->query($sql, ['today' => $today]);
        return array_map([$this, 'hydrate'], $results);
    }

    /**
     * Insère un nouvel événement
     * @param Events $event
     * @return int ID de l'événement inséré
     */
    public function insert(Events $event): int
    {
        $sql = "INSERT INTO $this->tableName 
            (libelle, lieu, opening_doors_at, event_start_at, associate_event, limitation_per_swimmer, created_at)
            VALUES (:libelle, :lieu, :opening_doors_at, :event_start_at, :associate_event, :limitation_per_swimmer, :created_at)";

        $this->execute($sql, [
            'libelle' => $event->getLibelle(),
            'lieu' => $event->getLieu(),
            'opening_doors_at' => $event->getOpeningDoorsAt()->format('Y-m-d H:i:s'),
            'event_start_at' => $event->getEventStartAt()->format('Y-m-d H:i:s'),
            'associate_event' => $event->getAssociateEvent(),
            'limitation_per_swimmer' => $event->getLimitationPerSwimmer(),
            'created_at' => $event->getCreatedAt()->format('Y-m-d H:i:s')
        ]);

        return $this->getLastInsertId();
    }

    /**
     * Met à jour un événement
     * @param Events $event L'événement à mettre à jour
     * @return bool Succès de la mise à jour
     */
    public function update(Events $event): bool
    {
        $sql = "UPDATE $this->tableName SET 
        libelle = :libelle,
        lieu = :lieu,
        opening_doors_at = :opening_doors_at,
        event_start_at = :event_start_at,
        associate_event = :associate_event,
        limitation_per_swimmer = :limitation_per_swimmer,
        updated_at = NOW()
        WHERE id = :id";

        return $this->execute($sql, [
            'id' => $event->getId(),
            'libelle' => $event->getLibelle(),
            'lieu' => $event->getLieu(),
            'opening_doors_at' => $event->getOpeningDoorsAt()->format('Y-m-d H:i:s'),
            'event_start_at' => $event->getEventStartAt()->format('Y-m-d H:i:s'),
            'associate_event' => $event->getAssociateEvent(),
            'limitation_per_swimmer' => $event->getLimitationPerSwimmer()
        ]);
    }

    /**
     * Supprime un événement par son ID
     * @param int $id
     * @return bool
     */
    public function delete(int $id): bool
    {
        $sql = "DELETE FROM $this->tableName WHERE id = :id";
        return $this->execute($sql, ['id' => $id]);
    }

    /**
     * Mise à jour de l'association entre événements
     * @param int $eventId
     * @param int|null $associateEventId
     * @return bool
     */
    public function updateEventAssociation(int $eventId, ?int $associateEventId): bool
    {
        $sql = "UPDATE $this->tableName SET associate_event = :associate_event, updated_at = NOW() WHERE id = :id";
        return $this->execute($sql, [
            'id' => $eventId,
            'associate_event' => $associateEventId
        ]);
    }

    /**
     * Hydrate un objet Events à partir d'un tableau de données
     * @param array $data
     * @return Events
     * @throws DateMalformedStringException
     */
    protected function hydrate(array $data): Events
    {
        $event = new Events();
        $event->setId($data['id'])
            ->setLibelle($data['libelle'])
            ->setLieu($data['lieu'])
            ->setOpeningDoorsAt($data['opening_doors_at'])
            ->setEventStartAt($data['event_start_at'])
            ->setLimitationPerSwimmer($data['limitation_per_swimmer'])
            ->setAssociateEvent($data['associate_event'])
            ->setCreatedAt($data['created_at'])
            ->setUpdatedAt($data['updated_at']);

        // Charger l'objet piscine associé
        if ($data['lieu']) {
            $piscinesRepository = new PiscinesRepository();
            $piscine = $piscinesRepository->findById($data['lieu']);
            if ($piscine) {
                $event->setPiscine($piscine);
            }
        }

        // Charger les tarifs associés
        $tarifsRepository = new \app\Repository\TarifsRepository();
        $tarifs = $tarifsRepository->findByEventId($data['id']);
        $event->setTarifs($tarifs);

        // Charger les dates d'inscription associées
        $inscriptionDatesRepository = new EventInscriptionDatesRepository();
        $inscriptionDates = $inscriptionDatesRepository->findByEventId($data['id']);
        $event->setInscriptionDates($inscriptionDates);

        return $event;
    }
}