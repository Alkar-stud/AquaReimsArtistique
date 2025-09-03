<?php

namespace app\Repository;

use app\Models\Accueil;

class AccueilRepository extends AbstractRepository
{
    public function __construct()
    {
        parent::__construct('accueil');
    }

    public function findById(int $id): ?Accueil
    {
        $sql = "SELECT * FROM $this->tableName WHERE id = :id;";
        $result = $this->query($sql, ['id' => $id]);
        // Retourne le premier résultat hydraté, ou null si le tableau est vide.
        return !empty($result) ? $this->hydrate($result[0]) : null;
    }

    public function findAll(): array
    {
        $sql = "SELECT * FROM $this->tableName ORDER BY display_until;";
        $results = $this->query($sql);
        return array_map([$this, 'hydrate'], $results);
    }

    public function findDisplayed(): array
    {
        $sql = "SELECT * FROM $this->tableName WHERE display_until >= NOW() ORDER BY display_until;";
        $results = $this->query($sql);
        return array_map([$this, 'hydrate'], $results);
    }

     public function insert(Accueil $accueil): int
    {
        $sql = "INSERT INTO $this->tableName (is_displayed, display_until, content, created_at) 
            VALUES (:is_displayed, :display_until, :content, :created_at)";
        $this->execute($sql, [
            //'is_displayed' => $accueil->isDisplayed(),
            'is_displayed' => (int)$accueil->isDisplayed(),
            'display_until' => $accueil->getDisplayUntil()->format('Y-m-d H:i:s'),
            'content' => $accueil->getContent(),
            'created_at' => $accueil->getCreatedAt()->format('Y-m-d H:i:s'),
        ]);
        return $this->getLastInsertId();
    }

    public function update(Accueil $accueil): void
    {
        $sql = "UPDATE $this->tableName SET 
            is_displayed = :is_displayed, display_until = :display_until, content = :content, updated_at = NOW()
            WHERE id = :id";
        $this->execute($sql, [
            'id' => $accueil->getId(),
            //'is_displayed' => $accueil->isDisplayed(),
            'is_displayed' => (int)$accueil->isDisplayed(),
            'display_until' => $accueil->getDisplayUntil()->format('Y-m-d H:i:s'),
            'content' => $accueil->getContent(),
        ]);
    }

    public function updateStatus(int $id, bool $isDisplayed): void
    {
        $sql = "UPDATE $this->tableName SET is_displayed = :is_displayed, updated_at = NOW() WHERE id = :id";
        $this->execute($sql, [
            'id' => $id,
            'is_displayed' => (int)$isDisplayed,
        ]);
    }

    public function delete(int $id): bool
    {
        $sql = "DELETE FROM $this->tableName WHERE id = :id";
        $this->execute($sql, ['id' => $id]);
        return true;
    }

     public function hydrate(array $data): Accueil
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