<?php
namespace app\DTO;

use JsonSerializable;

class ReservationSelectionSessionDTO implements JsonSerializable
{
    public function __construct(
        public int $eventId,
        public int $eventSessionId,
        public ?int $swimmerId = null,
        public ?int $limitPerSwimmer = null,
        public ?string $accessCode = null
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            eventId: (int)($data['event_id'] ?? 0),
            eventSessionId: (int)($data['event_session_id'] ?? 0),
            swimmerId: $data['swimmer_id'] ?? $data['swimmer_id'] !== null ? (int)($data['swimmerId'] ?? $data['swimmer_id']) : null,
            limitPerSwimmer: self::nullIfEmpty($data['limit_per_swimmer'] ?? null),
            accessCode: self::nullIfEmpty($data['access_code_used'] ?? null),
        );
    }

    public function jsonSerialize(): array
    {
        return get_object_vars($this);
    }

    private static function nullIfEmpty(?string $v): ?string
    {
        $v = isset($v) ? trim($v) : null;
        return $v === '' ? null : $v;
    }



}
