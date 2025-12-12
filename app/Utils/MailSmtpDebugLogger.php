<?php
namespace app\Utils;

class MailSmtpDebugLogger
{
    private string $storageDir;
    private string $filename;

    public function __construct()
    {
        $this->storageDir = __DIR__ . '/../../storage/app/private/MailSmtpDebug/';
        if (!is_dir($this->storageDir)) {
            mkdir($this->storageDir, 0770, true);
        }
        $this->filename = $this->storageDir . 'smtp_' . date('Ymd-His') . '_' . uniqid() . '.log';
    }

    public function append(string $line, int $level = 0): void
    {
        $prefix = '[L' . $level . '] ' . date('Y-m-d H:i:s') . ' ';
        @file_put_contents($this->filename, $prefix . $line . PHP_EOL, FILE_APPEND);
    }
}
