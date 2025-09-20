<?php

namespace app\Repository\User;

use app\Models\User\Role;
use app\Repository\AbstractRepository;
use DateMalformedStringException;

class RoleRepository extends AbstractRepository
{
    public function __construct()
    {
        parent::__construct('role');
    }

    /*
     * Retourne les rôles triés par level
     */
    public function findAllByLevel(): array
    {
        $sql = "SELECT * FROM $this->tableName ORDER BY `level`;";
        $results = $this->query($sql);
        return array_map([$this, 'hydrate'], $results);
    }

    /**
     * Trouve un rôle par son ID.
     */
    public function findById(int $id): ?Role
    {
        $sql = "SELECT * FROM $this->tableName WHERE id = :id";
        $result = $this->query($sql, ['id' => $id]);
        return $result ? $this->hydrate($result[0]) : null;
    }

    /**
     * Crée et remplit un objet Role à partir d'un tableau de données.
     * @throws DateMalformedStringException
     */
    private function hydrate(array $data): Role
    {
        $role = new Role();
        $role->setId($data['id'])
            ->setLabel($data['label'])
            ->setLevel($data['level'])
            ->setCreatedAt($data['created_at'])
            ->setUpdatedAt($data['updated_at']);
        return $role;
    }
}