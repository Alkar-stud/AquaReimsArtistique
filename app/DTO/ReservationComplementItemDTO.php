<?php
namespace app\DTO;

use JsonSerializable;

class ReservationComplementItemDTO extends AbstractDTO  implements JsonSerializable
{
    public function __construct(
        public int $tarif_id,
        public int $qty,
        public ?string $tarif_access_code = null,
    ) {}

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
            qty: isset($data['qty']) ? (int)$data['qty'] : 1,
            tarif_access_code: $code,
        );
    }

    public static function fromArray(array $data): self
    {
        return new self(
            tarif_id: isset($data['tarif_id']) ? (int)$data['tarif_id'] : 0,
            qty: isset($data['qty']) ? (int)$data['qty'] : 0,
            tarif_access_code: isset($data['tarif_access_code']) ? ReservationComplementItemDTO::nullIfEmpty((string)$data['tarif_access_code']) : null,
        );
    }

}
