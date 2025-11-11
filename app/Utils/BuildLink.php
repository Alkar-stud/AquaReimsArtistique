<?php

namespace app\Utils;

class BuildLink
{
    /**
     * Construit un lien à l'aide d'une URL et d'un token (optionnel)
     * @param string $url
     * @param string $token
     * @return string
     */
    public static function buildResetLink(string $url, string $token): string
    {
        $baseLink = self::buildBasicLink();
        return $baseLink . $url . '?token=' . rawurlencode($token);
    }

    public static function buildBasicLink(string $uri = ''): string
    {
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        return $protocol . $host . $uri;
    }


}