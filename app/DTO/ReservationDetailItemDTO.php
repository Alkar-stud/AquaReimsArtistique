<?php
namespace app\DTO;

use JsonSerializable;

final class ReservationDetailItemDTO extends AbstractDTO implements JsonSerializable
{
    public function __construct(
        public int $tarif_id,
        public ?string $name = null,
        public ?string $firstname = null,
        public ?string $justificatif_name = null,
        public ?string $justificatif_original_name = null,
        public ?string $tarif_access_code = null,
        public ?int $place_id = null
    ) {
    }

    /**
     * Construit un DTO avec en option un code un tarif spécial (seuls tarif_id et tarif_access_code sont renseignés ici)
     *
     * @param int $tarif_id
     * @param array $data
     * @param string|null $code
     * @return self
     */
    public static function fromArrayWithSpecialPrice(int $tarif_id, array $data = [], ?string $code = null): self
    {
        return new self(
            tarif_id: ($tarif_id),
            name: (string)($data['name'] ?? null),
            firstname: (string)($data['firstname'] ?? null),
            justificatif_name: (string)($data['justificatif_name'] ?? null),
            justificatif_original_name: (string)($data['justificatif_name'] ?? null),
            tarif_access_code: $code,
            place_id: (isset($data['place_id'])) ? (int)$data['place_id'] : null,
        );
    }

    public static function fromArray(array $data = []): self
    {
        return new self(
            tarif_id: isset($data['tarif_id']) ? (int)$data['tarif_id'] : 0,
            name: ReservationDetailItemDTO::nullIfEmpty($data['name'] ?? null),
            firstname: ReservationDetailItemDTO::nullIfEmpty($data['firstname'] ?? null),
            justificatif_name: ReservationDetailItemDTO::nullIfEmpty($data['justificatif_name'] ?? null),
            justificatif_original_name: ReservationDetailItemDTO::nullIfEmpty($data['justificatif_original_name'] ?? null),
            tarif_access_code: isset($data['tarif_access_code']) ? ReservationDetailItemDTO::nullIfEmpty((string)$data['tarif_access_code']) : null,
            place_id: isset($data['place_id']) ? (int)$data['place_id'] : null,
        );
    }

}
