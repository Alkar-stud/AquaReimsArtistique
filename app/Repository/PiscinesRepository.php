<?php

namespace app\Repository;

use app\Models\Piscines;
use DateMalformedStringException;

class PiscinesRepository extends AbstractRepository
{
    public function __construct()
    {
        parent::__construct('piscines');
    }

    /**
     * Retourne toutes les piscines.
     * @return Piscines[]
     */
    public function findAll(): array
    {
        $sql = "SELECT * FROM $this->tableName";
        $results = $this->query($sql);
        return array_map([$this, 'hydrate'], $results);
    }

    /**
     * Trouve une piscine par son ID.
     * @param int $id
     * @return Piscines|null
     * @throws DateMalformedStringException
     */
    public function findById(int $id): ?Piscines
    {
        $sql = "SELECT * FROM $this->tableName WHERE id = :id";
        $result = $this->query($sql, ['id' => $id]);
        return $result ? $this->hydrate($result[0]) : null;
    }

    /**
     * Insère une nouvelle piscine.
     * @param Piscines $piscine
     */
    public function insert(Piscines $piscine): void
    {
        $sql = "INSERT INTO $this->tableName (libelle, adresse, max_places, numbered_seats) 
                VALUES (:libelle, :adresse, :max_places, :numbered_seats)";
        $this->execute($sql, [
            'libelle' => $piscine->getLibelle(),
            'adresse' => $piscine->getAdresse(),
            'max_places' => $piscine->getMaxPlaces(),
            'numbered_seats' => $piscine->getNumberedSeats() ? 1 : 0,
        ]);
    }

    /**
     * Met à jour une piscine existante.
     * @param Piscines $piscine
     */
    public function update(Piscines $piscine): void
    {
        $sql = "UPDATE $this->tableName 
                SET libelle = :libelle, adresse = :adresse, max_places = :max_places, numbered_seats = :numbered_seats
                WHERE id = :id";
        $this->execute($sql, [
            'libelle' => $piscine->getLibelle(),
            'adresse' => $piscine->getAdresse(),
            'max_places' => $piscine->getMaxPlaces(),
            'numbered_seats' => $piscine->getNumberedSeats() ? 1 : 0,
            'id' => $piscine->getId(),
        ]);
    }

    /**
     * Supprime une piscine par son ID.
     * @param int $id
     * @return bool
     */
    public function delete(int $id): bool
    {
        $sql = "DELETE FROM $this->tableName WHERE id = :id";
        $this->execute($sql, ['id' => $id]);
        return true;
    }

    /**
     * Hydrate un objet Piscines à partir d’un tableau de données.
     * @param array $data
     * @return Piscines
     * @throws DateMalformedStringException
     */
    private function hydrate(array $data): Piscines
    {
        $piscine = new Piscines();
        $piscine->setId($data['id'])
            ->setLibelle($data['libelle'])
            ->setAdresse($data['adresse'])
            ->setMaxPlaces($data['max_places'])
            ->setNumberedSeats($data['numbered_seats'])
            ->setCreatedAt($data['created_at'])
            ->setUpdatedAt($data['updated_at']);
        return $piscine;
    }
}