<?php

namespace app\DTO;

use JsonSerializable;

readonly class ReservationUserDTO implements JsonSerializable
{
    public function __construct(
        public string $nom,
        public string $prenom,
        public string $email,
        public ?string $telephone = null
    ) {
    }

    public function jsonSerialize(): array
    {
        return get_object_vars($this);
    }
}