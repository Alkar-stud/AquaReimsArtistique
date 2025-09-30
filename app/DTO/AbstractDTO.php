<?php

namespace app\DTO;

class AbstractDTO
{

    public function jsonSerialize(): array
    {
        return get_object_vars($this);
    }

    protected static function nullIfEmpty(?string $v): ?string
    {
        $v = isset($v) ? trim($v) : null;
        return $v === '' ? null : $v;
    }

}