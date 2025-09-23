<?php

use app\Services\Log\Handler\FileLogHandler;
use app\Services\Log\Handler\MongoLogHandler;
use app\Services\Log\Handler\SleekDbLogHandler;

return [
    'handlers' => [
        ['class' => FileLogHandler::class, 'args' => [__DIR__ . '/../storage/log', 10_000_000, 7]],
        // MongoDB (optionnel) -> require "mongodb/mongodb"
        // Le handler lira $_ENV tout seul (MONGODB_URL, MONGODB_DB, LOG_MONGODB_DB, LOG_MONGODB_COLLECTION)
         ['class' => MongoLogHandler::class, 'args' => []],

        // SleekDB (optionnel) -> require "rakibtg/sleekdb"
        // ['class' => SleekDbLogHandler::class, 'args' => [__DIR__ . '/../var/sleekdb', 'log', []]],
    ],
];
