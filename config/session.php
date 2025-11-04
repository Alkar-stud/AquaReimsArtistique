<?php

// config/session.php
return [
    'production' => [
        'name' => '__Host-SID', // Préfixe de sécurité
        'cookie_lifetime' => 0, // Expire à la fermeture du navigateur
        'cookie_path' => '/',
        'cookie_domain' => '', // Vide pour le domaine actuel
        'cookie_secure' => true, // Uniquement via HTTPS
        'cookie_httponly' => true, // Inaccessible depuis JavaScript
        'cookie_samesite' => 'Strict' // Protection CSRF maximale
    ],
    'local' => [
        'name' => 'LOCAL_SID',
        'cookie_lifetime' => 0,
        'cookie_path' => '/',
        'cookie_domain' => '',
        'cookie_secure' => true, // false pour le développement en local (http)
        'cookie_httponly' => true,
        'cookie_samesite' => 'Lax' // Lax est plus permissif pour le dev
    ]
];