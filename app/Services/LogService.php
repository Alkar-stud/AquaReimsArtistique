<?php
namespace app\Services;

use app\Enums\LogType;
use Exception;
use MongoDB\BSON\UTCDateTime;

class LogService
{
    private string $logDirectory;
    private array $mongoServices = [];

    public function __construct()
    {
        $this->logDirectory = __DIR__ . '/../../storage/logs/';
        if (!is_dir($this->logDirectory)) {
            mkdir($this->logDirectory, 0755, true);
        }
    }

    public function log(LogType $type, string $message, array $context = [], string $level = 'INFO'): void
    {
        // 1. Récupérer TOUTES les données une seule fois
        $logData = $this->collectLogData($type, $message, $context, $level);

        // 2. Envoyer aux différents supports avec leurs formatages spécifiques
        $this->logToFile($type, $logData);
        $this->logToMongoDB($type, $logData);
    }

    /**
     * Collecte toutes les données de log une seule fois
     */
    private function collectLogData(LogType $type, string $message, array $context, string $level): array
    {
        return [
            'timestamp' => new UTCDateTime(),
            'timestamp_readable' => date('Y-m-d H:i:s'),
            'type' => $type->value,
            'level' => $level,
            'message' => $message,
            'context' => $context,
            'ip' => $this->getClientIp(),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
            'user_id' => $_SESSION['user']['id'] ?? null,
            'username' => $_SESSION['user']['username'] ?? null,
            'session_id' => session_id(),
            'server' => $_SERVER['SERVER_NAME'] ?? 'unknown'
        ];
    }

