<?php

namespace app\Repository;

use app\Models\Role;

class RoleRepository extends AbstractRepository
{
    public function __construct()
    {
        parent::__construct('roles');
    }

    /**
     * Trouve un rôle par son ID.
     *
     * @param int $id
     * @return Role|null
     */
    public function findById(int $id): ?Role
    {
        $sql = "SELECT * FROM $this->tableName WHERE id = :id";
        $result = $this->query($sql, ['id' => $id]);
        return $result ? $this->hydrate($result[0]) : null;
    }

    /**
     * Crée et remplit un objet Role à partir d'un tableau de données.
     */
    private function hydrate(array $data): Role
    {
        $role = new Role();
        $role->setId($data['id'])
            ->setLibelle($data['libelle'])
            ->setLevel($data['level'])
            ->setCreatedAt($data['created_at'])
            ->setUpdatedAt($data['updated_at']);

        return $role;
    }
}