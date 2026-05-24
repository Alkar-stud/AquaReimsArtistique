<?php

namespace app\Utils;

use Throwable;

class Sanitize
{
    public static function sanitizeLog(array $context, array $maskedKeys): array
    {
        $out = [];
        foreach ($context as $k => $v) {
            $lk = strtolower((string)$k);
            if (in_array($lk, $maskedKeys, true)) {
                $out[$k] = '[MASKED]';
                continue;
            }
            if (is_string($v)) {
                $out[$k] = mb_strlen($v) > 512 ? (mb_substr($v, 0, 512) . '...') : $v;
            } elseif ($v instanceof Throwable) {
                $out[$k] = ['ex' => get_class($v), 'msg' => $v->getMessage(), 'file' => $v->getFile() . ':' . $v->getLine()];
            } else {
                $out[$k] = $v;
            }
        }
        return $out;
    }

    /**
     * Remplacer les valeurs sensibles d'une requête par des placeholders
     * @param string $sql
     * @return string
     */
    public static function sanitizeSql(string $sql): string
    {
        // Remplacer les valeurs sensibles par des placeholders
        return preg_replace('/\s+/', ' ', trim($sql));
    }

    /**
     * Masquer les mots de passe et données sensibles
     * @param array $params
     * @return array
     */
    public static function sanitizeParams(array $params): array
    {
        // Masquer les mots de passe et données sensibles
        $sanitized = [];
        foreach ($params as $key => $value) {
            if (in_array(strtolower($key), ['password', 'token', 'secret'])) {
                $sanitized[$key] = '[MASKED]';
            } else {
                $sanitized[$key] = is_string($value) && strlen($value) > 100 ?
                    substr($value, 0, 100) . '...' : $value;
            }
        }
        return $sanitized;
    }

}