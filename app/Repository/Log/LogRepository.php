<?php
namespace app\Repository\Log;

use app\Enums\LogType;
use app\Services\Log\Handler\FileLogHandler;
use FilesystemIterator;
use Generator;
use RuntimeException;
use SplMaxHeap;

class LogRepository
{
    private string $logDirectory;

    public function __construct()
    {
        // Par défaut
        $dir = __DIR__ . '/../../../storage/log';

        // Essayer de récupérer le répertoire depuis config/logging.php (premier FileLogHandler)
        $configFile = __DIR__ . '/../../../config/logging.php';
        if (is_file($configFile)) {
            $config = require $configFile;
            foreach (($config['handlers'] ?? []) as $def) {
                if (($def['class'] ?? null) === FileLogHandler::class && !empty($def['args'][0])) {
                    $dir = (string)$def['args'][0];
                    break;
                }
            }
        }

        $this->logDirectory = $dir;
    }

    public function search(array $filters, int $limit, int $offset): array
    {
        $results = [];
        $count = 0;
        $skipped = 0;

        foreach ($this->readLogsDescending() as $logEntry) {
            if ($this->matches($logEntry, $filters)) {
                if ($skipped < $offset) {
                    $skipped++;
                    continue;
                }

                $results[] = $logEntry;
                $count++;

                if ($count >= $limit) {
                    break;
                }
            }
        }

        return $results;
    }

    public function count(array $filters): int
    {
        $total = 0;
        foreach ($this->readLogsDescending() as $logEntry) {
            if ($this->matches($logEntry, $filters)) {
                $total++;
            }
        }
        return $total;
    }

    private function readLogsDescending(): Generator
    {
        // Max-heap: extrait toujours l’élément le plus "grand" (ici: le plus récent)
        $heap = new class extends SplMaxHeap {
            protected function compare($a, $b): int
            {
                $aMs = isset($a['tsu']) ? (int)$a['tsu'] : (int) (strtotime($a['ts'] ?? '1970-01-01') * 1000);
                $bMs = isset($b['tsu']) ? (int)$b['tsu'] : (int) (strtotime($b['ts'] ?? '1970-01-01') * 1000);

                if ($aMs === $bMs) {
                    // Critère stable de repli pour éviter les regroupements
                    return (int) (($a['_seq'] ?? 0) <=> ($b['_seq'] ?? 0));
                }
                return $aMs <=> $bMs;
            }
        };

        $seq = 0; // séquence d’insertion pour stabilité

        try {
            $iterator = new FilesystemIterator($this->logDirectory, FilesystemIterator::SKIP_DOTS);
            $files = iterator_to_array($iterator);
            $fileHandles = [];

            foreach ($files as $fileInfo) {
                if (
                    !$fileInfo->isFile() ||
                    !$fileInfo->isReadable() ||
                    !str_ends_with($fileInfo->getBasename(), '.log') // ne lit que les .log du jour
                ) {
                    continue;
                }

                $file = $fileInfo->openFile('r');
                $file->seek(PHP_INT_MAX);
                $lineNum = $file->key();

                if ($lineNum >= 0) {
                    $basename = $file->getBasename();

                    while ($lineNum >= 0) {
                        $file->seek($lineNum);
                        $lineContent = trim((string)$file->current());
                        if ($lineContent !== '') {
                            $logData = json_decode($lineContent, true);
                            if (is_array($logData) && isset($logData['ts'])) {
                                $fileHandles[$basename] = ['handle' => $file, 'line' => $lineNum];
                                $logData['_src'] = $basename;
                                $logData['_seq'] = ++$seq;
                                $heap->insert($logData);
                                break;
                            }
                        }
                        $lineNum--;
                    }
                }
            }

            while (!$heap->isEmpty()) {
                $latestLog = $heap->extract();

                $clean = $latestLog;
                unset($clean['_src'], $clean['_seq']);
                $clean['channel'] = $this->normalizeChannel($clean['channel'] ?? null);

                yield $clean;

                $src = $latestLog['_src'] ?? null;
                if ($src && isset($fileHandles[$src])) {
                    $fh = $fileHandles[$src]['handle'];
                    $ln = $fileHandles[$src]['line'] - 1;

                    while ($ln >= 0) {
                        $fh->seek($ln);
                        $lineContent = trim((string)$fh->current());
                        if ($lineContent !== '') {
                            $logData = json_decode($lineContent, true);
                            if (is_array($logData) && isset($logData['ts'])) {
                                $logData['_src'] = $src;
                                $logData['_seq'] = ++$seq;
                                $heap->insert($logData);
                                $fileHandles[$src]['line'] = $ln;
                                break;
                            }
                        }
                        $ln--;
                    }

                    $fileHandles[$src]['line'] = max($ln, -1);
                }
            }
        } catch (RuntimeException $e) {
            error_log("Erreur lors de la lecture du répertoire de logs '$this->logDirectory': " . $e->getMessage());
            return;
        }
    }

    private function matches(array $logEntry, array $filters): bool
    {
        if (!empty($filters['level'])) {
            $logLevel = strtoupper($logEntry['level'] ?? '');
            if (is_array($filters['level'])) {
                // Le filtre est un tableau de niveaux (ex: ['WARNING', 'ERROR', ...])
                if (!in_array($logLevel, $filters['level'], true)) return false;
            } elseif (strcasecmp($logLevel, $filters['level']) !== 0) {
                // Le filtre est une chaîne unique (comportement précédent)
                return false;
            }
        }
        if (!empty($filters['channel']) && strcasecmp($logEntry['channel'] ?? '', $filters['channel']) !== 0) return false;
        if (!empty($filters['ip']) && ($logEntry['ip'] ?? '') !== $filters['ip']) return false;

        if (!empty($filters['user'])) {
            $logUser = (string)($logEntry['user_id'] ?? 'anonymous');
            if (strcasecmp($logUser, $filters['user']) !== 0) {
                return false;
            }
        }

        return true;
    }

    private function normalizeChannel(?string $channel): string
    {
        $candidate = trim((string)$channel);
        if ($candidate === '') {
            return LogType::APPLICATION->value;
        }

        foreach (LogType::cases() as $case) {
            if (strcasecmp($candidate, $case->value) === 0) {
                return $case->value;
            }
        }

        $aliases = [
            'database'    => LogType::DATABASE->value,
            'sql'         => LogType::SQL_ERROR->value,
            'sql_error'   => LogType::SQL_ERROR->value,
            'sql-error'   => LogType::SQL_ERROR->value,
            'http'        => LogType::URL->value,
            'request'     => LogType::ACCESS->value,
            'app'         => LogType::APPLICATION->value,
            'application' => LogType::APPLICATION->value,
            'security'    => LogType::SECURITY->value,
            'url_error'   => LogType::URL_ERROR->value,
            'url-error'   => LogType::URL_ERROR->value,
        ];
        $lk = strtolower($candidate);
        if (isset($aliases[$lk])) {
            return $aliases[$lk];
        }

        return LogType::APPLICATION->value;
    }
}
