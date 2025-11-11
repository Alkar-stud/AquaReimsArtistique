<?php
namespace app\Services\Security;

use app\Services\Log\Logger;
use RuntimeException;

final class CsrfService
{
    private const string SESSION_KEY = '_csrf';
    private TokenGenerateService $tokenGenerate;

    public function __construct()
    {
        $this->tokenGenerate = new TokenGenerateService();
    }

    /**
     * Pour s'assurer qu'une session PHP est déjà active
     *
     * @return void
     */
    private function ensureSessionStarted(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    /**
     * Pour obtenir un token CSRF, soit un nouveau, soit l'existant et le stocke dans $_SESSION
     *
     * @param string $context
     * @return string
     */
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
            $_SESSION[self::SESSION_KEY][$context . '_time'] = (new \DateTime())->format('Y-m-d H:i:s.v');
        }
        return $_SESSION[self::SESSION_KEY][$context];
    }

    /**
     * Valide et consomme le token CSRF, retourne false si la validation échoue, true si c'est bon
     *
     * @param string|null $submittedToken
     * @param string $context
     * @return bool
     */
    public function validateAndConsume(?string $submittedToken, string $context = 'default'): bool
    {
        // Ouvrir la session avant tout output
        $this->ensureSessionStarted();

        //On récupère le token de $_SESSION
        $stored = $_SESSION[self::SESSION_KEY][$context] ?? '';

        //On compare le token en session et le token reçu
        $ok = ($submittedToken !== '') && ($stored !== '') && hash_equals($stored, $submittedToken);

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

}
