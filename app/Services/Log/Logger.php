<?php
namespace app\Services\Log;

use app\Services\Log\Handler\FileLogHandler;
use app\Services\Log\Handler\LogHandlerInterface;
use Throwable;

final class Logger implements LoggerInterface
{
    private static ?self $instance = null;

    /** @var LogHandlerInterface[] */
    private array $handlers;

    private array $maskedKeys;

    private function __construct(array $handlers, array $maskedKeys)
    {
        $this->handlers = $handlers;
        $this->maskedKeys = array_map('strtolower', $maskedKeys);
    }

    public static function init(array $handlers, array $maskedKeys): void
    {
        self::$instance = new self($handlers, $maskedKeys);
    }

    public static function get(): self
    {
        if (!self::$instance) {
            // Fallback minimal: fichier dans storage/log
            $fileHandler = new FileLogHandler(__DIR__ . '/../../../storage/log');
            $masked = (require __DIR__ . '/../../../config/security.php')['sensitive_data_keys'] ?? ['password','token','secret'];
            self::$instance = new self([$fileHandler], $masked);
        }
        return self::$instance;
    }

    public function log(string $level, string $channel, string $message, array $context = []): void
    {
        $record = [
            'ts' => gmdate('c'),
            'level' => strtoupper($level),
            'channel' => $channel,
            'message' => (string)$message,
            'request_id' => RequestContext::getRequestId(),
            'duration_ms' => RequestContext::getDurationMs(),
            'user_id' => $_SESSION['user']['id'] ?? null,
            'ip' => $_SERVER['REMOTE_ADDR'] ?? null,
            'method' => $_SERVER['REQUEST_METHOD'] ?? null,
            'uri' => strtok($_SERVER['REQUEST_URI'] ?? '/', '?'),
            'ua' => $_SERVER['HTTP_USER_AGENT'] ?? null,
            'context' => $this->sanitize($context),
        ];
        foreach ($this->handlers as $h) {
            try { $h->handle($record); } catch (Throwable) { /* ignore */ }
        }
    }

    public function debug(string $channel, string $message, array $context = []): void { $this->log('DEBUG', $channel, $message, $context); }
    public function info(string $channel, string $message, array $context = []): void { $this->log('INFO', $channel, $message, $context); }
    public function warning(string $channel, string $message, array $context = []): void { $this->log('WARNING', $channel, $message, $context); }
    public function error(string $channel, string $message, array $context = []): void { $this->log('ERROR', $channel, $message, $context); }

    public function access(array $context): void { $this->info('access', 'request', $context); }
    public function db(string $operation, string $table, array $context): void { $this->info('db', $operation, array_merge(['table'=>$table], $context)); }
    public function security(string $event, array $context): void { $this->warning('security', $event, $context); }

    private function sanitize(array $context): array
    {
        $out = [];
        foreach ($context as $k => $v) {
            $lk = strtolower((string)$k);
            if (in_array($lk, $this->maskedKeys, true)) {
                $out[$k] = '[MASKED]';
                continue;
            }
            if (is_string($v)) {
                $out[$k] = mb_strlen($v) > 512 ? (mb_substr($v, 0, 512) . '...') : $v;
            } elseif ($v instanceof Throwable) {
                $out[$k] = ['ex'=>get_class($v),'msg'=>$v->getMessage(),'file'=>$v->getFile().':'.$v->getLine()];
            } else {
                $out[$k] = $v;
            }
        }
        return $out;
    }
}
