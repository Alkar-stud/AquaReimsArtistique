<?php

namespace app\Services\Event;

/**
 * Notificateur simple : envoie une notification texte via mail() si configuré,
 * ou écrit dans un fichier fallback.
 */
final class AlertNotifier
{
    private string $fallbackFile;
    private ?string $toEmail;
    private ?string $fromEmail;

    public function __construct(string $storageDir = null)
    {
        $storageDir = $storageDir ?? __DIR__ . '/../../../storage/log';
        if (!is_dir($storageDir)) { @mkdir($storageDir, 0755, true); }
        $this->fallbackFile = rtrim($storageDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'alerts.log';

        $this->toEmail = $_ENV['ALERT_EMAIL'] ?? null;
        $this->fromEmail = $_ENV['ALERT_FROM'] ?? ($_ENV['MAIL_FROM_ADDRESS'] ?? null);
    }

    /**
     * Envoie une notification. Si la configuration e-mail est absente ou que mail() échoue,
     * écrit dans le fichier fallback.
     *
     * @param EventDefinition $def
     * @param array $context
     */
    public function notify(EventDefinition $def, array $context = []): void
    {
        $subject = '[' . strtoupper($def->getLevel()) . '] ' . $def->getCode();
        $body = $this->buildBody($def, $context);

        if ($this->toEmail && $this->fromEmail) {
            $headers = 'From: ' . $this->fromEmail . "\r\n" . 'Content-Type: text/plain; charset=UTF-8';
            // Utiliser mail(); si échoue fallback
            $ok = @mail($this->toEmail, $subject, $body, $headers);
            if ($ok) {
                return;
            }
        }

        // fallback -> écrire dans un fichier
        $line = '[' . (new \DateTimeImmutable())->format('Y-m-d H:i:s') . '] ' . $subject . "\n" . $body . "\n\n";
        @file_put_contents($this->fallbackFile, $line, FILE_APPEND | LOCK_EX);
    }

    private function buildBody(EventDefinition $def, array $context): string
    {
        $ts = (new \DateTimeImmutable())->format('Y-m-d H:i:s');
        $lines = [];
        $lines[] = "Date: $ts";
        $lines[] = 'Environment: ' . ($_ENV['APP_ENV'] ?? 'local');
        $lines[] = 'Event: ' . $def->getCode();
        $lines[] = 'Level: ' . $def->getLevel();
        $lines[] = 'Description: ' . $def->getDescription();
        if (!empty($context['user_id'])) { $lines[] = 'User ID: ' . $context['user_id']; }
        if (!empty($context['ip'])) { $lines[] = 'IP: ' . $context['ip']; }
        if (!empty($context['uri'])) { $lines[] = 'URI: ' . $context['uri']; }
        if (!empty($context['request_id'])) { $lines[] = 'Request ID: ' . $context['request_id']; }

        $lines[] = '\nContext:';
        $json = json_encode($context, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $lines[] = $json === false ? print_r($context, true) : $json;

        return implode("\n", $lines);
    }
}
