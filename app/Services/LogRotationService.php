<?php
//Excécuté à l'affichage de la page d'accueil de Gestion : gestion.html.php
namespace app\Services;

use app\Enums\LogType;
use app\Core\DatabaseMongoDB;
use DateTime;
use Exception;

class LogRotationService
{
    private LogService $logService;
    private string $logDirectory;

    public function __construct()
    {
        $this->logService = new LogService();
        $this->logDirectory = __DIR__ . '/../../storage/logs/';
    }

    public function rotateAllLogs(): array
    {
        $report = [
            'files_deleted' => 0,
            'mongo_docs_deleted' => 0,
            'details' => []
        ];

        // Rotation des fichiers
        $filesDeletionResult = $this->rotateFilesByAge();
        $report['files_deleted'] = $filesDeletionResult['deleted_count'];
        if ($filesDeletionResult['deleted_count'] > 0) {
            $report['details'] = array_merge($report['details'], $filesDeletionResult['details']);
        }

        // Rotation MongoDB
        $mongoDeletionResult = $this->rotateMongoBySize();
        $report['mongo_docs_deleted'] = $mongoDeletionResult['deleted_count'];
        if ($mongoDeletionResult['deleted_count'] > 0) {
            $report['details'] = array_merge($report['details'], $mongoDeletionResult['details']);
        }

        // Logger seulement s'il y a eu des suppressions
        if ($report['files_deleted'] > 0 || $report['mongo_docs_deleted'] > 0) {
            $this->logService->log(LogType::ACCESS, 'Rotation automatique des logs effectuée', [
                'files_deleted' => $report['files_deleted'],
                'mongo_docs_deleted' => $report['mongo_docs_deleted'],
                'trigger' => 'gestion_page_view',
                'details' => $report['details']
            ], 'INFO');
        }

        return $report;
    }

    private function rotateFilesByAge(): array
    {
        $deleted = 0;
        $details = [];
        $cutoffDate = date('Y-m-d', strtotime('-' . MAX_LOGS_LIFE . ' days'));

        if (is_dir($this->logDirectory)) {
            $files = glob($this->logDirectory . '*.log');
            foreach ($files as $file) {
                if (is_file($file)) {
                    $fileDate = date('Y-m-d', filemtime($file));
                    if ($fileDate < $cutoffDate) {
                        $fileSize = filesize($file);
                        if (unlink($file)) {
                            $deleted++;
                            $details[] = [
                                'type' => 'file',
                                'name' => basename($file),
                                'size' => $fileSize,
                                'date' => $fileDate
                            ];
                        }
                    }
                }
            }
        }

        return [
            'deleted_count' => $deleted,
            'details' => $details
        ];
    }

    private function rotateMongoBySize(): array
    {
        $totalDeleted = 0;
        $details = [];

        foreach (LogType::cases() as $logType) {
            try {
                $mongoService = new MongoService($logType->value);
                $count = $mongoService->countDocuments();

                if ($count > MAX_LOGS_SIZE) {
                    $excessCount = $count - MAX_LOGS_SIZE;

                    // Supprimer les plus anciens documents
                    $oldestDocs = $mongoService->find(
                        [],
                        [
                            'sort' => ['timestamp' => 1],
                            'limit' => $excessCount
                        ]
                    );

                    $docIds = [];
                    foreach ($oldestDocs as $doc) {
                        $docIds[] = $doc['_id'];
                    }

                    if (!empty($docIds)) {
                        $deleted = $mongoService->delete(['_id' => ['$in' => $docIds]]);
                        if ($deleted > 0) {
                            $totalDeleted += $deleted;
                            $details[] = [
                                'type' => 'mongodb',
                                'collection' => $logType->value,
                                'deleted_count' => $deleted,
                                'total_before' => $count
                            ];
                        }
                    }
                }
            } catch (Exception $e) {
                // Logger uniquement les erreurs de rotation
                $this->logService->log(LogType::DATABASE, 'Erreur lors de la rotation MongoDB: ' . $e->getMessage(), [
                    'collection' => $logType->value,
                    'error' => $e->getMessage()
                ], 'WARNING');
            }
        }

        return [
            'deleted_count' => $totalDeleted,
            'details' => $details
        ];
    }

