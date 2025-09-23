<?php

namespace app\Repository\Piscine;

use app\Models\Piscine\Piscine;
use app\Repository\AbstractRepository;

class PiscineRepository extends AbstractRepository
{
    public function __construct()
    {
        parent::__construct('piscine');
    }

    /**
     * Retourne toutes les piscines ordonnées par nom.
     * @return Piscine[]
     */
    public function findAll(): array
    {
        $sql = "SELECT * FROM $this->tableName ORDER BY libelle";
        $rows = $this->query($sql);
        return array_map([$this, 'hydrate'], $rows);
    }

    /**
     * Trouve une piscine par son ID.
     * @param int $id
     * @return Piscine|null
     */
    public function findById(int $id): ?Piscine
    {
        $sql = "SELECT * FROM $this->tableName WHERE id = :id";
        $rows = $this->query($sql, ['id' => $id]);
        return $rows ? $this->hydrate($rows[0]) : null;
    }

    /**
     * Ajoute une nouvelle piscine.
     * @return int ID inséré (0 si échec)
     */
    public function insert(Piscine $piscine): int
    {
        $sql = "INSERT INTO $this->tableName (libelle, adresse, max_places, numbered_seats)
                VALUES (:libelle, :adresse, :max_places, :numbered_seats)";
        $ok = $this->execute($sql, [
            'libelle' => $piscine->getLibelle(),
            'adresse' => $piscine->getAdresse(),
            'max_places' => $piscine->getMaxPlaces(),
            'numbered_seats' => $piscine->getNumberedSeats() ? 1 : 0,
        ]);
        return $ok ? $this->getLastInsertId() : 0;
    }

    /**
     * Mise à jour des données d'une piscine.
     * @param Piscine $piscine
     * @return bool
     */
    public function update(Piscine $piscine): bool
    {
        $sql = "UPDATE {$this->tableName}
                SET libelle = :libelle,
                    adresse = :adresse,
                    max_places = :max_places,
                    numbered_seats = :numbered_seats,
                    updated_at = NOW()
                WHERE id = :id";
        return $this->execute($sql, [
            'libelle' => $piscine->getLibelle(),
            'adresse' => $piscine->getAdresse(),
            'max_places' => $piscine->getMaxPlaces(),
            'numbered_seats' => $piscine->getNumberedSeats() ? 1 : 0,
            'id' => $piscine->getId(),
        ]);
    }

    /**
     * Hydrate une piscine depuis une ligne BDD.
     * @param array<string, mixed> $data
     */
    protected function hydrate(array $data): Piscine
    {
        $piscine = new Piscine();
        $piscine->setId((int)$data['id'])
            ->setLibelle($data['libelle'])
            ->setAdresse($data['adresse'])
            ->setMaxPlaces((int)$data['max_places'])
            ->setNumberedSeats($data['numbered_seats']);

        return $piscine;
    }
}
