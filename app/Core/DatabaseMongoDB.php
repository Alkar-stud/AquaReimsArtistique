<?php

namespace app\Core;

use MongoDB\Client;
use MongoDB\Database;
use RuntimeException;

class DatabaseMongoDB
{
    private static ?Client $client = null;
    private static ?Database $database = null;

    private function __construct() {}
    private function __clone() {}

    public static function getClient(): Client
    {
        if (self::$client === null) {
            $uri = $_ENV['MONGODB_URL'] ?? '';
            if (!$uri) {
                throw new RuntimeException('MONGODB_URL non défini.');
            }
            self::$client = new Client($uri);
        }
        return self::$client;
    }

    public static function getDatabase(): Database
    {
        if (self::$database === null) {
            $dbName = $_ENV['MONGODB_DB'] ?? '';
            if (!$dbName) {
                throw new RuntimeException('MONGODB_DB non défini.');
            }
            self::$database = self::getClient()->selectDatabase($dbName);
        }
        return self::$database;
    }
}