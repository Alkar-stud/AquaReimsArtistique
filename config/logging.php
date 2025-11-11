<?php

use app\Services\Log\Handler\FileLogHandler;

return [
    'handlers' => [
        ['class' => FileLogHandler::class, 'args' => [__DIR__ . '/../storage/log', 10_000_000, 7]],
    ],
];
