<?php

declare(strict_types=1);

namespace App\Database;

use Predis\Client;

/**
 * Singleton-подключение к Redis через Predis.
 *
 * Обеспечивает единственный экземпляр Predis\Client на запрос.
 * Используется как фабрика; в production-коде предпочтительнее SafeRedis.
 *
 * @package App\Database
 */
class RedisConnection
{
    /** @var Client|null Единственный экземпляр клиента */
    private static ?Client $instance = null;

    /**
     * Возвращает единственный Predis\Client (Singleton).
     *
     * @param  array{host:string,port:int} $config Параметры подключения
     * @return Client                               Клиент Predis
     */
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
