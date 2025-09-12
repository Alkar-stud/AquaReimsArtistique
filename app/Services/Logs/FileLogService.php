<?php

namespace app\Services\Logs;

class FileLogService
{
    private string $logDirectory;

    public function __construct()
    {
        $this->logDirectory = __DIR__ . '/../../storage/logs';
        $this->ensureLogDirectoryExists();
    }

    public function write(array $logData): void
    {
        $date = $logData['timestamp']->format('Y-m-d');
        $filename = $this->getLogFilename($logData['type'], $date);
        $formattedLog = $this->formatLogEntry($logData);

        file_put_contents($filename, $formattedLog . PHP_EOL, FILE_APPEND | LOCK_EX);
    }

    private function ensureLogDirectoryExists(): void
    {
        if (!is_dir($this->logDirectory)) {
            mkdir($this->logDirectory, 0755, true);
        }
    }

    private function getLogFilename(string $type, string $date): string
    {
        return $this->logDirectory . "/{$type}_{$date}.log";
    }

    private function formatLogEntry(array $logData): string
    {
        $timestamp = $logData['timestamp']->format('Y-m-d H:i:s');
        $type = strtoupper($logData['type']);
        $criticality = strtoupper($logData['criticality']);

        $baseInfo = sprintf(
            '[%s] [%s] [%s] User: %s (%s) | IP: %s | Session: %s',
            $timestamp,
            $type,
            $criticality,
            $logData['username'],
            $logData['user_id'] ?? 'N/A',
            $logData['ip_address'],
            $logData['session_id']
        );

        switch ($logData['type']) {
            case 'sensitive_access':
                return $baseInfo . sprintf(
                        ' | URI: %s %s | Additional: %s',
                        $logData['method'],
                        $logData['uri'],
                        json_encode($logData['additional_data'])
                    );

            case 'database_operation':
                return $baseInfo . sprintf(
                        ' | Operation: %s on %s | Record ID: %s | Data: %s',
                        $logData['operation'],
                        $logData['table'],
                        $logData['record_id'] ?? 'N/A',
                        json_encode($logData['data'])
                    );

            case 'error':
                return $baseInfo . sprintf(
                        ' | Error: %s | Context: %s',
                        $logData['message'],
                        json_encode($logData['context'])
                    );

            default:
                return $baseInfo . ' | Data: ' . json_encode($logData);
        }
    }
}