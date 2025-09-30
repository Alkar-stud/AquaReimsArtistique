<?php
// php
namespace app\DTO;

use JsonSerializable;

class ReservationDetailItemDTO extends AbstractDTO implements JsonSerializable
{
    public function __construct(
        public int     $tarif_id,
        public ?string $tarif_access_code = null,
        public ?string $name = null,
        public ?string $firstname = null,
        public ?string $justificatif_name = null,
        public ?int    $place_number = null
    )
    {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            tarif_id: (int)($data['$tarif_id'] ?? 0),
            tarif_access_code: self::nullIfEmpty($data['tarif_access_code'] ?? null),
            name: self::nullIfEmpty($data['name'] ?? null),
            firstname: self::nullIfEmpty($data['firstname'] ?? null),
            justificatif_name: self::nullIfEmpty($data['justificatif_name'] ?? null),
            place_number: (int)($data['place_number'] ?? null),
        );
    }
}
