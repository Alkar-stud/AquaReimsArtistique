<?php

namespace app\Utils;

use RuntimeException;

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
        if (!isset($_SERVER['HTTP_HOST']) && !defined('HOST_SITE')) {
            throw new RuntimeException('Unable to determine host: HTTP_HOST header and HOST_SITE constant are not defined');
        }

        $host = $_SERVER['HTTP_HOST'] ?? HOST_SITE;
        $serverPort = $_SERVER['SERVER_PORT'] ?? 80;
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $serverPort == 443) ? "https://" : "http://";

        return $protocol . $host . $uri;
    }


}