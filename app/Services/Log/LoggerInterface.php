<?php
namespace app\Services\Log;

interface LoggerInterface
{
    public function log(string $level, string $channel, string $message, array $context = []): void;

    public function debug(string $channel, string $message, array $context = []): void;
    public function info(string $channel, string $message, array $context = []): void;
    public function notice(string $channel, string $message, array $context = []): void;
    public function warning(string $channel, string $message, array $context = []): void;
    public function error(string $channel, string $message, array $context = []): void;
    public function critical(string $channel, string $message, array $context = []): void;
    public function alert(string $channel, string $message, array $context = []): void;
    public function emergency(string $channel, string $message, array $context = []): void;

    // Helpers
    public function access(array $context): void;
    public function db(string $operation, string $table, array $context): void;
    public function security(string $event, array $context): void;
}
