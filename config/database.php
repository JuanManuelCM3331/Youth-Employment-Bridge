<?php

use App\Core\Env;

Env::load(__DIR__ . '/../.env');

return [
    'host' => Env::get('YEB_DB_HOST', '127.0.0.1'),
    'port' => Env::get('YEB_DB_PORT', '3306'),
    'database' => Env::get('YEB_DB_DATABASE', 'yeb_portal'),
    'username' => Env::get('YEB_DB_USERNAME', 'root'),
    'password' => Env::get('YEB_DB_PASSWORD', ''),
];
