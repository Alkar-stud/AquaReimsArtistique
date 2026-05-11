<?php

use app\Services\Log\Handler\DbLogHandler;
use app\Services\Log\Handler\FileLogHandler;


return [
    'handlers' => [
        // Écriture fichier (existant)
        ['class' => FileLogHandler::class, 'args' => [__DIR__ . '/../storage/log', 10_000_000, 7]],
        // Handler DB à activer après implémentation (optionnel)
        ['class' => DbLogHandler::class, 'args' => []],
    ],

    // Niveau minimal global pour persister en base (valeurs: DEBUG, INFO, NOTICE, WARNING, ERROR, CRITICAL, ALERT, EMERGENCY)
    'global_db_min_level' => 'WARNING',

    // Overrides par channel — si défini, ce niveau remplace le global pour ce channel
    'channel_min_levels' => [
        'application' => 'INFO',
        'reservation' => 'WARNING',
        'database'    => 'ERROR',
        'security'    => 'WARNING',
        'mail'        => 'ERROR',
        'access'      => 'INFO',
        'url'         => 'WARNING',
    ],
];
