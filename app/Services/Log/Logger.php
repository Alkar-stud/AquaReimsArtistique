<?php
namespace app\Services\Log;

use app\Enums\LogType;
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
        $normalizedChannel = $this->normalizeChannel($channel);
        $finalMessage = $this->buildRichMessage($normalizedChannel, $message, $context);
        $now = microtime(true);

        $record = [
            'ts' => gmdate('c'),
            'tsu' => (int) round($now * 1000), // millisecondes depuis epoch
            'level' => strtoupper($level),
            'channel' => $normalizedChannel,
            'message' => $finalMessage,
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
    public function notice(string $channel, string $message, array $context = []): void { $this->log('NOTICE', $channel, $message, $context); }
    public function warning(string $channel, string $message, array $context = []): void { $this->log('WARNING', $channel, $message, $context); }
    public function error(string $channel, string $message, array $context = []): void { $this->log('ERROR', $channel, $message, $context); }
    public function critical(string $channel, string $message, array $context = []): void { $this->log('CRITICAL', $channel, $message, $context); }
    public function alert(string $channel, string $message, array $context = []): void { $this->log('ALERT', $channel, $message, $context); }
    public function emergency(string $channel, string $message, array $context = []): void { $this->log('EMERGENCY', $channel, $message, $context); }

    public function access(array $context): void
    {
        $this->info(LogType::ACCESS->value, 'request', $context);
    }

    public function db(string $operation, string $table, array $context): void
    {
        $this->info(LogType::DATABASE->value, $operation, array_merge(['table' => $table], $context));
    }

    public function security(string $event, array $context): void
    {
        $this->warning(LogType::SECURITY->value, $event, $context);
    }

    private function buildRichMessage(string $channel, string $message, array $context): string
    {
        switch ($channel) {
            case LogType::DATABASE->value:
                $parts = [];
                if (!empty($context['query'])) {
                    $parts[] = "SQL: " . preg_replace('/\s+/', ' ', $context['query']);
                }

                // Si le contexte ne contient pas la requête, on retourne le message de base (ex : "SELECT")
                if (empty($parts)) {
                    return $message;
                }
                return implode(' | ', $parts);

            case LogType::ACCESS->value:
                $method = $_SERVER['REQUEST_METHOD'] ?? 'UNK';
                $uri = $context['route'] ?? strtok($_SERVER['REQUEST_URI'] ?? '/', '?');
                $status = $context['status'] ?? '???';
                // Format: GET /gestion/logs
                return "$method $uri -> $status";

            case LogType::URL->value:
            case LogType::URL_ERROR->value:
                $method = $context['method'] ?? $_SERVER['REQUEST_METHOD'] ?? 'UNK';
                $uri = $context['uri'] ?? strtok($_SERVER['REQUEST_URI'] ?? '/', '?');
                return "$method $uri";

            case LogType::SECURITY->value:
                $user = $context['user_id'] ?? 'anonymous';
                $ip = $context['ip'] ?? ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
                return "$message - User: $user, IP: $ip";

            // Ajoutez d'autres cas pour d'autres canaux si nécessaire

            default:
                return $message;
        }
    }

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
