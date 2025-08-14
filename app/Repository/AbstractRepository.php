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

    public function __construct(string $tableName)
    {
        $this->logService = new LogService();
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
        try {
            $start = microtime(true);
            $stmt = $this->pdo->prepare($sql);
            $result = $stmt->execute($params);

            $operation = $this->detectSqlOperation($sql);
            $this->logDatabaseOperation($sql, $params, $operation);

            return $result;
        } catch (\PDOException $e) {
            $this->logDatabaseOperation($sql, $params, 'ERROR');
            throw $e;
        }
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
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);

            // Log de la lecture (optionnel - peut être désactivé si trop verbeux)
            $this->logService->logDatabase(
                'SELECT',
                $this->tableName,
                [
                    'sql' => $this->sanitizeSql($sql),
                    'params_count' => count($params)
                ]
            );

            return $stmt->fetchAll();
        } catch (\PDOException $e) {
            $this->logService->logDatabase(
                'SELECT_ERROR',
                $this->tableName,
                [
                    'sql' => $sql,
                    'error' => $e->getMessage()
                ]
            );
            throw $e;
        }
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


}