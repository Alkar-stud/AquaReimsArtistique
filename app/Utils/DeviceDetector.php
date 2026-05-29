<?php

namespace app\Utils;

class DeviceDetector
{
    /**
     * Détecte si le device est un mobile ou une tablette basé sur l'User-Agent
     *
     * @return bool True si mobile/tablette, false si desktop
     */
    public static function isMobileOrTablet(): bool
    {
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';

        $mobilePatterns = [
            '/android/i',
            '/webos/i',
            '/iphone/i',
            '/ipad/i',
            '/ipod/i',
            '/blackberry/i',
            '/windows phone/i',
            '/opera mini/i',
            '/mobile/i',
            '/tablet/i',
            '/kindle/i',
            '/playbook/i',
        ];

        foreach ($mobilePatterns as $pattern) {
            if (preg_match($pattern, $userAgent)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Détecte si c'est un appareil Apple (iPhone/iPad/Mac)
     *
     * @return bool
     */
    public static function isAppleDevice(): bool
    {
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        return preg_match('/(iphone|ipad|ipod|mac)/i', $userAgent) === 1;
    }

    /**
     * Détecte si c'est un appareil Android
     *
     * @return bool
     */
    public static function isAndroidDevice(): bool
    {
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        return preg_match('/android/i', $userAgent) === 1;
    }
}

