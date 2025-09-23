<?php
namespace app\Services\Log\Handler;

final class FileLogHandler implements LogHandlerInterface
{
    public function __construct(
        private readonly string $dir,
        private readonly int $maxBytes = 10_000_000,
        private readonly int $maxFiles = 7
    ) {
        if (!is_dir($this->dir)) {
            @mkdir($this->dir, 0775, true);
        }
    }

    public function handle(array $record): void
    {
        $file = rtrim($this->dir, '/') . '/' . ($record['channel'] ?? 'app') . '.log';
        $this->rotateIfNeeded($file);
        $line = json_encode($record, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($line === false) {
            return;
        }
        @file_put_contents($file, $line . PHP_EOL, FILE_APPEND | LOCK_EX);
    }

    private function rotateIfNeeded(string $file): void
    {
        clearstatcache(true, $file);
        if (file_exists($file) && filesize($file) > $this->maxBytes) {
            $ts = gmdate('Ymd_His');
            @rename($file, "{$file}.{$ts}");
            $this->pruneOld($file);
        }
    }

    private function pruneOld(string $file): void
    {
        $files = glob("{$file}.*") ?: [];
        rsort($files, SORT_STRING);
        foreach (array_slice($files, $this->maxFiles) as $old) {
            @unlink($old);
        }
    }
}
