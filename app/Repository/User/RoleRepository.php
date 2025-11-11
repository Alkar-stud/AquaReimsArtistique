<?php

namespace app\Repository\User;

use app\Models\User\Role;
use app\Repository\AbstractRepository;

class RoleRepository extends AbstractRepository
{
    public function __construct()
    {
        parent::__construct('role');
    }

    /**
     * Retourne tous les rôles ordonnés par niveau.
     * @return Role[]
     */
    public function findAll(): array
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
     * Hydrate un objet Role depuis une ligne BDD.
     */
    protected function hydrate(array $data): Role
    {
        $role = new Role();
        $role->setId($data['id'])
            ->setLabel($data['label'])
            ->setLevel($data['level']);
        return $role;
    }
}