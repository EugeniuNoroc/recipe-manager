<?php

declare(strict_types=1);

use Dotenv\Dotenv;
use Predis\Client;

// Standalone CLI script: rebuilds the popular:recipes sorted set from recipe:*:views counters.
// Usage: php migrations/rebuild_popular_zset.php

$root = dirname(__DIR__);
require_once $root . '/vendor/autoload.php';

$dotenv = Dotenv::createImmutable($root);
$dotenv->load();

$redisHost = $_ENV['REDIS_HOST'] ?? '127.0.0.1';
$redisPort = (int) ($_ENV['REDIS_PORT'] ?? 6379);

echo "Connecting to Redis at {$redisHost}:{$redisPort}...\n";

try {
    $redis = new Client([
        'scheme'  => 'tcp',
        'host'    => $redisHost,
        'port'    => $redisPort,
        'timeout' => 2.0,
    ]);
    $redis->ping();
    echo "[OK] Connected.\n\n";
} catch (\Exception $e) {
    echo "[ERROR] Cannot connect to Redis: " . $e->getMessage() . "\n";
    exit(1);
}

$keys = $redis->keys('recipe:*:views');

if (empty($keys)) {
    echo "No recipe:*:views keys found. Restored 0 keys.\n";
    exit(0);
}

$restored = 0;

foreach ($keys as $key) {
    if (!preg_match('/recipe:(\d+):views/', (string) $key, $m)) {
        continue;
    }
    $id    = (int) $m[1];
    $views = (int) $redis->get($key);

    if ($views <= 0) {
        continue;
    }

    // ZADD popular:recipes <score> <member>
    $redis->zadd('popular:recipes', [$id => $views]);
    echo "  zadd popular:recipes {$views} {$id}\n";
    $restored++;
}

echo "\nВосстановлено {$restored} ключей в ZSET popular:recipes.\n";
