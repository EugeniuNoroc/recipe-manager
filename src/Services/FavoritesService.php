<?php

declare(strict_types=1);

namespace App\Services;

use App\Database\NullRedisClient;
use App\Database\SafeRedis;

class FavoritesService
{
    public function __construct(private SafeRedis|NullRedisClient $redis) {}

    public function add(int $userId, int $recipeId): void
    {
        $this->redis->sadd("user:{$userId}:favorites", [$recipeId]);
    }

    public function remove(int $userId, int $recipeId): void
    {
        $this->redis->srem("user:{$userId}:favorites", $recipeId);
    }

    public function isFavorite(int $userId, int $recipeId): bool
    {
        return (bool) $this->redis->sismember("user:{$userId}:favorites", $recipeId);
    }

    /** @return int[] */
    public function getIds(int $userId): array
    {
        return array_map('intval', $this->redis->smembers("user:{$userId}:favorites"));
    }

    public function count(int $userId): int
    {
        return (int) $this->redis->scard("user:{$userId}:favorites");
    }
}
