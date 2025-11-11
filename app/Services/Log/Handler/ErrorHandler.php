<?php

namespace app\Services\Log\Handler;

use app\Enums\LogType;
use app\Services\Log\Logger;
use app\Services\Log\LoggingBootstrap;
use app\Services\Log\RequestContext;
use Throwable;

final class ErrorHandler
{
    private bool $debug;
    private static ?self $instance = null;

    private function __construct(bool $debug)
    {
        $this->debug = $debug;
    }

    public static function register(bool $debug): void
    {
        if (self::$instance !== null) {
            return;
        }
        self::$instance = new self($debug);

        set_exception_handler([self::$instance, 'handleException']);
        set_error_handler([self::$instance, 'handleError']);
        register_shutdown_function([self::$instance, 'handleShutdown']);
    }

    public function handleException(Throwable $e): void
    {
        LoggingBootstrap::ensureInitialized();

        Logger::get()->error(
            LogType::APPLICATION->value,
            'uncaught_exception',
            ['exception' => $e]
        );

        $this->respond500($e);
    }

    public function handleError(int $errno, string $errstr, string $errfile, int $errline): bool
    {
        LoggingBootstrap::ensureInitialized();

        $level = match ($errno) {
            E_USER_ERROR, E_RECOVERABLE_ERROR => 'ERROR',
            E_WARNING, E_USER_WARNING => 'WARNING',
            E_NOTICE, E_USER_NOTICE, E_DEPRECATED, E_USER_DEPRECATED => 'NOTICE',
            default => 'INFO',
        };

        Logger::get()->log(
            $level,
            LogType::APPLICATION->value,
            'php_error',
            ['errno' => $errno, 'message' => $errstr, 'file' => $errfile . ':' . $errline]
        );

        // Laisser le handler natif poursuivre
        return false;
    }

    public function handleShutdown(): void
    {
        $err = error_get_last();
        if (!$err) {
            return;
        }

        $fatalTypes = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR];
        if (in_array($err['type'] ?? 0, $fatalTypes, true)) {
            LoggingBootstrap::ensureInitialized();

            Logger::get()->critical(
                LogType::APPLICATION->value,
                'fatal_error',
                [
                    'type' => $err['type'],
                    'message' => $err['message'],
                    'file' => $err['file'] . ':' . $err['line'],
                ]
            );

            if (!headers_sent()) {
                $this->respond500('Fatal error');
            }
        }
    }

    private function respond500(null|Throwable|string $e): void
    {
        http_response_code(500);

        if ($this->wantsJson()) {
            header('Content-Type: application/json');
            echo json_encode([
                'error' => 'Internal Server Error',
                'request_id' => RequestContext::getRequestId(),
                'details' => $this->debug
                    ? (string)($e instanceof Throwable ? $e : ($e ?? ''))
                    : null,
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        } else {
            $msg = $this->debug
                ? '<pre class="alert alert-danger" style="white-space:pre-wrap;">'
                . htmlspecialchars((string)($e instanceof Throwable ? $e : ($e ?? '')), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
                . '</pre>'
                : 'Une erreur interne est survenue.';
            header('Content-Type: text/html; charset=UTF-8');
            echo $msg;
        }
        exit;
    }

    private function wantsJson(): bool
    {
        $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
        return str_contains($accept, 'application/json')
            || (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest');
    }
}