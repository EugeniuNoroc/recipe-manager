<?php

declare(strict_types=1);

namespace App\Database;

/**
 * No-op заглушка Redis (паттерн Null Object).
 *
 * Используется когда Redis недоступен. Все команды возвращают безопасные
 * значения по умолчанию, позволяя приложению работать в деградированном режиме.
 * Сервисы, использующие NullRedisClient, остаются функциональными но без кэширования.
 *
 * @package App\Database
 */
class NullRedisClient
{
    /**
     * Перехватывает любую Redis-команду и возвращает безопасное значение.
     *
     * Возвращаемые значения соответствуют типам реальных Redis-ответов,
     * чтобы вызывающий код не требовал специальной обработки null-клиента.
     *
     * @param  string $method Имя Redis-команды
     * @param  array  $args   Аргументы команды
     * @return mixed          [] для списков, 0 для счётчиков, null для скалярных
     */
    public function __call(string $method, array $args): mixed
    {
        return match (strtolower($method)) {
            'smembers', 'keys'  => [],
            // mget must return an array the same length as the input key list
            'mget'              => array_fill(0, count($args[0] ?? []), null),
            'scard', 'incr', 'decr', 'del',
            'sadd', 'srem', 'expire', 'ttl',
            'sismember', 'hexists', 'exists', 'ping' => 0,
            default             => null,
        };
    }
}
