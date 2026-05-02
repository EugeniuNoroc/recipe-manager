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
        return (bool) (self::read()['redis_disabled'] ?? false);
    }

    public static function isMysqlDisabled(): bool
    {
        if (self::$appEnv !== 'demo') return false;
        return (bool) (self::read()['mysql_disabled'] ?? false);
    }

    public static function disableRedis(): void
    {
        $data = self::read();
        $data['redis_disabled'] = true;
        self::write($data);
    }

    public static function disableMysql(): void
    {
        $data = self::read();
        $data['mysql_disabled'] = true;
        self::write($data);
    }

    public static function enableRedis(): void
    {
        $data = self::read();
        $data['redis_disabled'] = false;
        self::write($data);
    }

    public static function enableMysql(): void
    {
        $data = self::read();
        $data['mysql_disabled'] = false;
        self::write($data);
    }

    public static function getStatus(): array
    {
        $data = self::read();
        return [
            'redis_disabled' => (bool) ($data['redis_disabled'] ?? false),
            'mysql_disabled' => (bool) ($data['mysql_disabled'] ?? false),
        ];
    }

    private static function defaultState(): array
    {
        return ['redis_disabled' => false, 'mysql_disabled' => false];
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

        $raw = json_decode((string) $content, true);
        if (!is_array($raw)) return self::defaultState();

        // Migrate old format (fields *_until dropped)
        return [
            'redis_disabled' => (bool) ($raw['redis_disabled'] ?? false),
            'mysql_disabled' => (bool) ($raw['mysql_disabled'] ?? false),
        ];
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
