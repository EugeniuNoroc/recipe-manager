<?php

declare(strict_types=1);

namespace App\Services;

use App\Database\NullRedisClient;
use App\Database\SafeRedis;

/**
 * Ограничитель частоты запросов на основе Redis.
 *
 * Реализует скользящее окно через паттерн INCR + EXPIRE.
 * При недоступном Redis деградирует в fail-open режим (всегда разрешает запрос).
 *
 * @package App\Services
 */
class RateLimiter
{
    /**
     * @param SafeRedis|NullRedisClient $redis Redis-клиент
     */
    public function __construct(private SafeRedis|NullRedisClient $redis) {}

    /**
     * Проверяет, не превышен ли лимит запросов для ключа.
     *
     * Возвращает true если запрос разрешён (в пределах лимита).
     * Fail-open: при Redis = NullRedisClient INCR возвращает 0, что всегда ≤ $limit.
     *
     * @param  string $key       Идентификатор клиента/действия (например IP или user ID)
     * @param  int    $limit     Максимальное количество запросов в окне
     * @param  int    $windowSec Размер временного окна в секундах
     * @return bool              true — запрос разрешён, false — лимит превышен
     */
    public function check(string $key, int $limit, int $windowSec): bool
    {
        $current = (int) $this->redis->incr($key);
        if ($current === 1) {
            $this->redis->expire($key, $windowSec);
        }
        return $current <= $limit;
    }
}
