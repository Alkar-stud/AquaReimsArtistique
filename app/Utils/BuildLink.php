<?php

namespace app\Utils;

class BuildLink
{
    /**
     * Construit un lien à l'aide d'une URL et d'un token
     * @param string $url
     * @param string $token
     * @return string
     */
    public static function buildResetLink(string $url, string $token): string
    {
        $scheme = 'https://';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        return $scheme . $host . $url . '?token=' . rawurlencode($token);
    }


}