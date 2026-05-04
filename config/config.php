<?php

declare(strict_types=1);

use Dotenv\Dotenv;

$dotenv = Dotenv::createImmutable(dirname(__DIR__));
$dotenv->load();

$dotenv->required([
    'MYSQL_HOST', 'MYSQL_PORT', 'MYSQL_DATABASE',
    'MYSQL_USER', 'MYSQL_PASSWORD',
    'REDIS_HOST', 'REDIS_PORT',
]);

return [
    'mysql' => [
        'host'     => $_ENV['MYSQL_HOST'],
        'port'     => (int) $_ENV['MYSQL_PORT'],
        'database' => $_ENV['MYSQL_DATABASE'],
        'user'     => $_ENV['MYSQL_USER'],
        'password' => $_ENV['MYSQL_PASSWORD'],
        'charset'  => 'utf8mb4',
    ],
    'redis' => [
        'host' => $_ENV['REDIS_HOST'],
        'port' => (int) $_ENV['REDIS_PORT'],
    ],
    'app' => [
        'env'           => $_ENV['APP_ENV'] ?? 'dev',
        'url'           => $_ENV['APP_URL'] ?? '',
        'cookie_secure' => filter_var($_ENV['COOKIE_SECURE'] ?? 'false', FILTER_VALIDATE_BOOLEAN),
    ],
];
