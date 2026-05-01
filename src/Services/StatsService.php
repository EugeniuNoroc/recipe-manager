<?php

declare(strict_types=1);

namespace App\Services;

use App\Database\NullRedisClient;
use App\Database\SafeRedis;

class StatsService
{
    public function __construct(private SafeRedis|NullRedisClient $redis) {}

    public function incrementView(int $recipeId): void
    {
        $this->redis->incr("recipe:{$recipeId}:views");
    }

    public function getViews(int $recipeId): int
    {
        return (int) ($this->redis->get("recipe:{$recipeId}:views") ?? 0);
    }

    /**
     * Batch-fetches view counts for many recipes in one MGET call.
     * @param  int[]         $recipeIds
     * @return array<int,int> recipeId => views
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
     * Returns top-N recipes by view count.
     * Uses KEYS (acceptable for a lab; production would use a sorted set).
     * @return array<int,int> recipeId => views, sorted desc
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
