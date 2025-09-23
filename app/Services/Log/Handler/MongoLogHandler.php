<?php
namespace app\Services\Log\Handler;

use Throwable;

final class MongoLogHandler implements LogHandlerInterface
{
    private $collection = null;

    public function __construct(
        ?string $dsn = null,
        ?string $database = null,
        ?string $collection = null,
        array $options = []
    ) {
        // Résolution via $_ENV (une seule source de vérité)
        $dsn = $dsn
            ?? ($_ENV['MONGODB_URL']
                ?? ('mongodb://' . ($_ENV['MONGODB_HOST'] ?? '127.0.0.1') . ':' . ($_ENV['MONGODB_PORT'] ?? '27017')));

        // DB des logs: priorise LOG_MONGODB_DB sinon réutilise MONGODB_DB
        $database = $database
            ?? ($_ENV['LOG_MONGODB_DB'] ?? $_ENV['MONGODB_DB'] ?? 'app_logs');

        // Collection des logs: LOG_MONGODB_COLLECTION sinon 'logs'
        $collection = $collection
            ?? ($_ENV['LOG_MONGODB_COLLECTION'] ?? 'logs');

        if (class_exists(\MongoDB\Client::class)) {
            try {
                $client = new \MongoDB\Client($dsn, $options);
                $this->collection = $client->selectCollection($database, $collection);
            } catch (Throwable) {
                // silencieux
            }
        }
    }

    public function handle(array $record): void
    {
        if (!$this->collection) {
            return; // silencieux si l'extension n'est pas dispo
        }
        try {
            $this->collection->insertOne($record);
        } catch (Throwable) {
            // ne pas casser l'app
        }
    }
}
