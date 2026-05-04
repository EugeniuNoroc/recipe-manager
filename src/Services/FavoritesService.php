<?php

declare(strict_types=1);

namespace App\Services;

use App\Database\NullRedisClient;
use App\Database\SafeRedis;

/**
 * Сервис управления избранными рецептами.
 *
 * Хранит множества избранного в Redis (SET user:{id}:favorites).
 * При недоступном Redis (NullRedisClient) все операции выполняются безопасно,
 * возвращая пустые результаты (fail-open).
 *
 * @package App\Services
 */
class FavoritesService
{
    /**
     * @param SafeRedis|NullRedisClient $redis Redis-клиент (реальный или заглушка)
     */
    public function __construct(private SafeRedis|NullRedisClient $redis) {}

    /**
     * Добавляет рецепт в избранное пользователя.
     *
     * @param int $userId   ID пользователя
     * @param int $recipeId ID рецепта
     */
    public function add(int $userId, int $recipeId): void
    {
        $this->redis->sadd("user:{$userId}:favorites", [$recipeId]);
    }

    /**
     * Удаляет рецепт из избранного пользователя.
     *
     * @param int $userId   ID пользователя
     * @param int $recipeId ID рецепта
     */
    public function remove(int $userId, int $recipeId): void
    {
        $this->redis->srem("user:{$userId}:favorites", $recipeId);
    }

    /**
     * Проверяет, находится ли рецепт в избранном пользователя.
     *
     * @param  int  $userId   ID пользователя
     * @param  int  $recipeId ID рецепта
     * @return bool
     */
    public function isFavorite(int $userId, int $recipeId): bool
    {
        return (bool) $this->redis->sismember("user:{$userId}:favorites", $recipeId);
    }

    /**
     * Возвращает все ID избранных рецептов пользователя.
     *
     * @param  int   $userId ID пользователя
     * @return int[]         Массив ID рецептов
     */
    public function getIds(int $userId): array
    {
        return array_map('intval', $this->redis->smembers("user:{$userId}:favorites"));
    }

    /**
     * Возвращает количество избранных рецептов пользователя.
     *
     * @param  int $userId ID пользователя
     * @return int
     */
    public function count(int $userId): int
    {
        return (int) $this->redis->scard("user:{$userId}:favorites");
    }
}
