<?php

namespace app\DTO;

use JsonSerializable;

readonly class ReservationUserDTO implements JsonSerializable
{
    public function __construct(
        public string $name,
        public string $firstname,
        public string $email,
        public ?string $phone = null
    ) {
    }

    public function jsonSerialize(): array
    {
        return get_object_vars($this);
    }
}