<?php

use App\Core\Env;

return [
    'host' => Env::get('DB_HOST', 'localhost'),
    'database' => Env::get('DB_DATABASE', 'yve_crm'),
    'username' => Env::get('DB_USERNAME', 'root'),
    'password' => Env::get('DB_PASSWORD', ''),
    'charset' => Env::get('DB_CHARSET', 'utf8mb4'),
    'collation' => Env::get('DB_COLLATION', 'utf8mb4_unicode_ci'),
    'options' => [
        \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
        \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
        \PDO::ATTR_EMULATE_PREPARES => false,
    ]
];
