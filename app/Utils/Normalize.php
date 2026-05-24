<?php

namespace app\Utils;

use app\Enums\LogType;

class Normalize
{
    public static function normalizeChannel(?string $channel): string
    {
        $candidate = trim((string)$channel);
        if ($candidate === '') {
            return LogType::APPLICATION->value;
        }

        foreach (LogType::cases() as $case) {
            if (strcasecmp($candidate, $case->value) === 0) {
                return $case->value;
            }
        }

        $aliases = [
            'database'    => LogType::DATABASE->value,
            'sql'         => LogType::SQL_ERROR->value,
            'sql_error'   => LogType::SQL_ERROR->value,
            'sql-error'   => LogType::SQL_ERROR->value,
            'http'        => LogType::URL->value,
            'request'     => LogType::ACCESS->value,
            'app'         => LogType::APPLICATION->value,
            'application' => LogType::APPLICATION->value,
            'security'    => LogType::SECURITY->value,
            'url_error'   => LogType::URL_ERROR->value,
            'url-error'   => LogType::URL_ERROR->value,
        ];
        $lk = strtolower($candidate);
        if (isset($aliases[$lk])) {
            return $aliases[$lk];
        }

        return LogType::APPLICATION->value;
    }
}