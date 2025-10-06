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
        public ?string $access_code = null
    ) {}

    public static function fromArray(array $data): self
    {
        $access = $data['access_code_used']
            ?? $data['access_code']
            ?? $data['accessCode']
            ?? null;

        return new self(
            eventId: (int)($data['event_id'] ?? 0),
            eventSessionId: (int)($data['event_session_id'] ?? 0),
            swimmerId: isset($data['swimmer_id']) ? (int)$data['swimmer_id']
                : (isset($data['swimmerId']) ? (int)$data['swimmerId'] : null),
            limitPerSwimmer: isset($data['limit_per_swimmer']) ? (int)$data['limit_per_swimmer'] : null,
            access_code: self::nullIfEmpty($access),
        );
    }
}
