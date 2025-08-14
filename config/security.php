<?php

//Liste des données sensibles à surveiller
return [
    'sensitive_urls' => [
        '/gestion',
        '/account',
        '/install',
    ],
    'critical_tables' => [
        'user',
        'roles',
        'config'
    ],
    'sensitive_data_keys' => [
        'password',
        'token',
        'secret',
        'session_id',
        'confirm_password',
        'current_password',
        'new_password'
    ]
];