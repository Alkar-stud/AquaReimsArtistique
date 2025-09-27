<?php
namespace app\Services\Log;

use app\Services\Log\Handler\LogHandlerInterface;

final class LoggingBootstrap
{
    private static bool $initialized = false;

    public static function ensureInitialized(): void
    {
        if (self::$initialized) {
            return;
        }

        $handlers = [];

        // Construire les handlers depuis la config
        $configFile = __DIR__ . '/../../../config/logging.php';
        if (is_file($configFile)) {
            $config = require $configFile;

            foreach (($config['handlers'] ?? []) as $def) {
                $class = $def['class'] ?? null;
                $args = $def['args'] ?? [];

                if (!is_string($class) || !class_exists($class)) {
                    continue;
                }

                try {
                    $ref = new \ReflectionClass($class);
                    $instance = $ref->newInstanceArgs(is_array($args) ? $args : []);
                    if ($instance instanceof LogHandlerInterface) {
                        $handlers[] = $instance;
                    }
                } catch (\Throwable) {
                    // Ignorer les erreurs d’instanciation d’un handler
                }
            }
        }

        // Si aucun handler n’est construit, laisser le fallback de Logger::get()
        if ($handlers) {
            $security = require __DIR__ . '/../../../config/security.php';
            $masked = $security['sensitive_data_keys'] ?? [];
            Logger::init($handlers, $masked);
        }

        self::$initialized = true;
    }
}
