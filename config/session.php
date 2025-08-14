<?php

return [
    'production' => [
        'cookie_secure' => true,     // HTTPS uniquement
        'cookie_httponly' => true,   // Pas d'accès JavaScript
        'cookie_samesite' => 'Strict',
        'use_strict_mode' => true,
        'session_name' => 'AQUA_SESSION'
    ],
    'local' => [
        'cookie_secure' => false,    // HTTP autorisé en dev
        'cookie_httponly' => true,
        'cookie_samesite' => 'Strict',
        'use_strict_mode' => true,
        'session_name' => 'AQUA_SESSION_DEV'
    ]
];