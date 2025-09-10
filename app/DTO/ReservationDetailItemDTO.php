<?php

namespace app\DTO;

use JsonSerializable;

class ReservationDetailItemDTO implements JsonSerializable
{
    public function __construct(
        public int $tarif_id,
        public ?string $nom = null,
        public ?string $prenom = null,
        public ?string $access_code = null,
        public ?string $justificatif_name = null,
        public ?int $seat_id = null,
        public ?string $seat_name = null
    ) {
    }

    public function jsonSerialize(): array
    {
        // Ne sérialise que les propriétés non nulles pour garder la session propre
        return array_filter(get_object_vars($this), fn ($value) => $value !== null);
    }

}