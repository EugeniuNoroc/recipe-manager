<?php

declare(strict_types=1);

namespace App\Services;

use App\Database\NullRedisClient;
use App\Database\SafeRedis;

/**
 * Хранилище пользовательских сессий в Redis.
 *
 * Связывает случайный опaque-токен с userId. Токен хранится в httpOnly cookie,
 * а userId — в Redis-ключе session:{token} с TTL 24 часа.
 * При недоступном Redis токен не найден → пользователь считается неаутентифицированным.
 *
 * @package App\Services
 */
class SessionStore
{
    /** @var int TTL сессии в секундах (24 часа) */
    private const TTL = 86400;

    /**
     * @param SafeRedis|NullRedisClient $redis Redis-клиент
     */
    public function __construct(private SafeRedis|NullRedisClient $redis) {}

    /**
     * Создаёт новую сессию: сохраняет userId в Redis и возвращает токен.
     *
     * @param  int    $userId ID аутентифицированного пользователя
     * @return string         Опaque-токен (hex, 64 символа)
     */
    public function create(int $userId): string
    {
        $token = bin2hex(random_bytes(32));
        $this->redis->setex("session:{$token}", self::TTL, (string) $userId);
        return $token;
    }

    /**
     * Возвращает userId для валидного токена или null если истёк/неизвестен.
     *
     * @param  string   $token Токен из cookie
     * @return int|null        ID пользователя или null
     */
    public function get(string $token): ?int
    {
        $val = $this->redis->get("session:{$token}");
        return $val !== null ? (int) $val : null;
    }

    /**
     * Уничтожает сессию — удаляет Redis-ключ.
     *
     * @param string $token Токен для удаления
     */
    public function destroy(string $token): void
    {
        $this->redis->del(["session:{$token}"]);
    }
}
