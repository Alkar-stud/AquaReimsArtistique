<?php
namespace app\Utils;

use Random\RandomException;

final class NsqlIdGenerator
{
    private function __construct() {}

    // ID applicatif stable, portable (32 hex chars)
    public static function new(): string
    {
        try {
            return bin2hex(random_bytes(16));
        } catch (RandomException $e) {

        }
    }
}
