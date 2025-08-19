<?php

namespace app\Models\Reservation;

use DateTimeImmutable;
use app\Utils\DurationHelper;

class ReservationSession
{
    private string $token;
    private array $data = [];
    private int $currentStep = 1;
    private int $maxStep = 7;
    private DateTimeImmutable $createdAt;
    private DateTimeImmutable $expiresAt;

    public function __construct()
    {
        $this->token = bin2hex(random_bytes(16));
        $this->createdAt = new DateTimeImmutable();
        $timeout = DurationHelper::iso8601ToSeconds(TIMEOUT_PLACE_RESERV);
        $this->expiresAt = $this->createdAt->modify("+{$timeout} seconds");
    }

    // Getters et setters
    public function getToken(): string
    {
        return $this->token;
    }

    public function setStep(int $step): self
    {
        if ($step >= 1 && $step <= $this->maxStep) {
            $this->currentStep = $step;
        }
        return $this;
    }

    public function getCurrentStep(): int
    {
        return $this->currentStep;
    }

    public function setData(string $key, $value): self
    {
        $this->data[$key] = $value;
        return $this;
    }

    public function getData(string $key, $default = null)
    {
        return $this->data[$key] ?? $default;
    }

    public function getAllData(): array
    {
        return $this->data;
    }

    public function isExpired(): bool
    {
        return new DateTimeImmutable() > $this->expiresAt;
    }

    public function extendExpiry(): self
    {
        $timeout = DurationHelper::iso8601ToSeconds(TIMEOUT_PLACE_RESERV);
        $this->expiresAt = new DateTimeImmutable("now +{$timeout} seconds");
        return $this;
    }

    // Méthodes de sérialisation pour stocker en session
    public function serialize(): array
    {
        return [
            'token' => $this->token,
            'data' => $this->data,
            'currentStep' => $this->currentStep,
            'createdAt' => $this->createdAt->format('Y-m-d H:i:s'),
            'expiresAt' => $this->expiresAt->format('Y-m-d H:i:s')
        ];
    }

    public static function deserialize(array $data): self
    {
        $session = new self();
        $session->token = $data['token'];
        $session->data = $data['data'];
        $session->currentStep = $data['currentStep'];
        $session->createdAt = new DateTimeImmutable($data['createdAt']);
        $session->expiresAt = new DateTimeImmutable($data['expiresAt']);
        return $session;
    }
}