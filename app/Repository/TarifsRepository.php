<?php

namespace app\Repository;

use app\Models\Tarifs;

class TarifsRepository extends AbstractRepository
{
    public function __construct()
    {
        parent::__construct('tarifs');
    }

    public function findAll(string $filter = 'all'): array
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
        $sql = "SELECT * FROM $this->tableName $where $orderby;";
        $results = $this->query($sql);
        return array_map([$this, 'hydrate'], $results);
    }

    public function findById(int $id): ?Tarifs
    {
        $sql = "SELECT * FROM $this->tableName WHERE id = :id;";
        $result = $this->query($sql, ['id' => $id]);
        return $result ? $this->hydrate($result[0]) : null;
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

    private function hydrate(array $data): Tarifs
    {
        $tarif = new Tarifs();
        $tarif->setId($data['id'])
            ->setLibelle($data['libelle'])
            ->setDescription($data['description'])
            ->setNbPlace($data['nb_place'])
            ->setAgeMin($data['age_min'])
            ->setAgeMax($data['age_max'])
            ->setMaxTickets($data['max_tickets'])
            ->setPrice($data['price'])
            ->setIsProgramShowInclude($data['is_program_show_include'])
            ->setIsProofRequired($data['is_proof_required'])
            ->setAccessCode($data['access_code'])
            ->setIsActive($data['is_active'])
            ->setCreatedAt($data['created_at'])
            ->setUpdatedAt($data['updated_at']);
        return $tarif;
    }
}