    /**
     * Récupération robuste de l'IP client
     */
    private function getClientIp(): string
    {
        $ipKeys = [
            'HTTP_CF_CONNECTING_IP',     // Cloudflare
            'HTTP_CLIENT_IP',            // Proxy
            'HTTP_X_FORWARDED_FOR',      // Load Balancer/Proxy
            'HTTP_X_FORWARDED',          // Proxy
            'HTTP_X_CLUSTER_CLIENT_IP',  // Cluster
            'HTTP_FORWARDED_FOR',        // Proxy
            'HTTP_FORWARDED',            // Proxy
            'REMOTE_ADDR'                // Standard
        ];

        foreach ($ipKeys as $key) {
            if (!empty($_SERVER[$key])) {
                $ip = $_SERVER[$key];
                // Si plusieurs IPs (séparées par des virgules), prendre la première
                if (strpos($ip, ',') !== false) {
                    $ip = trim(explode(',', $ip)[0]);
                }
                // Valider que c'est une IP valide
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }

        return 'unknown';
    }

    /**
     * Formatage et écriture en fichier
     */
    private function logToFile(LogType $type, array $logData): void
    {
        $filename = $this->logDirectory . $type->value . '_' . date('Y-m-d') . '.log';

        // Formatage spécifique pour fichier
        $logLine = '[' . $logData['timestamp_readable'] . '] ' .
            '[' . $logData['level'] . '] ' .
            strtoupper($type->value) . ': ' .
            $logData['message'] .
            (!empty($logData['context']) ? ' | Context: ' . json_encode($logData['context']) : '') .
            ' | IP: ' . $logData['ip'] .
            ' | User: ' . (($logData['username'] ?? null) && ($logData['user_id'] ?? null)
                ? $logData['username'] . '(' . $logData['user_id'] . ')'
                : ($logData['username'] ?? $logData['user_id'] ?? 'anonymous')) . PHP_EOL;

        file_put_contents($filename, $logLine, FILE_APPEND | LOCK_EX);
    }

    /**
     * Formatage et écriture en MongoDB
     */
    private function logToMongoDB(LogType $type, array $logData): void
    {
        try {
            if (!isset($this->mongoServices[$type->value])) {
                $this->mongoServices[$type->value] = new MongoService($type->value);
            }

            // Le formatage pour MongoDB est déjà correct dans $logData
            $this->mongoServices[$type->value]->create($logData);
        } catch (Exception $e) {
            error_log("Erreur MongoDB log: " . $e->getMessage());
            $errorLog = $this->logDirectory . 'mongodb_errors_' . date('Y-m-d') . '.log';
            file_put_contents($errorLog, date('Y-m-d H:i:s') . " - " . $e->getMessage() . PHP_EOL, FILE_APPEND);
        }
    }

    // Méthodes spécifiques pour chaque type de log
    public function logAccess(string $action, array $context = []): void
    {
        $this->log(LogType::ACCESS, $action, $context);
    }

    public function logDatabase(string $operation, string $table, array $context = []): void
    {
        $maskedContext = $this->maskSensitiveData($context);

        $message = "Database $operation on table '$table'";

        if (isset($context['query'])) {
            $message .= " | Query: " . $context['query'];
        }

        if (isset($context['params']) && !empty($context['params'])) {
            $maskedParams = $this->maskSensitiveData($context['params']);
            $message .= " | Params: " . json_encode($maskedParams);
        }

        $level = $this->getDatabaseLogLevel($operation, $table);
        $maskedContext['table'] = $table;
        $maskedContext['level'] = $level;

        $this->log(LogType::DATABASE, $message, $maskedContext, $level);
    }

    private function getDatabaseLogLevel(string $operation, string $table): string
    {
        $operation = strtoupper($operation);
        $sensitiveConfig = require __DIR__ . '/../../config/security.php';
        $criticalTables = $sensitiveConfig['critical_tables'] ?? [];

        // DELETE = toujours DANGER
        if ($operation === 'DELETE') {
            return 'DANGER';
        }

        // UPDATE sur table critique = DANGER
        if ($operation === 'UPDATE' && $this->isTableCritical($table, $criticalTables)) {
            return 'DANGER';
        }

        // INSERT et UPDATE (non critique) = WARNING
        if (in_array($operation, ['INSERT', 'UPDATE'])) {
            return 'WARNING';
        }

        // SELECT DEBUG
        if (in_array($operation, ['SELECT'])) {
            return 'DEBUG';
        }
        // et autres = INFO (par défaut)
        return 'INFO';
    }

    private function isTableCritical(string $table, array $criticalTables): bool
    {
        $tableLower = strtolower($table);

        foreach ($criticalTables as $criticalTable) {
            if (str_contains($tableLower, strtolower($criticalTable))) {
                return true;
            }
        }

        return false;
    }

    public function logUrl(string $url, string $method, array $context = []): void
    {
        $context['method'] = $method;
        $context['url'] = $url;
        $this->log(LogType::URL, "URL accessed: $method $url", $context);
    }

    public function logRoute(string $route, string $controller, string $action, array $context = []): void
    {
        $context['controller'] = $controller;
        $context['action'] = $action;
        $this->log(LogType::ROUTE, "Route called: $route -> $controller::$action", $context);
    }

    public function logUrlError(string $url, string $method, int $statusCode, array $context = []): void
    {
        $context['method'] = $method;
        $context['url'] = $url;
        $context['status_code'] = $statusCode;
        $context['referer'] = $_SERVER['HTTP_REFERER'] ?? null;

        $this->log(LogType::URL_ERROR, "Error {$statusCode}: {$method} {$url}", $context);
    }

    public function getAllLogs(string $dateStart, string $dateEnd, int $limit, int $offset, bool $excludeLogsRoute = false, string $level = 'all'): array
    {
        $logs = [];

        foreach (LogType::cases() as $logType) {
            $typeLogs = $this->getLogsByType($logType, $dateStart, $dateEnd, $limit * 2, 0, $excludeLogsRoute, $level);
            $logs = array_merge($logs, $typeLogs);
        }

        usort($logs, function($a, $b) {
            return strtotime($b['timestamp']) <=> strtotime($a['timestamp']);
        });

        return array_slice($logs, $offset, $limit);
    }

    public function getLogsByType(LogType $type, string $datetimeStart, string $datetimeEnd, int $limit, int $offset, bool $excludeLogsRoute = false, string $level = 'all'): array
    {
        $mongoService = new MongoService($type->value);

        $filter = [
            'timestamp' => [
                '$gte' => new \MongoDB\BSON\UTCDateTime(strtotime($datetimeStart) * 1000),
                '$lte' => new \MongoDB\BSON\UTCDateTime(strtotime($datetimeEnd) * 1000)
            ]
        ];

        // Filtre par niveau si spécifié
        if ($level !== 'all') {
            $filter['level'] = $level;
        }

        // Filtre d'exclusion des logs de consultation
        if ($excludeLogsRoute) {
            $filter['$and'] = array_merge($filter['$and'] ?? [], [
                [
                    '$or' => [
                        ['context.url' => ['$not' => ['$regex' => '/gestion/logs']]],
                        ['context.url' => ['$exists' => false]]
                    ]
                ],
                [
                    '$or' => [
                        ['message' => ['$not' => ['$regex' => '/gestion/logs']]],
                        ['message' => ['$exists' => false]]
                    ]
                ]
            ]);
        }

        $options = [
            'sort' => ['timestamp' => -1],
            'limit' => $limit,
            'skip' => $offset
        ];

        $cursor = $mongoService->find($filter, $options);
        $logs = [];

        foreach ($cursor as $document) {
            // Convertir le document MongoDB en array PHP
            $logEntry = $document->getArrayCopy();

            // Convertir seulement le timestamp pour l'affichage
            if (isset($logEntry['timestamp'])) {
                $utcDateTime = $logEntry['timestamp']->toDateTime();
                $utcDateTime->setTimezone(new \DateTimeZone(date_default_timezone_get()));
                $logEntry['timestamp'] = $utcDateTime->format('Y-m-d H:i:s');
            }

            // Convertir l'ObjectId en string pour l'affichage
            if (isset($logEntry['_id'])) {
                $logEntry['_id'] = (string)$logEntry['_id'];
            }

            $logs[] = $logEntry;
        }

        return $logs;
    }

    public function countAllLogs(string $dateStart, string $dateEnd, bool $excludeLogsRoute = false, string $level = 'all'): int
    {
        $total = 0;

        foreach (LogType::cases() as $logType) {
            $total += $this->countLogsByType($logType, $dateStart, $dateEnd, $excludeLogsRoute, $level);
        }

        return $total;
    }

    public function countLogsByType(LogType $type, string $dateStart, string $dateEnd, bool $excludeLogsRoute = false, string $level = 'all'): int
    {
        $mongoService = new MongoService($type->value);

        $filter = [
            'timestamp' => [
                '$gte' => new \MongoDB\BSON\UTCDateTime(strtotime($dateStart) * 1000),
                '$lte' => new \MongoDB\BSON\UTCDateTime(strtotime($dateEnd) * 1000)
            ]
        ];

        // Filtre par niveau si spécifié
        if ($level !== 'all') {
            $filter['level'] = $level;
        }

        // Filtre d'exclusion des logs de consultation
        if ($excludeLogsRoute) {
            $filter['$and'] = array_merge($filter['$and'] ?? [], [
                [
                    '$or' => [
                        ['context.url' => ['$not' => ['$regex' => '/gestion/logs']]],
                        ['context.url' => ['$exists' => false]]
                    ]
                ],
                [
                    '$or' => [
                        ['message' => ['$not' => ['$regex' => '/gestion/logs']]],
                        ['message' => ['$exists' => false]]
                    ]
                ]
            ]);
        }

        return $mongoService->countDocuments($filter);
    }

    private function maskSensitiveData(array $data): array
    {
        $sensitiveConfig = require __DIR__ . '/../../config/security.php';
        $sensitiveKeys = $sensitiveConfig['sensitive_data_keys'] ?? [];

        return $this->maskSensitiveDataRecursive($data, $sensitiveKeys);
    }

    private function maskSensitiveDataRecursive(array $data, array $sensitiveKeys): array
    {
        $masked = [];

        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $masked[$key] = $this->maskSensitiveDataRecursive($value, $sensitiveKeys);
            } elseif (is_string($key) && $this->isSensitiveKey($key, $sensitiveKeys)) {
                $masked[$key] = $this->maskValue($value);
            } else {
                $masked[$key] = $value;
            }
        }

        return $masked;
    }

    private function isSensitiveKey(string $key, array $sensitiveKeys): bool
    {
        $keyLower = strtolower($key);

        foreach ($sensitiveKeys as $sensitiveKey) {
            if (str_contains($keyLower, strtolower($sensitiveKey))) {
                return true;
            }
        }

        return false;
    }

    private function maskValue($value): string
    {
        if ($value === null || $value === '') {
            return (string)$value;
        }

        $length = strlen((string)$value);

        if ($length <= 3) {
            return '***';
        } elseif ($length <= 8) {
            return substr($value, 0, 1) . str_repeat('*', $length - 2) . substr($value, -1);
        } else {
            return substr($value, 0, 2) . str_repeat('*', $length - 4) . substr($value, -2);
        }
    }

}