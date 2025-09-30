<?php
namespace app\DTO;

use JsonSerializable;

class ReservationSelectionSessionDTO extends AbstractDTO implements JsonSerializable
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
            swimmerId: isset($data['swimmer_id']) ? (int)$data['swimmer_id']
                : (isset($data['swimmerId']) ? (int)$data['swimmerId'] : null),
            limitPerSwimmer: self::nullIfEmpty($data['limit_per_swimmer'] ?? null),
            accessCode: self::nullIfEmpty($data['access_code_used'] ?? null),
        );
    }

}
