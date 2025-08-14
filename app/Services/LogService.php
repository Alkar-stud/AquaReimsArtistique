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

    public function log(LogType $type, string $message, array $context = []): void
    {
        $logData = [
            'timestamp' => new UTCDateTime(),
            'timestamp_readable' => date('Y-m-d H:i:s'),
            'type' => $type->value,
            'message' => $message,
            'context' => $context,
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
            'user_id' => $_SESSION['user']['id'] ?? null,
            'username' => $_SESSION['user']['username'] ?? null,
            'session_id' => session_id(),
            'server' => $_SERVER['SERVER_NAME'] ?? 'unknown'
        ];

        // Log en fichier
        $this->logToFile($type, $logData);

        // Log en MongoDB
        $this->logToMongoDB($type, $logData);
    }

    private function logToFile(LogType $type, array $logData): void
    {
        $filename = $this->logDirectory . $type->value . '_' . date('Y-m-d') . '.log';
        $logLine = '[' . $logData['timestamp_readable'] . '] ' .
            strtoupper($type->value) . ': ' .
            $logData['message'] .
            (!empty($logData['context']) ? ' | Context: ' . json_encode($logData['context']) : '') .
            ' | IP: ' . $logData['ip'] .
            ' | User: ' . (($logData['username'] ?? null) && ($logData['user_id'] ?? null)
                ? $logData['username'] . '(' . $logData['user_id'] . ')'
                : ($logData['username'] ?? $logData['user_id'] ?? 'anonymous')) . PHP_EOL;
        file_put_contents($filename, $logLine, FILE_APPEND | LOCK_EX);
    }

    private function logToMongoDB(LogType $type, array $logData): void
    {
        try {
            // Utiliser un cache pour éviter de recréer les services
            if (!isset($this->mongoServices[$type->value])) {
                $this->mongoServices[$type->value] = new MongoService($type->value);
            }

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
        $context['table'] = $table;
        $this->log(LogType::DATABASE, $operation, $context);
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
}