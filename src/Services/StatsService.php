<?php

declare(strict_types=1);

namespace App\Services;

use App\Database\NullRedisClient;
use App\Database\SafeRedis;

/**
 * Сервис статистики просмотров рецептов.
 *
 * Хранит счётчики просмотров в Redis (ключи recipe:{id}:views).
 * При недоступном Redis все операции деградируют безопасно (возвращают 0).
 *
 * @package App\Services
 */
class StatsService
{
    /**
     * @param SafeRedis|NullRedisClient $redis Redis-клиент
     */
    public function __construct(private SafeRedis|NullRedisClient $redis) {}

    /**
     * Инкрементирует счётчик просмотров рецепта.
     *
     * Обновляет два ключа: строковый счётчик recipe:{id}:views
     * и sorted set popular:recipes для быстрой выборки топа без KEYS.
     *
     * @param int $recipeId ID рецепта
     */
    public function incrementView(int $recipeId): void
    {
        $this->redis->incr("recipe:{$recipeId}:views");
        $this->redis->zincrby('popular:recipes', 1, (string) $recipeId);
    }

    /**
     * Возвращает количество просмотров рецепта.
     *
     * @param  int $recipeId ID рецепта
     * @return int
     */
    public function getViews(int $recipeId): int
    {
        return (int) ($this->redis->get("recipe:{$recipeId}:views") ?? 0);
    }

    /**
     * Пакетно получает счётчики просмотров для нескольких рецептов (один MGET).
     *
     * @param  int[]         $recipeIds Массив ID рецептов
     * @return array<int,int>           recipeId => views
     */
    public function getViewsForMany(array $recipeIds): array
    {
        if (empty($recipeIds)) {
            return [];
        }
        $keys   = array_map(fn($id) => "recipe:{$id}:views", $recipeIds);
        $values = $this->redis->mget($keys);
        $result = [];
        foreach ($recipeIds as $i => $id) {
            $result[$id] = (int) ($values[$i] ?? 0);
        }
        return $result;
    }

    /**
     * Возвращает топ-N рецептов по числу просмотров.
     *
     * Использует KEYS (допустимо для учебного проекта; в production — sorted set).
     *
     * @param  int           $limit Максимальное количество рецептов
     * @return array<int,int>       recipeId => views, sorted DESC
     */
    public function getTopPopular(int $limit = 10): array
    {
        $keys = $this->redis->keys('recipe:*:views');
        if (empty($keys)) {
            return [];
        }
        $values = $this->redis->mget($keys);
        $result = [];
        foreach ($keys as $i => $key) {
            if ($values[$i] !== null && preg_match('/recipe:(\d+):views/', (string) $key, $m)) {
                $result[(int) $m[1]] = (int) $values[$i];
            }
        }
        arsort($result);
        return array_slice($result, 0, $limit, true);
    }
}
