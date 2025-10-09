<?php
namespace app\Core;

use RuntimeException;
use SleekDB\Store;
use Throwable;

/**
 * Singleton pour centraliser l'accès aux Stores SleekDB
 */
class DatabaseSleekDB
{
    /** @var array<string, object> cache des stores */
    private static array $stores = [];

    private function __construct() {}
    private function __clone() {}

    /**
     * Retourne une instance de Store SleekDB pour le nom de collection donné.
     *
     * @param string $name
     * @return object Instance de \SleekDB\Store
     */
    public static function getStore(string $name): object
    {
        if (isset(self::$stores[$name])) {
            return self::$stores[$name];
        }

        $basePath = __DIR__ . '/../..' . $_ENV['SLEEKDB_PATH'] ?? sys_get_temp_dir() . '/storage/sleekdb';
        if (!is_dir($basePath)) {
            if (!mkdir($basePath, 0755, true) && !is_dir($basePath)) {
                throw new RuntimeException('Impossible de créer le dossier SleekDB : ' . $basePath);
            }
        }

        if (!class_exists(Store::class)) {
            throw new RuntimeException('La librairie SleekDB n\'est pas installée. Executer `composer require sleekdb/sleekdb`');
        }

        try {
            // API commune : new \SleekDB\Store($storeName, $storePath)
            $configuration = [
                'timeout' => false // timeout "Deprecated"
            ];
            $store = new Store($name, $basePath, $configuration);
        } catch (Throwable $e) {
            // Si l'API diffère (ex: v2), remonter l'erreur pour ajuster
            throw new RuntimeException('Impossible d\'instancier SleekDB Store: ' . $e->getMessage(), 0, $e);
        }

        self::$stores[$name] = $store;
        return $store;
    }
}
