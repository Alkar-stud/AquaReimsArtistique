<?php
namespace app\Services\Security;

use app\Services\Log\Logger;
use app\Services\Security\TokenGenerateService;
use RuntimeException;

final class CsrfService
{
    private const string SESSION_KEY = '_csrf';
    private TokenGenerateService $tokenGenerate;

    public function __construct()
    {
        $this->tokenGenerate = new TokenGenerateService();
    }

    private function ensureSessionStarted(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    public function getToken(string $context = 'default'): string
    {
        $this->ensureSessionStarted();
        $_SESSION[self::SESSION_KEY] ??= [];
        if (empty($_SESSION[self::SESSION_KEY][$context])) {
            $tokenData = $this->tokenGenerate->generateToken(32);

            if ($tokenData === null) {
                Logger::get()->error('security', 'Failed to generate CSRF token due to lack of secure randomness source.', [
                    'context' => $context,
                ]);
                // Dans ce cas critique, il est plus sûr de faire échouer la requête.
                throw new RuntimeException('Could not generate a secure CSRF token.');
            }

            // Pour le CSRF, nous n'avons besoin que du token, pas de la date d'expiration.
            $_SESSION[self::SESSION_KEY][$context] = $tokenData['token'];
        }
        return $_SESSION[self::SESSION_KEY][$context];
    }

    public function validateAndConsume(?string $submittedToken, string $context = 'default'): bool
    {
        // Ouvrir la session avant tout output
        $this->ensureSessionStarted();

        $stored = $_SESSION[self::SESSION_KEY][$context] ?? '';
        $submitted = (string)($submittedToken ?? '');

        $ok = ($submitted !== '') && ($stored !== '') && hash_equals($stored, $submitted);

        // Consommer le token (usage unique)
        unset($_SESSION[self::SESSION_KEY][$context]);

        if (!$ok) {
            Logger::get()->security('csrf_fail', [
                'reason'  => 'token_mismatch_or_missing',
                'context' => $context,
            ]);
        }

        return $ok;
    }

    public function invalidate(string $context = 'default'): void
    {
        $this->ensureSessionStarted();
        unset($_SESSION[self::SESSION_KEY][$context]);
    }
}
