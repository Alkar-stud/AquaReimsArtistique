<?php
namespace app\DTO;

use JsonSerializable;

class ReservationUserDTO extends AbstractDTO implements JsonSerializable
{
    public function __construct(
        public string $name,
        public string $firstname,
        public string $email,
        public ?string $phone = null
    ) {
        $this->name = $name !== null ? mb_strtoupper($name, 'UTF-8') : null;
        $this->firstname = $firstname !== null ? mb_convert_case($firstname, MB_CASE_TITLE, 'UTF-8') : null;
    }

    public static function fromArray(array $data): self
    {
        return new self(
            name: (string)($data['booker']['name'] ?? null),
            firstname: (string)($data['booker']['firstname'] ?? null),
            email: (string)($data['booker']['email'] ?? null),
            phone: self::nullIfEmpty($data['booker']['phone'] ?? null),
        );
    }

}