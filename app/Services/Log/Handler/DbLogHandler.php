<?php

namespace app\Services\Log\Handler;

use app\Core\Database;
use PDOException;

/**
 * Handler qui persiste certains logs en base (table `logs`).
 *
 * Comportement :
 * - Lit la configuration `config/logging.php` pour `global_db_min_level` et `channel_min_levels`.
 * - Convertit le niveau textuel en entier et compare au seuil applicable.
 * - Si le niveau est suffisant, insère un enregistrement dans la table `logs`.
 * - Ne jette pas d'exception en cas d'erreur pour ne pas impacter l'application.
 */
final class DbLogHandler implements LogHandlerInterface
{
    private const LEVELS = [
        'DEBUG' => 100,
        'INFO' => 200,
        'NOTICE' => 250,
        'WARNING' => 300,
        'ERROR' => 400,
        'CRITICAL' => 500,
        'ALERT' => 550,
        'EMERGENCY' => 600,
    ];

    private int $globalMinLevelInt;
    /** @var array<string,int> */
    private array $channelMinLevels = [];

    /**
     * Constructeur. Aucun argument requis ; on lira la config si présente.
     * @param array|null $options (optionnel) map pour surcharger les seuils
     */
    public function __construct(?array $options = null)
    {
        $configFile = __DIR__ . '/../../../config/logging.php';
        $cfg = [];
        if (is_file($configFile)) {
            try { $cfg = require $configFile; } catch (\Throwable) { $cfg = []; }
        }

        // Valeur par défaut : WARNING
        $global = $options['global_db_min_level'] ?? $cfg['global_db_min_level'] ?? 'WARNING';
        $this->globalMinLevelInt = $this->levelToInt($global);

        $rawChannels = $options['channel_min_levels'] ?? $cfg['channel_min_levels'] ?? [];
        foreach ($rawChannels as $ch => $lv) {
            $this->channelMinLevels[$ch] = $this->levelToInt($lv);
        }
    }

    public function handle(array $record): void
    {
        try {
            $levelName = strtoupper((string)($record['level'] ?? 'INFO'));
            $levelInt = $this->levelToInt($levelName);
            $channel = (string)($record['channel'] ?? 'application');

            $threshold = $this->channelMinLevels[$channel] ?? $this->globalMinLevelInt;
            if ($levelInt < $threshold) {
                return; // Ne pas persister en DB
            }

            // Préparer les valeurs
            $tsu = isset($record['tsu']) ? (int)$record['tsu'] : (int)round(microtime(true) * 1000);
            // construire ts DATETIME(3) à partir de tsu (ms)
            $seconds = intdiv($tsu, 1000);
            $ms = $tsu % 1000;
            $dt = new \DateTime('@' . $seconds);
            $dt->setTimezone(new \DateTimeZone(date_default_timezone_get()));
            $ts = $dt->format('Y-m-d H:i:s') . '.' . str_pad((string)$ms, 3, '0', STR_PAD_LEFT);

            $message = isset($record['message']) ? (string)$record['message'] : '';
            $context = isset($record['context']) ? $record['context'] : [];
            $contextJson = json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

            $user_id = $record['user_id'] ?? null;
            $ip = $record['ip'] ?? null;
            $uri = $record['uri'] ?? null;
            $method = $record['method'] ?? null;
            $duration = isset($record['duration_ms']) ? (float)$record['duration_ms'] : null;
            $request_id = $context['request_id'] ?? ($record['request_id'] ?? null);

            // Insertion en base
            $pdo = Database::getInstance();
            $sql = 'INSERT INTO logs (ts, tsu, level, level_int, channel, message, context, user_id, ip, uri, method, duration_ms, request_id) VALUES (:ts, :tsu, :level, :level_int, :channel, :message, :context, :user_id, :ip, :uri, :method, :duration_ms, :request_id)';
            $stmt = $pdo->prepare($sql);
            $stmt->bindValue(':ts', $ts);
            $stmt->bindValue(':tsu', $tsu, \PDO::PARAM_INT);
            $stmt->bindValue(':level', $levelName);
            $stmt->bindValue(':level_int', $levelInt, \PDO::PARAM_INT);
            $stmt->bindValue(':channel', $channel);
            $stmt->bindValue(':message', $message);
            $stmt->bindValue(':context', $contextJson);
            $stmt->bindValue(':user_id', $user_id === null ? null : (int)$user_id, $user_id === null ? \PDO::PARAM_NULL : \PDO::PARAM_INT);
            $stmt->bindValue(':ip', $ip);
            $stmt->bindValue(':uri', $uri);
            $stmt->bindValue(':method', $method);
            $stmt->bindValue(':duration_ms', $duration === null ? null : $duration);
            $stmt->bindValue(':request_id', $request_id);
            $result = $stmt->execute();

            if (!$result) {
                $errorInfo = $stmt->errorInfo();
                error_log('DbLogHandler execute failed: ' . json_encode($errorInfo));
            }
        } catch (PDOException $e) {
            error_log('DbLogHandler PDOException: ' . $e->getMessage() . ' -- ' . json_encode($record, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        } catch (\Throwable $e) {
            error_log('DbLogHandler Throwable: ' . $e->getMessage() . ' -- ' . json_encode($record, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        }
    }

    /**
     * Convertit un niveau (string ou int) en entier interne.
     * @param string|int $level
     */
    private function levelToInt($level): int
    {
        if (is_int($level)) {
            return $level;
        }
        $s = strtoupper(trim((string)$level));
        if ($s === '') { return self::LEVELS['INFO']; }
        if (isset(self::LEVELS[$s])) { return self::LEVELS[$s]; }

        // Supporter quelques alias comme 'warn'->WARNING
        $aliases = [
            'WARN' => 'WARNING',
            'ERR' => 'ERROR',
            'FATAL' => 'CRITICAL',
        ];
        if (isset($aliases[$s]) && isset(self::LEVELS[$aliases[$s]])) {
            return self::LEVELS[$aliases[$s]];
        }

        // Si on reçoit un nombre en string
        if (is_numeric($s)) {
            return (int)$s;
        }

        // fallback
        return self::LEVELS['INFO'];
    }
}

