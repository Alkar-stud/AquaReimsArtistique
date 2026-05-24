<?php
namespace app\Services\Log;

use app\Enums\LogType;
use app\Services\Log\Handler\FileLogHandler;
use app\Services\Log\Handler\LogHandlerInterface;
use app\Utils\Normalize;
use app\Utils\Sanitize;
use Throwable;
use app\Services\Event\EventCatalogService;
use app\Services\Event\AlertNotifier;

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
            // Fallback minimal : fichier dans storage/log
            $fileHandler = new FileLogHandler(__DIR__ . '/../../../storage/log');
            $masked = (require __DIR__ . '/../../../config/security.php')['sensitive_data_keys'] ?? ['password','token','secret'];
            self::$instance = new self([$fileHandler], $masked);
        }
        return self::$instance;
    }

    public function log(string $level, string $channel, string $message, array $context = []): void
    {
        $normalizedChannel = Normalize::normalizeChannel($channel);
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
            'context' => Sanitize::sanitizeLog($context, $this->maskedKeys),
        ];
        foreach ($this->handlers as $h) {
            try { $h->handle($record); } catch (Throwable) { /* ignore */ }
        }
    }

    public function error(string $channel, string $message, array $context = []): void { $this->log('ERROR', $channel, $message, $context); }

    /**
     * Enregistre un événement métier identifié par son code.
     * Le catalogue décide du niveau, du channel et si une notification doit être envoyée.
     * @param string $code
     * @param array $context
     */
    public function event(string $code, array $context = []): void
    {
        try {
            $catalog = new EventCatalogService();
            $def = $catalog->getDefinition($code);

            if ($def === null) {
                // Evénement inconnu -> loguer en CRITICAL sur application
                //On log l'event
                Logger::get()->event(
                    'application.unknown_event',
                    [
                        array_merge(['event_code' => $code], $context)
                    ]);
                return;
            }

            $level = strtoupper($def->getLevel());
            $channel = $def->getChannel() ?: LogType::APPLICATION->value;

            $message = $code;
            $descr = $def->getDescription();
            if ($descr) {
                $message .= ' - ' . $descr;
            }

            // Appel standard aux handlers
            $this->log($level, $channel, $message, $context);

            // Notification si nécessaire et si le rate-limit l'autorise
            if ($def->isNotifiable() && $catalog->shouldNotify($code)) {
                try {
                    $notifier = new AlertNotifier();
                    $notifier->notify($def, $context);
                } catch (Throwable) {
                    // Ne pas laisser une notification casser le flux
                }
            }
        } catch (Throwable) {
            // silencieux : on ne doit pas casser l'application pour un problème de logging
        }
    }

    public function access(array $context): void
    {
        //On log l'event
        Logger::get()->event(
            'access.request',
            [
                'request', $context
            ]);
    }

    public function db(string $operation, string $table, array $context): void
    {
        //On log l'event
        Logger::get()->event(
            'database.query.info',
            [
                'operation' => $operation,
                array_merge(['table' => $table], $context)
            ]);
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
                // Format : GET /gestion/logs
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

}
