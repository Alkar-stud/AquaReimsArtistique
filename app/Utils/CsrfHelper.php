<?php
namespace app\Utils;

use app\Services\LogService;
use app\Enums\LogType;

class CsrfHelper
{
    private static LogService $logService;

    /**
     * Génère un nouveau token CSRF et le stocke en session
     * @return string Le token généré
     */
    public static function generateToken(): string
    {
        $token = bin2hex(random_bytes(32));
        $_SESSION['csrf_token'] = $token;
        return $token;
    }

    /**
     * Récupère le token CSRF existant ou en génère un nouveau
     * @return string Le token CSRF
     */
    public static function getToken(): string
    {
        return $_SESSION['csrf_token'] ?? self::generateToken();
    }

    public static function validateToken(string $token, bool $logFailure = true): bool
    {
        if (!isset($_SESSION['csrf_token'])) {
            if ($logFailure) {
                self::logInvalidCsrf('Token CSRF manquant en session', $token);
            }
            return false;
        }

        $isValid = hash_equals($_SESSION['csrf_token'], $token);

        if (!$isValid && $logFailure) {
            self::logInvalidCsrf('Token CSRF ne correspond pas', $token);
        }

        // Supprimer le token après validation (one-time use)
        unset($_SESSION['csrf_token']);

        return $isValid;
    }

    private static function logInvalidCsrf(string $reason, string $submittedToken): void
    {
        $logService = new LogService();
        $logService->log(LogType::ACCESS, 'Tentative CSRF invalide: ' . $reason, [
            'submitted_token_length' => strlen($submittedToken),
            'session_token_exists' => isset($_SESSION['csrf_token']),
            'ip' => $_SERVER['REMOTE_ADDR'] ?? '',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'referer' => $_SERVER['HTTP_REFERER'] ?? '',
            'request_uri' => $_SERVER['REQUEST_URI'] ?? ''
        ], 'DANGER');
    }
}