<?php

namespace app\Repository;

use app\Models\Accueil;

class AccueilRepository extends AbstractRepository
{
    public function __construct()
    {
        parent::__construct('accueil');
    }

    public function findById(int $id): array
    {
        $sql = "SELECT * FROM $this->tableName WHERE id = :id;";
        $result = $this->query($sql, ['id' => $id]);
        return array_map([$this, 'hydrate'], $results);
    }

    public function findAll(): array
    {
        $sql = "SELECT * FROM $this->tableName ORDER BY display_until;";
        $results = $this->query($sql);
        return array_map([$this, 'hydrate'], $results);
    }

    public function findDisplayed(): array
    {
        $sql = "SELECT * FROM $this->tableName WHERE is_displayed = '1' AND display_until <= NOW();";
        $results = $this->query($sql);
        return array_map([$this, 'hydrate'], $results);
    }

    public function insert(Accueil $accueil): void
    {
        $sql = "INSERT INTO $this->tableName (is_displayed, display_until, content, created_at) 
            VALUES (:is_displayed, :display_until, :content, :created_at)";
        $this->execute($sql, [
            'is_displayed' => $accueil->isDisplayed(),
            'display_until' => $accueil->getDisplayUntil(),
            'content' => $accueil->getContent(),
            'created_at' => $accueil->getCreatedAt()->format('Y-m-d H:i:s'),
        ]);
    }

    public function update(Accueil $accueil): void
    {
        $sql = "UPDATE $this->tableName SET 
            is_displayed = :is_displayed, display_until = :display_until, content = :content, updated_at = NOW()
            WHERE id = :id";
        $this->execute($sql, [
            'id' => $accueil->getId(),
            'is_displayed' => $accueil->isDisplayed(),
            'display_until' => $accueil->getDisplayUntil(),
            'content' => $accueil->getContent(),
        ]);
    }

    public function delete(int $id): bool
    {
        $sql = "DELETE FROM $this->tableName WHERE id = :id";
        $this->execute($sql, ['id' => $id]);
        return true;
    }

    public function hydrate(array $data): Tarifs
    {
        $accueil = new Accueil();
        $accueil->setId($data['id'])
            ->setIsdisplayed($data['is_displayed'])
            ->setDisplayUntil($data['display_until'])
            ->setContent($data['content'])
            ->setCreatedAt($data['created_at'])
            ->setUpdatedAt($data['updated_at']);
        return $accueil;
    }

}