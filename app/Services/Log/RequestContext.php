<?php
namespace app\Services\Log;

final class RequestContext
{
    private static ?string $requestId = null;
    private static float $startTime = 0.0;

    public static function boot(): void
    {
        self::$startTime = microtime(true);
        $incoming = $_SERVER['HTTP_X_REQUEST_ID'] ?? '';
        self::$requestId = $incoming !== '' ? substr($incoming, 0, 128) : bin2hex(random_bytes(12));
        if (!headers_sent()) {
            header('X-Request-Id: ' . self::$requestId);
        }
    }

    public static function getRequestId(): string
    {
        if (!self::$requestId) {
            self::boot();
        }
        return self::$requestId;
    }

    public static function getDurationMs(): float
    {
        if (!self::$startTime) {
            self::$startTime = microtime(true);
        }
        return round((microtime(true) - self::$startTime) * 1000, 2);
    }
}
