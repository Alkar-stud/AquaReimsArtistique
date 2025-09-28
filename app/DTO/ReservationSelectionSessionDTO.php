<?php
namespace app\DTO;

use JsonSerializable;

readonly class ReservationSelectionSessionDTO implements JsonSerializable
{
    public function __construct(
        public int $eventId,
        public int $eventSessionId,
        public ?int $swimmerId = null,
        public ?string $accessCode = null
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            eventId: (int)($data['eventId'] ?? $data['event'] ?? 0),
            eventSessionId: (int)($data['eventSessionId'] ?? $data['event_session'] ?? 0),
            swimmerId: $data['swimmerId'] ?? $data['swimmer_id'] !== null ? (int)($data['swimmerId'] ?? $data['swimmer_id']) : null,
            accessCode: self::nullIfEmpty($data['accessCode'] ?? null),
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
