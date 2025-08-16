<?php

namespace app\Repository;

use PDOStatement;
use app\Traits\HasPdoConnection;
use app\Services\LogService;
use app\Enums\LogType;

abstract class AbstractRepository
{
    use HasPdoConnection;

    protected string $tableName;
    protected LogService $logService;
    private ?PDOStatement $lastStatement = null;

    public function __construct(string $tableName)
    {
        $this->tableName = $tableName;
        $this->logService = new LogService();
        $this->initPdo();
    }

    /**
     * Récupère tous les enregistrements d'une table.
     * @return array
     */
    public function findAll(): array
    {
        $sql = "SELECT * FROM $this->tableName";
        return $this->query($sql);
    }

    /**
     * Supprime un enregistrement par son ID.
     * @param int $id
     * @return bool
     */
    public function delete(int $id): bool
    {
        $sql = "DELETE FROM $this->tableName WHERE id = :id";
        return $this->execute($sql, ['id' => $id]);
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
        $startTime = microtime(true);

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $result = $stmt->fetchAll();

        $this->lastStatement = $stmt;
        $this->logQuery('SELECT', $sql, $params, $startTime, count($result));

        return $result;
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
        $startTime = microtime(true);

        $stmt = $this->pdo->prepare($sql);
        $result = $stmt->execute($params);

        $this->lastStatement = $stmt;
        $operation = $this->getOperationType($sql);
        $affectedRows = $stmt->rowCount();

        $this->logQuery($operation, $sql, $params, $startTime, $affectedRows);

        return $result;
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

    protected function fetchAll(string $sql, array $params = []): array
    {
        return $this->query($sql, $params);
    }

    private function logDatabaseOperation(string $sql, array $params, string $operation): void
    {
        $context = [
            'sql' => $this->sanitizeSql($sql),
            'params' => $this->sanitizeParams($params),
            'affected_table' => $this->tableName
        ];

        $this->logService->logDatabase($operation, $this->tableName, $context);
    }

    private function detectSqlOperation(string $sql): string
    {
        $sql = trim(strtoupper($sql));
        if (strpos($sql, 'INSERT') === 0) return 'INSERT';
        if (strpos($sql, 'UPDATE') === 0) return 'UPDATE';
        if (strpos($sql, 'DELETE') === 0) return 'DELETE';
        if (strpos($sql, 'SELECT') === 0) return 'SELECT';
        return 'UNKNOWN';
    }

    private function sanitizeSql(string $sql): string
    {
        // Remplacer les valeurs sensibles par des placeholders
        return preg_replace('/\s+/', ' ', trim($sql));
    }

    private function sanitizeParams(array $params): array
    {
        // Masquer les mots de passe et données sensibles
        $sanitized = [];
        foreach ($params as $key => $value) {
            if (in_array(strtolower($key), ['password', 'token', 'secret'])) {
                $sanitized[$key] = '[MASKED]';
            } else {
                $sanitized[$key] = is_string($value) && strlen($value) > 100 ?
                    substr($value, 0, 100) . '...' : $value;
            }
        }
        return $sanitized;
    }
    // UNE SEULE méthode de logging - celle qui utilise logDatabase
    private function logQuery(string $operation, string $sql, array $params, float $startTime, int $affectedRows): void
    {
        $context = [
            'query' => $sql,
            'params' => $params,
            'execution_time' => round((microtime(true) - $startTime) * 1000, 2) . 'ms',
            'affected_rows' => $affectedRows
        ];

        $this->logService->logDatabase($operation, $this->tableName, $context);
    }

    private function getOperationType(string $sql): string
    {
        $sql = trim(strtoupper($sql));

        if (str_starts_with($sql, 'SELECT')) return 'SELECT';
        if (str_starts_with($sql, 'INSERT')) return 'INSERT';
        if (str_starts_with($sql, 'UPDATE')) return 'UPDATE';
        if (str_starts_with($sql, 'DELETE')) return 'DELETE';

        return 'UNKNOWN';
    }

    /**
     * Récupère l'ID de la dernière insertion en base de données
     * @return int
     */
    protected function getLastInsertId(): int
    {
        return (int)$this->pdo->lastInsertId();
    }


}