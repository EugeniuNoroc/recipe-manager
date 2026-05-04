<?php

declare(strict_types=1);

namespace App\Database;

use App\Services\ChaosFlags;
use Predis\Client;
use Predis\Connection\ConnectionException;
use Predis\CommunicationException;

/**
 * Прокси вокруг Predis\Client с graceful degradation (паттерн Proxy).
 *
 * Перехватывает исключения соединения/коммуникации при каждом вызове
 * и прозрачно деградирует до поведения NullRedisClient.
 * Отмечает себя недоступным после первого сбоя, чтобы вызывающий код мог реагировать.
 * Также деградирует при симуляции сбоя через ChaosFlags.
 *
 * @package App\Database
 */
class SafeRedis
{
    /** @var bool Флаг доступности: false после первого сбоя соединения */
    private bool $alive = true;

    /** @var NullRedisClient No-op клиент для деградированного режима */
    private NullRedisClient $null;

    /**
     * @param Client $client Реальный Predis-клиент
     */
    public function __construct(private Client $client)
    {
        $this->null = new NullRedisClient();
    }

    /**
     * Возвращает true если Redis соединение живо и ChaosFlags не имитирует сбой.
     *
     * @return bool
     */
    public function isAvailable(): bool
    {
        return $this->alive && !ChaosFlags::isRedisDisabled();
    }

    /**
     * Проксирует вызов методов к реальному клиенту с перехватом ошибок.
     *
     * При ConnectionException или CommunicationException переходит в degraded-режим
     * и перенаправляет все последующие вызовы к NullRedisClient.
     *
     * @param  string $method Имя Redis-команды (get, set, incr и т.д.)
     * @param  array  $args   Аргументы команды
     * @return mixed          Результат команды или null/0/[] от NullRedisClient
     */
    public function __call(string $method, array $args): mixed
    {
        if (ChaosFlags::isRedisDisabled()) {
            return ($this->null)->$method(...$args);
        }
        if (!$this->alive) {
            return ($this->null)->$method(...$args);
        }
        try {
            return $this->client->$method(...$args);
        } catch (ConnectionException | CommunicationException $e) {
            $this->alive = false;
            error_log('[Redis] Connection lost on ' . $method . ': ' . $e->getMessage());
            return ($this->null)->$method(...$args);
        } catch (\Exception $e) {
            $this->alive = false;
            error_log('[Redis] Error on ' . $method . ': ' . $e->getMessage());
            return ($this->null)->$method(...$args);
        }
    }
}
