<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/env.php';

load_env(dirname(__DIR__) . '/.env');

return [
    'host' => env('DB_HOST', '127.0.0.1'),
    // 3306 is the standard MySQL port — set DB_PORT explicitly if your local
    // dev setup (e.g. MAMP) uses a different one.
    'port' => (int) env('DB_PORT', '3306'),
    'database' => env('DB_DATABASE', 'east_ren_metrics'),
    'username' => env('DB_USERNAME', 'root'),
    'password' => env('DB_PASSWORD', 'root'),
    'charset' => env('DB_CHARSET', 'utf8mb4'),
];
