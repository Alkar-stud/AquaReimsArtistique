<?php
namespace app\Utils;

final class NsqlIdGenerator
{
    private function __construct() {}

    // ID applicatif stable, portable (32 hex chars)
    public static function new(): string
    {
        return bin2hex(random_bytes(16));
    }
}
