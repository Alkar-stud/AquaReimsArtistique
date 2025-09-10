<?php

namespace app\DTO;

use JsonSerializable;

readonly class ReservationAccessCodeDTO implements JsonSerializable
{
    public function __construct(
        public int    $eventId,
        public string $code
    ) {
    }

    public function jsonSerialize(): array
    {
        return get_object_vars($this);
    }
}