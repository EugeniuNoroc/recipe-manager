<?php

declare(strict_types=1);

namespace App\Database;

use Predis\Client;

class RedisConnection
{
    private static ?Client $instance = null;

    public static function getInstance(array $config): Client
    {
        if (self::$instance === null) {
            self::$instance = new Client([
                'scheme' => 'tcp',
                'host'   => $config['host'],
                'port'   => $config['port'],
            ]);
        }

        return self::$instance;
    }

    private function __construct() {}
}
