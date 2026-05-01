<?php

declare(strict_types=1);

namespace App\Services;

use App\Database\NullRedisClient;
use App\Database\SafeRedis;

class SessionStore
{
    private const TTL = 86400; // 24 h

    public function __construct(private SafeRedis|NullRedisClient $redis) {}

    /** Stores userId in Redis, returns the opaque token */
    public function create(int $userId): string
    {
        $token = bin2hex(random_bytes(32));
        $this->redis->setex("session:{$token}", self::TTL, (string) $userId);
        return $token;
    }

    /** Returns userId for a valid token, null if expired/unknown */
    public function get(string $token): ?int
    {
        $val = $this->redis->get("session:{$token}");
        return $val !== null ? (int) $val : null;
    }

    public function destroy(string $token): void
    {
        $this->redis->del(["session:{$token}"]);
    }
}
