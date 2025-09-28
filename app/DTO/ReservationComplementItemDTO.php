<?php
namespace app\DTO;

use JsonSerializable;

readonly class ReservationComplementItemDTO implements JsonSerializable
{
    public function __construct(
        public int $tarifId,
        public int $qty,
        public ?string $accessCode = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            tarifId: (int)($data['tarifId'] ?? $data['tarif'] ?? 0),
            qty: (int)($data['qty'] ?? 0),
            accessCode: self::nullIfEmpty($data['accessCode'] ?? $data['tarif_access_code'] ?? null),
        );
    }

    public function jsonSerialize(): array
    {
        return [
            'tarifId' => $this->tarifId,
            'qty' => $this->qty,
            'accessCode' => $this->accessCode,
        ];
    }

    private static function nullIfEmpty(?string $v): ?string
    {
        $v = isset($v) ? trim($v) : null;
        return $v === '' ? null : $v;
    }
}
