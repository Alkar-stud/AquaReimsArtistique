<?php
// php
namespace app\DTO;

use JsonSerializable;

readonly class ReservationDetailItemDTO implements JsonSerializable
{
    public function __construct(
        public int $tarifId,
        public ?string $accessCode = null,
        public ?string $name = null,
        public ?string $firstname = null,
        public ?string $justificatifName = null,
        public ?int $placeId = null
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            tarifId: (int)($data['tarifId'] ?? $data['tarif'] ?? 0),
            accessCode: self::nullIfEmpty($data['accessCode'] ?? $data['tarif_access_code'] ?? null),
            name: self::nullIfEmpty($data['name'] ?? null),
            firstname: self::nullIfEmpty($data['firstname'] ?? null),
            justificatifName: self::nullIfEmpty($data['justificatifName'] ?? $data['justificatif_name'] ?? null),
            placeId: (isset($data['placeId']) ? (int)$data['placeId'] : (isset($data['place_number']) && is_numeric($data['place_number']))) ? (int)$data['place_number'] : null,
        );
    }

    public function jsonSerialize(): array
    {
        return [
            'tarifId' => $this->tarifId,
            'accessCode' => $this->accessCode,
            'name' => $this->name,
            'firstname' => $this->firstname,
            'justificatifName' => $this->justificatifName,
            'placeId' => $this->placeId
        ];
    }

    private static function nullIfEmpty(?string $v): ?string
    {
        $v = isset($v) ? trim($v) : null;
        return $v === '' ? null : $v;
    }
}
