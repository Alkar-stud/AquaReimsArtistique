<?php
namespace app\Services\Log\Handler;

use Throwable;

final class SleekDbLogHandler implements LogHandlerInterface
{
    private $store = null;

    public function __construct(string $path, string $storeName = 'log', array $options = [])
    {
        if (class_exists(\SleekDB\SleekDB::class)) {
            $this->store = \SleekDB\SleekDB::store($storeName, rtrim($path, '/'), $options);
        }
    }

    public function handle(array $record): void
    {
        if (!$this->store) {
            return;
        }
        try {
            $this->store->insert($record);
        } catch (Throwable) {
            // silencieux
        }
    }
}