    public function rotateOldLogs(): array
    {
        $report = [
            'files_deleted' => 0,
            'files_size_freed' => 0,
            'mongo_docs_deleted' => 0,
            'errors' => []
        ];

        try {
            // Rotation des fichiers
            $fileReport = $this->rotateLogFiles();
            $report['files_deleted'] = $fileReport['deleted'];
            $report['files_size_freed'] = $fileReport['size_freed'];

            // Rotation MongoDB
            $mongoReport = $this->rotateMongoLogs();
            $report['mongo_docs_deleted'] = $mongoReport['deleted'];

            // Log de l'opération de rotation
            $this->logService->logAccess('LOG_ROTATION_COMPLETED', [
                'files_deleted' => $report['files_deleted'],
                'files_size_freed' => $this->formatBytes($report['files_size_freed']),
                'mongo_docs_deleted' => $report['mongo_docs_deleted']
            ]);

        } catch (Exception $e) {
            $report['errors'][] = $e->getMessage();
            $this->logService->logAccess('LOG_ROTATION_ERROR', [
                'error' => $e->getMessage()
            ], 'DANGER');
        }

        return $report;
    }

    private function rotateLogFiles(): array
    {
        $deleted = 0;
        $sizeFreed = 0;
        $cutoffDate = new DateTime('-' . MAX_LOGS_LIFE . ' days');

        if (!is_dir($this->logDirectory)) {
            return ['deleted' => 0, 'size_freed' => 0];
        }

        $files = glob($this->logDirectory . '*.log');

        foreach ($files as $file) {
            $fileTime = filemtime($file);
            $fileDate = new DateTime('@' . $fileTime);

            if ($fileDate < $cutoffDate) {
                $size = filesize($file);
                if (unlink($file)) {
                    $deleted++;
                    $sizeFreed += $size;
                }
            }
        }

        return ['deleted' => $deleted, 'size_freed' => $sizeFreed];
    }

    private function rotateMongoLogs(): array
    {
        $totalDeleted = 0;
        $cutoffTimestamp = strtotime('-' . MAX_LOGS_LIFE . ' days') * 1000;

        foreach (LogType::cases() as $logType) {
            try {
                $mongoService = new MongoService($logType->value);

                // Supprimer les anciens logs
                $filter = [
                    'timestamp' => [
                        '$lt' => new \MongoDB\BSON\UTCDateTime($cutoffTimestamp)
                    ]
                ];

                $deleted = $mongoService->delete($filter);
                $totalDeleted += $deleted;

                // Vérifier si on dépasse la limite de taille
                $totalCount = $mongoService->countDocuments([]);
                if ($totalCount > MAX_LOGS_SIZE) {
                    $excessCount = $totalCount - MAX_LOGS_SIZE;

                    // Supprimer les plus anciens logs en excès
                    $oldestLogs = $mongoService->find([], [
                        'sort' => ['timestamp' => 1],
                        'limit' => $excessCount
                    ]);

                    foreach ($oldestLogs as $log) {
                        $mongoService->delete(['_id' => $log['_id']]);
                        $totalDeleted++;
                    }
                }

            } catch (Exception $e) {
                error_log("Erreur rotation MongoDB pour {$logType->value}: " . $e->getMessage());
            }
        }

        return ['deleted' => $totalDeleted];
    }

    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);

        $bytes /= pow(1024, $pow);

        return round($bytes, 2) . ' ' . $units[$pow];
    }

    public function getLogsStatistics(): array
    {
        $stats = [
            'files' => [],
            'mongo' => [],
            'total_file_size' => 0,
            'total_mongo_docs' => 0
        ];

        // Statistiques fichiers
        if (is_dir($this->logDirectory)) {
            $files = glob($this->logDirectory . '*.log');
            foreach ($files as $file) {
                if (is_file($file)) {
                    $size = filesize($file);
                    $lines = $this->countLines($file);

                    $stats['files'][] = [
                        'name' => basename($file),
                        'size' => $size,
                        'lines' => $lines
                    ];

                    $stats['total_file_size'] += $size;
                }
            }
        }

        // Statistiques MongoDB
        foreach (LogType::cases() as $logType) {
            try {
                $mongoService = new MongoService($logType->value);
                $count = $mongoService->countDocuments();

                $stats['mongo'][] = [
                    'name' => $logType->value,
                    'count' => $count
                ];

                $stats['total_mongo_docs'] += $count;
            } catch (Exception $e) {
                $this->logService->log(LogType::DATABASE, 'Erreur lors du comptage MongoDB: ' . $e->getMessage(), [
                    'collection' => $logType->value,
                    'error' => $e->getMessage()
                ], 'WARNING');
            }
        }

        return $stats;
    }

    private function countLines(string $file): int
    {
        $lines = 0;
        $handle = fopen($file, 'r');
        if ($handle) {
            while (($line = fgets($handle)) !== false) {
                $lines++;
            }
            fclose($handle);
        }
        return $lines;
    }
}