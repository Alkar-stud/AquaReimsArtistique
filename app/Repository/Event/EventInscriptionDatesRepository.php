<?php

namespace app\Repository\Event;

use app\Models\Event\EventInscriptionDates;
use app\Repository\AbstractRepository;
use DateMalformedStringException;

class EventInscriptionDatesRepository extends AbstractRepository
{
    public function __construct()
    {
        parent::__construct('events_inscriptions_dates');
    }

    /**
     * Trouve toutes les périodes d'inscription
     * @return EventInscriptionDates[]
     * @throws DateMalformedStringException
     */
    public function findAll(): array
    {
        $sql = "SELECT * FROM $this->tableName ORDER BY start_registration_at";
        $results = $this->query($sql);
        return array_map([$this, 'hydrate'], $results);
    }

    /**
     * Trouve une période d'inscription par son ID
     * @param int $id
     * @return EventInscriptionDates|null
     * @throws DateMalformedStringException
     */
    public function findById(int $id): ?EventInscriptionDates
    {
        $sql = "SELECT * FROM $this->tableName WHERE id = :id";
        $result = $this->query($sql, ['id' => $id]);

        if (!$result) {
            return null;
        }

        return $this->hydrate($result[0]);
    }

    /**
     * Trouve toutes les périodes d'inscription pour un événement donné
     * @param int $eventId
     * @return EventInscriptionDates[]
     * @throws DateMalformedStringException
     */
    public function findByEventId(int $eventId): array
    {
        $sql = "SELECT * FROM $this->tableName WHERE event = :event_id ORDER BY start_registration_at ASC";
        $results = $this->query($sql, ['event_id' => $eventId]);
        return array_map([$this, 'hydrate'], $results);
    }

    /**
     * Insère une nouvelle période d'inscription
     * @param EventInscriptionDates $inscriptionDate
     * @return int ID de la période d'inscription insérée
     */
    public function insert(EventInscriptionDates $inscriptionDate): int
    {
        $sql = "INSERT INTO $this->tableName 
                (event, libelle, start_registration_at, close_registration_at, access_code, created_at)
                VALUES (:event, :libelle, :start_registration_at, :close_registration_at, :access_code, :created_at)";

        $this->execute($sql, [
            'event' => $inscriptionDate->getEvent(),
            'libelle' => $inscriptionDate->getLibelle(),
            'start_registration_at' => $inscriptionDate->getStartRegistrationAt()->format('Y-m-d H:i:s'),
            'close_registration_at' => $inscriptionDate->getCloseRegistrationAt()->format('Y-m-d H:i:s'),
            'access_code' => $inscriptionDate->getAccessCode(),
            'created_at' => date('Y-m-d H:i:s')
        ]);

        return $this->getLastInsertId();
    }

    /**
     * Met à jour une période d'inscription existante
     * @param EventInscriptionDates $inscriptionDate
     */
    public function update(EventInscriptionDates $inscriptionDate): void
    {
        $sql = "UPDATE $this->tableName SET 
                event = :event,
                libelle = :libelle,
                start_registration_at = :start_registration_at,
                close_registration_at = :close_registration_at,
                access_code = :access_code,
                updated_at = NOW()
                WHERE id = :id";

        $this->execute($sql, [
            'id' => $inscriptionDate->getId(),
            'event' => $inscriptionDate->getEvent(),
            'libelle' => $inscriptionDate->getLibelle(),
            'start_registration_at' => $inscriptionDate->getStartRegistrationAt()->format('Y-m-d H:i:s'),
            'close_registration_at' => $inscriptionDate->getCloseRegistrationAt()->format('Y-m-d H:i:s'),
            'access_code' => $inscriptionDate->getAccessCode()
        ]);
    }

    /**
     * Supprime une période d'inscription par son ID
     * @param int $id
     * @return bool
     */
    public function delete(int $id): bool
    {
        $sql = "DELETE FROM $this->tableName WHERE id = :id";
        return $this->execute($sql, ['id' => $id]);
    }

    /**
     * Supprime toutes les périodes d'inscription pour un événement donné
     * @param int $eventId
     * @return bool
     */
    public function deleteByEventId(int $eventId): bool
    {
        $sql = "DELETE FROM $this->tableName WHERE event = :event_id";
        return $this->execute($sql, ['event_id' => $eventId]);
    }

    /**
     * Hydrate un objet EventInscriptionDates à partir des données
     */
    protected function hydrate(array $data): EventInscriptionDates
    {
        $inscriptionDate = new \app\Models\Event\EventInscriptionDates();
        $inscriptionDate->setId($data['id'])
            ->setEvent($data['event'])
            ->setLibelle($data['libelle'])
            ->setStartRegistrationAt($data['start_registration_at'])
            ->setCloseRegistrationAt($data['close_registration_at'])
            ->setAccessCode($data['access_code'])
            ->setCreatedAt($data['created_at'])
            ->setUpdatedAt($data['updated_at']);

        return $inscriptionDate;
    }
}