<?php

namespace app\Repository;

use PDOStatement;
use app\Traits\HasPdoConnection;


abstract class AbstractRepository
{
    use HasPdoConnection;

    protected string $tableName;

    public function __construct(string $tableName)
    {
        $this->initPdo();
        $this->tableName = $tableName;
    }

    /**
     * Récupère tous les enregistrements d'une table.
     * @return array
     */
    public function findAll(): array
    {
        $stmt = $this->pdo->query("SELECT * FROM $this->tableName");
        return $stmt->fetchAll();
    }


    /**
     * Supprime un enregistrement par son ID.
     * @param int $id
     * @return bool
     */
    public function delete(int $id): bool
    {
        $stmt = $this->prepare("DELETE FROM $this->tableName WHERE id = :id");
        return $stmt->execute(['id' => $id]);
    }

    /**
     * Prépare et exécute une requête SQL avec des paramètres.
     *
     * @param string $sql La requête SQL à exécuter.
     * @param array $params Les paramètres de la requête.
     * @return array Les résultats de la requête.
     */
    protected function query(string $sql, array $params = []): array
    {
        $stmt = $this->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * Prépare et exécute une requête SQL qui ne retourne pas de jeu de résultats (INSERT, UPDATE, DELETE).
     *
     * @param string $sql La requête SQL à exécuter.
     * @param array $params Les paramètres de la requête.
     * @return bool True si la requête a réussi, false sinon.
     */
    protected function execute(string $sql, array $params = []): bool
    {
        return $this->prepare($sql)->execute($params);
    }

    /**
     * Raccourci pour préparer une requête.
     *
     * @param string $sql
     * @return PDOStatement
     */
    protected function prepare(string $sql): PDOStatement
    {
        return $this->pdo->prepare($sql);
    }
}