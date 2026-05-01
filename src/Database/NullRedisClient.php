<?php

declare(strict_types=1);

namespace App\Database;

/**
 * No-op Redis stub used when Redis is unavailable.
 * Services using this stay functional with degraded behavior (fail-open).
 */
class NullRedisClient
{
    public function __call(string $method, array $args): mixed
    {
        return match (strtolower($method)) {
            'smembers', 'keys'  => [],
            // mget must return an array the same length as the input key list
            'mget'              => array_fill(0, count($args[0] ?? []), null),
            'scard', 'incr', 'decr', 'del',
            'sadd', 'srem', 'expire', 'ttl',
            'sismember', 'hexists', 'exists', 'ping' => 0,
            default             => null,
        };
    }
}
