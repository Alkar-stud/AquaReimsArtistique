<?php

namespace app\Repository;

use app\Models\Tarifs;
use DateMalformedStringException;

class TarifsRepository extends AbstractRepository
{
    public function __construct()
    {
        parent::__construct('tarifs');
    }

    /*
     * $IsActiveSearch peut être null = on cherche tout, true ou false
     */
    public function findAll(string $filter = 'all', ?bool $IsActiveSearch = null): array
    {
        $where = '';
        $orderby = '';
        if ($filter === 'places') {
            $where = 'WHERE nb_place IS NOT NULL';
        } elseif ($filter === 'autres') {
            $where = 'WHERE nb_place IS NULL';
            $orderby = 'ORDER BY libelle ASC';
        } else {
            $orderby = 'ORDER BY nb_place DESC';
        }
        if ($IsActiveSearch === true) {
            $where == '' ? $where .= 'WHERE ':$where .= ' AND ';
            $where .= 'is_active = 1';
        } else if ($IsActiveSearch === false) {
            $where == '' ? $where .= 'WHERE ':$where .= ' AND ';
            $where .= 'is_active = 0';
        }

        $sql = "SELECT * FROM $this->tableName $where $orderby;";
        $results = $this->query($sql);
        return array_map([$this, 'hydrate'], $results);
    }

    /**
     * @throws DateMalformedStringException
     */
    public function findById(int $id): ?Tarifs
    {
        $sql = "SELECT * FROM $this->tableName WHERE id = :id;";
        $result = $this->query($sql, ['id' => $id]);
        return $result ? $this->hydrate($result[0]) : null;
    }

    /**
     * Récupère tous les tarifs associés à un événement spécifique
     *
     * @param int $eventId ID de l'événement
     * @return Tarifs[] Tableau d'objets Tarifs
     */
    public function findByEventId(int $eventId): array
    {
        $sql = "SELECT t.* FROM $this->tableName t
            INNER JOIN events_tarifs et ON t.id = et.tarif
            WHERE et.event = :event_id
            ORDER BY t.nb_place DESC, t.libelle";

        $results = $this->query($sql, ['event_id' => $eventId]);
        return array_map([$this, 'hydrate'], $results);
    }

    /**
     * Trouve plusieurs tarifs par leurs IDs.
     * @param array $ids
     * @return Tarifs[]
     */
    public function findByIds(array $ids): array
    {
        if (empty($ids)) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $sql = "SELECT * FROM $this->tableName WHERE id IN ($placeholders)";
        $results = $this->query($sql, $ids);
        return array_map([$this, 'hydrate'], $results);
    }

    public function insert(Tarifs $tarif): void
    {
        $sql = "INSERT INTO $this->tableName 
            (id, libelle, description, nb_place, age_min, age_max, max_tickets, price, is_program_show_include, is_proof_required, access_code, is_active, created_at) 
            VALUES (:id, :libelle, :description, :nb_place, :age_min, :age_max, :max_tickets, :price, :is_program_show_include, :is_proof_required, :access_code, :is_active, :created_at)";
        $this->execute($sql, [
            'id' => $tarif->getId(),
            'libelle' => $tarif->getLibelle(),
            'description' => $tarif->getDescription(),
            'nb_place' => $tarif->getNbPlace(),
            'age_min' => $tarif->getAgeMin(),
            'age_max' => $tarif->getAgeMax(),
            'max_tickets' => $tarif->getMaxTickets(),
            'price' => $tarif->getPrice(),
            'is_program_show_include' => $tarif->getIsProgramShowInclude() ? 1 : 0,
            'is_proof_required' => $tarif->getIsProofRequired() ? 1 : 0,
            'access_code' => $tarif->getAccessCode(),
            'is_active' => $tarif->getIsActive() ? 1 : 0,
            'created_at' => $tarif->getCreatedAt()->format('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Ajoute une relation entre un événement et un tarif
     *
     * @param int $eventId ID de l'événement
     * @param int $tarifId ID du tarif
     * @return bool Succès de l'opération
     */
    public function addEventTarif(int $eventId, int $tarifId): bool
    {
        $sql = "INSERT INTO events_tarifs (event, tarif) VALUES (:event_id, :tarif_id)";
        return $this->execute($sql, ['event_id' => $eventId, 'tarif_id' => $tarifId]);
    }

    public function update(Tarifs $tarif): void
    {
        $sql = "UPDATE $this->tableName SET 
            libelle = :libelle, description = :description, nb_place = :nb_place, age_min = :age_min, age_max = :age_max, 
            max_tickets = :max_tickets, price = :price, is_program_show_include = :is_program_show_include, 
            is_proof_required = :is_proof_required, access_code = :access_code, is_active = :is_active, updated_at = NOW()
            WHERE id = :id";
        $this->execute($sql, [
            'id' => $tarif->getId(),
            'libelle' => $tarif->getLibelle(),
            'description' => $tarif->getDescription(),
            'nb_place' => $tarif->getNbPlace(),
            'age_min' => $tarif->getAgeMin(),
            'age_max' => $tarif->getAgeMax(),
            'max_tickets' => $tarif->getMaxTickets(),
            'price' => $tarif->getPrice(),
            'is_program_show_include' => $tarif->getIsProgramShowInclude() ? 1 : 0,
            'is_proof_required' => $tarif->getIsProofRequired() ? 1 : 0,
            'access_code' => $tarif->getAccessCode(),
            'is_active' => $tarif->getIsActive() ? 1 : 0,
        ]);
    }

    public function delete(int $id): bool
    {
        $sql = "DELETE FROM $this->tableName WHERE id = :id";
        $this->execute($sql, ['id' => $id]);
        return true;
    }

    /**
     * Supprime toutes les relations entre un événement et ses tarifs
     *
     * @param int $eventId ID de l'événement
     * @return bool Succès de l'opération
     */
    public function deleteEventTarifs(int $eventId): bool
    {
        $sql = "DELETE FROM events_tarifs WHERE event = :event_id";
        return $this->execute($sql, ['event_id' => $eventId]);
    }

    /**
     * @throws DateMalformedStringException
     */
    public function hydrate(array $data): Tarifs
    {
        $tarif = new Tarifs();
        $tarif->setId($data['id'])
            ->setLibelle($data['libelle'])
            ->setDescription($data['description'])
            ->setNbPlace($data['nb_place'])
            ->setAgeMin($data['age_min'])
            ->setAgeMax($data['age_max'])
            ->setMaxTickets($data['max_tickets'])
            ->setPrice((int)$data['price'])
            ->setIsProgramShowInclude($data['is_program_show_include'])
            ->setIsProofRequired($data['is_proof_required'])
            ->setAccessCode($data['access_code'])
            ->setIsActive($data['is_active'])
            ->setCreatedAt($data['created_at'])
            ->setUpdatedAt($data['updated_at']);
        return $tarif;
    }
}