<?php

declare(strict_types=1);

namespace App\Services;

use App\Database\NullRedisClient;
use App\Database\SafeRedis;

class RateLimiter
{
    public function __construct(private SafeRedis|NullRedisClient $redis) {}

    /**
     * Returns true when the request is within the limit (fail-open: true when Redis is down).
     * Uses INCR + EXPIRE sliding window pattern.
     */
    public function check(string $key, int $limit, int $windowSec): bool
    {
        $current = (int) $this->redis->incr($key);
        if ($current === 1) {
            // First hit in this window: set expiry
            $this->redis->expire($key, $windowSec);
        }
        return $current <= $limit;
        // With NullRedisClient incr() returns 0, so 0 <= $limit is always true → fail-open
    }
}
