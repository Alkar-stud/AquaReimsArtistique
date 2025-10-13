<?php
namespace app\Services\Security;

use app\Services\Log\Logger;
use app\Utils\DurationHelper;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Exception;

class TokenGenerateService
{
    /**
     * Génère un token avec une date d'expiration optionnelle (expirationInterval OU expiresAt OU expiresAtStr)
     *
     * @param int $length Longueur en octets (le token hex sera x2).
     * @param string|null $expirationInterval Ex : 'PT1H'. Null = pas d'expiration.
     * @param DateTimeInterface|null $expiresAt
     * @param string|null $expiresAtStr
     * @return array|null ['token' => string, 'expires_at' => int|null]
     */
    public function generateToken(int $length, ?string $expirationInterval = null, ?DateTimeInterface $expiresAt = null, ?string $expiresAtStr = null): ?array
    {
        //
        $token = $this->generate($length);
        if ($token === null) {
            return null;
        }

        //On calcule en fonction de ce qu'on a reçu
        if ($expirationInterval !== null) {
            $seconds = DurationHelper::Iso8601ToSeconds($expirationInterval);
            if ($seconds === null) {
                Logger::get()->error('security', 'Invalid date interval provided for token generation.', [
                    'interval' => $expirationInterval,
                ]);
                return null;
            }
            $expiresAt = time() + $seconds;
            try {
                // Utilise le fuseau par défaut (ou fallback Europe/Paris)
                $tz = new DateTimeZone(\date_default_timezone_get() ?: 'Europe/Paris');
                $expiresAtStr = (new DateTimeImmutable('now', $tz))
                    ->setTimestamp($expiresAt)
                    ->format('Y-m-d H:i:s');
            } catch (Exception $e) {
                Logger::get()->error('security', 'Failed to build DateTimeImmutable for token expiration.', [
                    'expires_at' => $expiresAt,
                    'exception' => $e->getMessage(),
                ]);
                return null;
            }
        } elseif ($expiresAt !== null) {
            // On a reçu un objet DateTime, on le convertit.
            $expiresAtStr = $expiresAt->format('Y-m-d H:i:s');
        } elseif ($expiresAtStr !== null) {
            // On a reçu une chaîne de caractères, on la convertit en objet DateTime
            try {
                $tz = new DateTimeZone(\date_default_timezone_get() ?: 'Europe/Paris');
                $dt = new DateTimeImmutable($expiresAtStr, $tz);
                $expiresAt = $dt->getTimestamp();
            } catch (Exception) {
                $expiresAt = null; // La chaîne était invalide
            }
        }

        return [
            'token' => $token,
            'expires_at' => $expiresAt,
            'expires_at_str' => $expiresAtStr,
        ];
    }

    /**
     * Génère un token d'une longueur donnée
     *
     * @param int $length
     * @return string|null
     */
    private function generate(int $length): ?string
    {
        try {
            return bin2hex(random_bytes($length));
        } catch (Exception $e) {
            Logger::get()->error('security', 'Failed to generate secure random bytes for token.', [
                'exception' => $e->getMessage(),
                'length' => $length
            ]);
            return null;
        }
    }
}
