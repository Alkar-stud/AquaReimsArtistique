<?php

namespace app\Repository\Nageuse;

use app\Models\Nageuse\GroupesNageuses;
use app\Repository\AbstractRepository;

class GroupesNageusesRepository extends AbstractRepository
{
    public function __construct()
    {
        parent::__construct('nageuses_groupes');
    }

    public function findAll(): array
    {
        $sql = "SELECT * FROM $this->tableName ORDER BY `order`;";
        $results = $this->query($sql);
        return array_map([$this, 'hydrate'], $results);
    }

    public function findById(int $id): ?GroupesNageuses
    {
        $sql = "SELECT * FROM $this->tableName WHERE id = :id;";
        $result = $this->query($sql, ['id' => $id]);
        return $result ? $this->hydrate($result[0]) : null;
    }

    public function insert(GroupesNageuses $groupe): void
    {
        $sql = "INSERT INTO $this->tableName 
            (libelle, coach, is_active, `order`, created_at) 
            VALUES (:libelle, :coach, :is_active, :order, :created_at)";
        $this->execute($sql, [
            'libelle' => $groupe->getLibelle(),
            'coach' => $groupe->getCoach(),
            'is_active' => $groupe->getIsActive() ? 1 : 0,
            'order' => $groupe->getOrder(),
            // Correction ici : conversion DateTime en string
            'created_at' => $groupe->getCreatedAt()->format('Y-m-d H:i:s'),
        ]);
    }

    public function update(GroupesNageuses $groupe): void
    {
        $sql = "UPDATE $this->tableName SET 
            libelle = :libelle, coach = :coach, is_active = :is_active, `order` = :order, updated_at = NOW()
            WHERE id = :id";
        $this->execute($sql, [
            'id' => $groupe->getId(),
            'libelle' => $groupe->getLibelle(),
            'coach' => $groupe->getCoach(),
            'is_active' => $groupe->getIsActive() ? 1 : 0,
            'order' => $groupe->getOrder(),
        ]);
    }

    public function delete(int $id): bool
    {
        $sql = "DELETE FROM $this->tableName WHERE id = :id";
        $this->execute($sql, ['id' => $id]);
        return true;
    }

    private function hydrate(array $data): GroupesNageuses
    {
        $groupe = new GroupesNageuses();
        $groupe->setId($data['id'])
            ->setLibelle($data['libelle'])
            ->setCoach($data['coach'])
            ->setIsActive((bool)$data['is_active'])
            ->setOrder($data['order'])
            ->setCreatedAt($data['created_at'])
            ->setUpdatedAt($data['updated_at']);
        return $groupe;
    }
}