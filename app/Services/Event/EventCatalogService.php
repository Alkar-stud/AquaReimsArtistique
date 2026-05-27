<?php

namespace app\Services\Event;

use DateTimeImmutable;

/**
 * Service pour interroger le catalogue d'évènements et appliquer des règles simples
 * (décision de niveau, autorisation, et rate-limit pour notifications).
 */
final class EventCatalogService
{
    private string $storageDir;
    private string $alertsStateFile;

    public function __construct(?string $storageDir = null)
    {
        $this->storageDir = $storageDir ?? __DIR__ . '/../../../storage/log';
        if (!is_dir($this->storageDir)) {
            @mkdir($this->storageDir, 0755, true);
        }
        $this->alertsStateFile = rtrim($this->storageDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'alerts_last_sent.json';
    }

    public function getDefinition(string $eventCode): ?EventDefinition
    {
        return EventCatalog::get($eventCode);
    }

    public function isKnown(string $eventCode): bool
    {
        return $this->getDefinition($eventCode) !== null;
    }

    /**
     * Renvoie le niveau effectif à appliquer pour cet événement. Si inconnu, renvoie WARNING par défaut.
     */
    public function decideLevel(string $eventCode): string
    {
        $def = $this->getDefinition($eventCode);
        if ($def) {
            return $def->getLevel();
        }
        return 'WARNING';
    }

    /**
     * Indique si l'événement doit déclencher une notification maintenant.
     * Applique un simple rate-limit par eventCode en écrivant le timestamp du dernier envoi.
     */
    public function shouldNotify(string $eventCode, ?int $explicitRateLimitSeconds = null): bool
    {
        $def = $this->getDefinition($eventCode);
        if (!$def) {
            // si inconnu, on considère non notifiable par défaut
            return false;
        }
        if (!$def->isNotifiable()) {
            return false;
        }

        $limit = $explicitRateLimitSeconds ?? $def->getRateLimitSeconds() ?? 3600;

        $state = $this->readAlertsState();
        $now = (int)round(microtime(true));

        $key = $eventCode;
        $last = isset($state[$key]) ? (int)$state[$key] : 0;
        if ($now - $last < $limit) {
            return false;
        }

        // on enregistre le nouvel envoi
        $state[$key] = $now;
        $this->writeAlertsState($state);
        return true;
    }

    private function readAlertsState(): array
    {
        if (!is_file($this->alertsStateFile)) {
            return [];
        }
        $raw = @file_get_contents($this->alertsStateFile);
        if ($raw === false) {
            return [];
        }
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }

    private function writeAlertsState(array $state): void
    {
        $tmp = $this->alertsStateFile . '.tmp';
        @file_put_contents($tmp, json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        @rename($tmp, $this->alertsStateFile);
        @chmod($this->alertsStateFile, 0644);
    }
}
