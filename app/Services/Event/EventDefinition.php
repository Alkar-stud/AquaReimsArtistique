<?php

namespace app\Services\Event;

/**
 * Représente la définition d'un événement métier pour le logging/alerting
 */
final class EventDefinition
{
    private string $code;
    private string $channel;
    private string $level;
    private bool $auditable;
    private bool $notifiable;
    private string $description;
    private bool $expected;
    private ?int $rateLimitSeconds;

    public function __construct(
        string $code,
        string $channel,
        string $level,
        bool $auditable,
        bool $notifiable,
        string $description = '',
        bool $expected = true,
        ?int $rateLimitSeconds = null
    ) {
        $this->code = $code;
        $this->channel = $channel;
        $this->level = $level;
        $this->auditable = $auditable;
        $this->notifiable = $notifiable;
        $this->description = $description;
        $this->expected = $expected;
        $this->rateLimitSeconds = $rateLimitSeconds;
    }

    public function getCode(): string { return $this->code; }
    public function getChannel(): string { return $this->channel; }
    public function getLevel(): string { return $this->level; }
    public function isAuditable(): bool { return $this->auditable; }
    public function isNotifiable(): bool { return $this->notifiable; }
    public function getDescription(): string { return $this->description; }
    public function isExpected(): bool { return $this->expected; }
    public function getRateLimitSeconds(): ?int { return $this->rateLimitSeconds; }
}

