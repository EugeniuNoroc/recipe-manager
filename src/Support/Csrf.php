<?php

declare(strict_types=1);

namespace App\Support;

use App\Database\NullRedisClient;
use App\Database\SafeRedis;

class Csrf
{
    private static SafeRedis|NullRedisClient|null $redis = null;

    public static function setRedis(SafeRedis|NullRedisClient $redis): void
    {
        self::$redis = $redis;
    }

    public static function token(): string
    {
        // Check runtime availability — SafeRedis tracks whether the connection is live.
        $alive = (self::$redis instanceof SafeRedis) && self::$redis->isAvailable();

        if ($alive) {
            $key   = 'csrf:' . session_id();
            $token = self::$redis->get($key);
            // Re-check after the get() call: SafeRedis may have just lost the connection.
            if (self::$redis->isAvailable()) {
                if ($token === null || $token === '') {
                    $token = bin2hex(random_bytes(32));
                    self::$redis->setex($key, 3600, $token);
                }
                return (string) $token;
            }
            // Redis died mid-call — fall through to SESSION
        }

        // Fallback: store in $_SESSION when Redis is down
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    public static function verify(string $token): bool
    {
        $expected = self::token();
        return $expected !== '' && hash_equals($expected, $token);
    }

    public static function field(): string
    {
        return '<input type="hidden" name="_token" value="'
            . htmlspecialchars(self::token(), ENT_QUOTES) . '">';
    }
}
