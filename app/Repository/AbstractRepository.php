<?php

namespace app\Repository;

use app\Services\Log\Logger;
use app\Traits\HasPdoConnection;
use PDOException;
use PDOStatement;

abstract class AbstractRepository
{
    use HasPdoConnection;

    protected string $tableName;
    private ?PDOStatement $lastStatement = null;
    private ?string $lastError;

    public function __construct(string $tableName)
    {
        $this->tableName = $tableName;
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
    final public function delete(int $id): bool
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

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            $result = $stmt->fetchAll();

            $this->lastStatement = $stmt;
            $this->logQuery('SELECT', $sql, $params, $startTime, count($result));

            return $result;
        } catch (PDOException $e) {
            $this->lastError = $e->getMessage();
            Logger::get()->error(
                'SQL Error',
                $e->getMessage(),
                [
                    'table' => $this->tableName,
                    'query' => $this->sanitizeSql($sql),
                    'params' => $this->sanitizeParams($params),
                    'error' => $e->getMessage(),
                ]
            );
            echo 'Erreur SQL : ' . $e->getMessage() . "\n";
            echo 'Requête : ' . $this->sanitizeSql($sql) . "\n";
            return [];
        }
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
        $this->lastError = null; // Réinitialise l’erreur

        $operation = $this->getOperationType($sql);
        $affectedRows = 0;

        try {
            $stmt = $this->pdo->prepare($sql);
            $result = $stmt->execute($params);

            $this->lastStatement = $stmt;
            $affectedRows = $stmt->rowCount();

            $this->logQuery($operation, $sql, $params, $startTime, $affectedRows);

            return $result;
        } catch (PDOException $e) {
            $this->lastError = $e->getMessage();
            Logger::get()->error(
                'SQL Error',
                $e->getMessage(),
                [
                    'table' => $this->tableName,
                    'query' => $this->sanitizeSql($sql),
                    'params' => $this->sanitizeParams($params),
                    'error' => $e->getMessage(),
                ]
            );
            $this->logQuery($operation, $sql, $params, $startTime, $affectedRows);
            return false;
        }
    }

    /**
     * @param string $sql
     * @return string
     */
    private function sanitizeSql(string $sql): string
    {
        // Remplacer les valeurs sensibles par des placeholders
        return preg_replace('/\s+/', ' ', trim($sql));
    }

    /**
     * @param array $params
     * @return array
     */
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

    /**
     * @param string $operation
     * @param string $sql
     * @param array $params
     * @param float $startTime
     * @param int $affectedRows
     * @return void
     */
    private function logQuery(string $operation, string $sql, array $params, float $startTime, int $affectedRows): void
    {
        $safeSql = $this->sanitizeSql($sql);
        $safeParams = $this->sanitizeParams($params);

        Logger::get()->db($operation, $this->tableName, [
            'query' => $safeSql,
            'params' => $safeParams,
            'execution_ms' => (int) round((microtime(true) - $startTime) * 1000),
            'affected' => $affectedRows,
        ]);
    }

    /**
     * @param string $sql
     * @return string
     */
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

    /**
     * @return string|null
     */
    public function getLastError(): ?string
    {
        return $this->lastError ?? null;
    }

    /**
     * Démarre une transaction.
     */
    public function beginTransaction(): void
    {
        $this->pdo->beginTransaction();
    }

    /**
     * Valide une transaction.
     */
    public function commit(): void
    {
        $this->pdo->commit();
    }

    /**
     * Annule une transaction.
     */
    public function rollBack(): void
    {
        $this->pdo->rollBack();
    }


}