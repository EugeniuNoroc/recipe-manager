<?php

declare(strict_types=1);

namespace App\Database;

use App\Services\ChaosFlags;
use Predis\Client;
use Predis\Connection\ConnectionException;
use Predis\CommunicationException;

/**
 * Proxy around Predis\Client that catches connection/communication exceptions
 * on every call and transparently falls back to NullRedisClient behavior.
 * Marks itself unavailable after the first failure so callers can react.
 * Also degrades gracefully when ChaosFlags simulates a Redis outage.
 */
class SafeRedis
{
    private bool $alive = true;
    private NullRedisClient $null;

    public function __construct(private Client $client)
    {
        $this->null = new NullRedisClient();
    }

    public function isAvailable(): bool
    {
        return $this->alive && !ChaosFlags::isRedisDisabled();
    }

    public function __call(string $method, array $args): mixed
    {
        if (ChaosFlags::isRedisDisabled()) {
            return ($this->null)->$method(...$args);
        }
        if (!$this->alive) {
            return ($this->null)->$method(...$args);
        }
        try {
            return $this->client->$method(...$args);
        } catch (ConnectionException | CommunicationException $e) {
            $this->alive = false;
            error_log('[Redis] Connection lost on ' . $method . ': ' . $e->getMessage());
            return ($this->null)->$method(...$args);
        } catch (\Exception $e) {
            $this->alive = false;
            error_log('[Redis] Error on ' . $method . ': ' . $e->getMessage());
            return ($this->null)->$method(...$args);
        }
    }
}
