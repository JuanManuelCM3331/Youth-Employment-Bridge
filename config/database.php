<?php

$envFile = __DIR__ . '/../.env';
if (is_file($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) continue;
        [$key, $value] = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value);
        if (($value[0] ?? '') === '"' && str_ends_with($value, '"')) $value = substr($value, 1, -1);
        if (getenv($key) === false) putenv($key . '=' . $value);
    }
}

return [
    'host' => getenv('YEB_DB_HOST') ?: '127.0.0.1',
    'port' => getenv('YEB_DB_PORT') ?: '3306',
    'database' => getenv('YEB_DB_DATABASE') ?: 'yeb_portal',
    'username' => getenv('YEB_DB_USERNAME') ?: 'root',
    'password' => getenv('YEB_DB_PASSWORD') ?: '',
];