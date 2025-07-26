<?php

namespace app\Repository;

use app\Models\Nageuses;

class NageusesRepository extends AbstractRepository
{
    public function __construct()
    {
        parent::__construct('nageuses');
    }

    /**
     * Retourne toutes les nageuses.
     * @return Nageuses[]
     */
    public function findAll(): array
    {
        $sql = "SELECT * FROM $this->tableName ORDER BY name;";
        $results = $this->query($sql);
        return array_map([$this, 'hydrate'], $results);
    }

    /**
     * Retourne une nageuse par son ID.
     */
    public function findById(int $id): ?Nageuses
    {
        $sql = "SELECT * FROM $this->tableName WHERE id = :id;";
        $result = $this->query($sql, ['id' => $id]);
        return $result ? $this->hydrate($result[0]) : null;
    }

    /**
     * Retourne les nageuses d'un groupe spécifique.
     */

    public function findByGroupId(int $groupId): array
    {
        $sql = "SELECT n.*, g.libelle AS groupe_libelle
            FROM nageuses n
            LEFT JOIN nageuses_groupes g ON n.groupe = g.id
            WHERE n.groupe = :groupId
            ORDER BY n.name;";
        $results = $this->query($sql, ['groupId' => $groupId]);
        return array_map([$this, 'hydrate'], $results);
    }

    /**
     * Ajoute une nouvelle nageuse.
     */
    public function insert(Nageuses $nageuse): void
    {
        $sql = "INSERT INTO $this->tableName (name, groupe, created_at) VALUES (:name, :groupe, :created_at)";
        $this->execute($sql, [
            'name' => $nageuse->getName(),
            'groupe' => $nageuse->getGroupe(),
            'created_at' => $nageuse->getCreatedAt()->format('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Modifie une nageuse existante.
     */
    public function update(Nageuses $nageuse): void
    {
        $sql = "UPDATE $this->tableName SET name = :name, groupe = :groupe, updated_at = NOW() WHERE id = :id";
        $this->execute($sql, [
            'id' => $nageuse->getId(),
            'name' => $nageuse->getName(),
            'groupe' => $nageuse->getGroupe(),
        ]);
    }

    /**
     * Supprime une nageuse par son ID.
     */
    public function delete(int $id): bool
    {
        $sql = "DELETE FROM $this->tableName WHERE id = :id";
        $this->execute($sql, ['id' => $id]);
        return true;
    }

    /**
     * Transforme un tableau SQL en objet Nageuses.
     */
    private function hydrate(array $data): Nageuses
    {
        $nageuse = new Nageuses();
        $nageuse->setId($data['id'])
            ->setName($data['name'])
            ->setGroupe($data['groupe'] ?? null)
            ->setCreatedAt($data['created_at'])
            ->setUpdatedAt($data['updated_at'] ?? null);
        return $nageuse;
    }
}