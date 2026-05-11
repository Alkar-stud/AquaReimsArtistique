<?php

namespace app\Models\Log;

use app\Models\AbstractModel;
use DateTimeInterface;

class Logs extends AbstractModel
{
    private int $tsu;
    private string $level;
    private int $level_int;
    private string $channel;
    private string $message;
    private array $context = [];
    private ?int $user_id = null;
    private ?string $ip = null;
    private ?string $uri = null;
    private ?string $method = null;
    private ?float $duration_ms = null;
    private ?string $request_id = null;

    // --- GETTERS ---
    public function getTsu(): int { return $this->tsu; }
    public function getLevel(): string { return $this->level; }
    public function getLevelInt(): int { return $this->level_int; }
    public function getChannel(): string { return $this->channel; }
    public function getMessage(): string { return $this->message; }
    public function getContext(): array { return $this->context; }
    public function getUserId(): ?int { return $this->user_id; }
    public function getIp(): ?string { return $this->ip; }
    public function getUri(): ?string { return $this->uri; }
    public function getMethod(): ?string { return $this->method; }
    public function getDurationMs(): ?float { return $this->duration_ms; }
    public function getRequestId(): ?string { return $this->request_id; }

    // --- SETTERS ---
    public function setTsu(int $tsu): static { $this->tsu = $tsu; return $this; }
    public function setLevel(string $level): static { $this->level = $level; return $this; }
    public function setLevelInt(int $level_int): static { $this->level_int = $level_int; return $this; }
    public function setChannel(string $channel): static { $this->channel = $channel; return $this; }
    public function setMessage(string $message): static { $this->message = $message; return $this; }
    public function setContext(array $context): static { $this->context = $context; return $this; }
    public function setUserId(?int $user_id): static { $this->user_id = $user_id; return $this; }
    public function setIp(?string $ip): static { $this->ip = $ip; return $this; }
    public function setUri(?string $uri): static { $this->uri = $uri; return $this; }
    public function setMethod(?string $method): static { $this->method = $method; return $this; }
    public function setDurationMs(?float $duration_ms): static { $this->duration_ms = $duration_ms; return $this; }
    public function setRequestId(?string $request_id): static { $this->request_id = $request_id; return $this; }

    /**
     * Convertit l'objet en tableau pour sérialisation / stockage
     * @return array
     */
    public function toArray(): array
    {
        return [
            'id' => $this->getId(),
            'ts' => $this->getCreatedAt()?->format(DateTimeInterface::ATOM),
            'tsu' => $this->getTsu(),
            'level' => $this->getLevel(),
            'level_int' => $this->getLevelInt(),
            'channel' => $this->getChannel(),
            'message' => $this->getMessage(),
            'context' => $this->getContext(),
            'user_id' => $this->getUserId(),
            'ip' => $this->getIp(),
            'uri' => $this->getUri(),
            'method' => $this->getMethod(),
            'duration_ms' => $this->getDurationMs(),
            'request_id' => $this->getRequestId(),
            'created_at' => $this->getCreatedAt()?->format('Y-m-d H:i:s'),
            'updated_at' => $this->getUpdatedAt()?->format('Y-m-d H:i:s'),
        ];
    }

}