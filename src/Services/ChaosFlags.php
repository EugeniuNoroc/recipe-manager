<?php

declare(strict_types=1);

namespace App\Services;

class ChaosFlags
{
    private static string $storageFile = '';
    private static string $appEnv      = 'dev';

    public static function init(string $storageDir, string $appEnv = 'dev'): void
    {
        self::$storageFile = rtrim($storageDir, '/\\') . '/chaos.json';
        self::$appEnv      = $appEnv;
    }

    public static function isRedisDisabled(): bool
    {
        if (self::$appEnv !== 'demo') return false;
        $data  = self::read();
        if (!($data['redis_disabled'] ?? false)) return false;
        $until = $data['redis_disabled_until'] ?? null;
        if ($until !== null && time() > $until) {
            self::enableRedis();
            return false;
        }
        return true;
    }

    public static function isMysqlDisabled(): bool
    {
        if (self::$appEnv !== 'demo') return false;
        $data  = self::read();
        if (!($data['mysql_disabled'] ?? false)) return false;
        $until = $data['mysql_disabled_until'] ?? null;
        if ($until !== null && time() > $until) {
            self::enableMysql();
            return false;
        }
        return true;
    }

    public static function disableRedis(int $seconds = 30): void
    {
        $data = self::read();
        $data['redis_disabled']       = true;
        $data['redis_disabled_until'] = time() + $seconds;
        self::write($data);
    }

    public static function disableMysql(int $seconds = 30): void
    {
        $data = self::read();
        $data['mysql_disabled']       = true;
        $data['mysql_disabled_until'] = time() + $seconds;
        self::write($data);
    }

    public static function enableRedis(): void
    {
        $data = self::read();
        $data['redis_disabled']       = false;
        $data['redis_disabled_until'] = null;
        self::write($data);
    }

    public static function enableMysql(): void
    {
        $data = self::read();
        $data['mysql_disabled']       = false;
        $data['mysql_disabled_until'] = null;
        self::write($data);
    }

    public static function getStatus(): array
    {
        $data = self::read();
        $now  = time();

        $redisUntil    = $data['redis_disabled_until'] ?? null;
        $mysqlUntil    = $data['mysql_disabled_until'] ?? null;
        $redisDisabled = ($data['redis_disabled'] ?? false)
            && ($redisUntil === null || $now < $redisUntil);
        $mysqlDisabled = ($data['mysql_disabled'] ?? false)
            && ($mysqlUntil === null || $now < $mysqlUntil);

        if (($data['redis_disabled'] ?? false) && !$redisDisabled) {
            self::enableRedis();
        }
        if (($data['mysql_disabled'] ?? false) && !$mysqlDisabled) {
            self::enableMysql();
        }

        return [
            'redis_disabled'     => $redisDisabled,
            'redis_seconds_left' => ($redisDisabled && $redisUntil) ? max(0, $redisUntil - $now) : 0,
            'mysql_disabled'     => $mysqlDisabled,
            'mysql_seconds_left' => ($mysqlDisabled && $mysqlUntil) ? max(0, $mysqlUntil - $now) : 0,
        ];
    }

    private static function defaultState(): array
    {
        return [
            'redis_disabled'       => false,
            'redis_disabled_until' => null,
            'mysql_disabled'       => false,
            'mysql_disabled_until' => null,
        ];
    }

    private static function read(): array
    {
        if (self::$storageFile === '' || !file_exists(self::$storageFile)) {
            return self::defaultState();
        }
        $fp = @fopen(self::$storageFile, 'r');
        if (!$fp) return self::defaultState();
        flock($fp, LOCK_SH);
        $content = stream_get_contents($fp);
        flock($fp, LOCK_UN);
        fclose($fp);
        return json_decode((string) $content, true) ?? self::defaultState();
    }

    private static function write(array $data): void
    {
        if (self::$storageFile === '') return;
        $dir = dirname(self::$storageFile);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        $fp = @fopen(self::$storageFile, 'c');
        if (!$fp) return;
        flock($fp, LOCK_EX);
        ftruncate($fp, 0);
        rewind($fp);
        fwrite($fp, json_encode($data, JSON_PRETTY_PRINT));
        flock($fp, LOCK_UN);
        fclose($fp);
    }
}
