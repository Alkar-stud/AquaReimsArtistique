<?php

namespace app\Core;

use MongoDB\Client;
use MongoDB\Database;
use RuntimeException;
use MongoDB\Driver\Exception\Exception as MongoDBException;

class DatabaseMongoDB
{
    private static ?Client $client = null;
    private static ?Database $database = null;

    private function __construct() {}
    private function __clone() {}

    public static function getClient(): Client
    {
        $uri = $_ENV['MONGODB_URL'] ?? '';
        if (!$uri) {
            throw new RuntimeException('MONGODB_URL non défini.');
        }
        if (self::$client === null) {
            try {
                self::$client = new Client($uri);
                // Teste la connexion
                self::$client->listDatabases();
            } catch (MongoDBException $e) {
                throw new RuntimeException('Erreur de connexion à MongoDB : ' . $e->getMessage(), 0, $e);
            }
        }
        return self::$client;
    }

    public static function getDatabase(): Database
    {
        $dbName = $_ENV['MONGODB_DB'] ?? '';
        if (!$dbName) {
            throw new RuntimeException('MONGODB_DB non défini.');
        }
        if (self::$database === null) {
            self::$database = self::getClient()->selectDatabase($dbName);
        }
        return self::$database;
    }
}