<?php
namespace app\Utils;

use app\Enums\LogType;
use app\Services\Logs\LogService;
use Random\RandomException;

class CsrfHelper
{
    /**
     * Génère un nouveau token CSRF et le stocke en session
     * @return string Le token généré
     * @throws RandomException
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
     * @throws RandomException
     */
    public static function getToken(): string
    {
        return $_SESSION['csrf_token'] ?? self::generateToken();
    }

    /**
     * Valide un token CSRF.
     * @param string $submittedToken Le token soumis par le client.
     * @param string|null $action L'action associée pour un logging plus précis.
     * @param bool $consume Si true, le token sera supprimé de la session après validation (usage unique).
     * @return bool True si le token est valide, false sinon.
     */
    public static function validateToken(string $submittedToken, ?string $action = null, bool $consume = true): bool
    {
        $sessionToken = $_SESSION['csrf_token'] ?? '';

        if (empty($submittedToken) || empty($sessionToken) || !hash_equals($sessionToken, $submittedToken)) {
            self::logInvalidCsrf("Token CSRF invalide sur l'action: " . ($action ?? 'unknown'), $submittedToken);
            return false;
        }

        if ($consume) {
            unset($_SESSION['csrf_token']);
        }

        return true;
    }

    private static function logInvalidCsrf(string $reason, string $submittedToken): void
    {
        // Initialisation à la demande pour éviter les dépendances inutiles lors de la génération de token.
        $logService = new LogService();
        $logService->log(LogType::ACCESS, 'Tentative CSRF invalide: ' . $reason, [
            'submitted_token_length' => strlen($submittedToken),
            'session_token_exists' => isset($_SESSION['csrf_token']),
            'ip' => $_SERVER['REMOTE_ADDR'] ?? '',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'referer' => $_SERVER['HTTP_REFERER'] ?? 'non fourni',
            'request_uri' => $_SERVER['REQUEST_URI'] ?? 'non fourni'
        ], 'DANGER');
    }
}