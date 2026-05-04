<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Управление флагами симуляции сбоев (Chaos Engineering).
 *
 * Хранит состояние сбоев в JSON-файле (storage/chaos.json), который
 * читается/пишется с file-lock. Файловое хранилище выбрано намеренно:
 * оно работает даже когда Redis или MySQL "отключены" через эти же флаги.
 *
 * Все методы isXxx() возвращают false вне demo-режима — безопасно в production.
 *
 * @package App\Services
 */
class ChaosFlags
{
    /** @var string Путь к файлу состояния chaos.json */
    private static string $storageFile = '';

    /** @var string Окружение приложения (только 'demo' активирует флаги) */
    private static string $appEnv      = 'dev';

    /**
     * Инициализирует сервис: задаёт директорию хранилища и режим окружения.
     *
     * Должен вызываться в bootstrap.php до создания любых сервисов.
     *
     * @param string $storageDir Абсолютный путь к директории storage/
     * @param string $appEnv     Значение APP_ENV ('dev', 'demo', 'production')
     */
    public static function init(string $storageDir, string $appEnv = 'dev'): void
    {
        self::$storageFile = rtrim($storageDir, '/\\') . '/chaos.json';
        self::$appEnv      = $appEnv;
    }

    /**
     * Проверяет, симулируется ли отключение Redis.
     *
     * @return bool true только в demo-режиме при активном флаге
     */
    public static function isRedisDisabled(): bool
    {
        if (self::$appEnv !== 'demo') return false;
        return (bool) (self::read()['redis_disabled'] ?? false);
    }

    /**
     * Проверяет, симулируется ли отключение MySQL.
     *
     * @return bool true только в demo-режиме при активном флаге
     */
    public static function isMysqlDisabled(): bool
    {
        if (self::$appEnv !== 'demo') return false;
        return (bool) (self::read()['mysql_disabled'] ?? false);
    }

    /**
     * Включает симуляцию отключения Redis.
     */
    public static function disableRedis(): void
    {
        $data = self::read();
        $data['redis_disabled'] = true;
        self::write($data);
    }

    /**
     * Включает симуляцию отключения MySQL.
     */
    public static function disableMysql(): void
    {
        $data = self::read();
        $data['mysql_disabled'] = true;
        self::write($data);
    }

    /**
     * Выключает симуляцию отключения Redis.
     */
    public static function enableRedis(): void
    {
        $data = self::read();
        $data['redis_disabled'] = false;
        self::write($data);
    }

    /**
     * Выключает симуляцию отключения MySQL.
     */
    public static function enableMysql(): void
    {
        $data = self::read();
        $data['mysql_disabled'] = false;
        self::write($data);
    }

    /**
     * Возвращает текущее состояние флагов.
     *
     * @return array{redis_disabled:bool,mysql_disabled:bool}
     */
    public static function getStatus(): array
    {
        $data = self::read();
        return [
            'redis_disabled' => (bool) ($data['redis_disabled'] ?? false),
            'mysql_disabled' => (bool) ($data['mysql_disabled'] ?? false),
        ];
    }

    /**
     * @return array{redis_disabled:bool,mysql_disabled:bool}
     */
    private static function defaultState(): array
    {
        return ['redis_disabled' => false, 'mysql_disabled' => false];
    }

    /**
     * Читает состояние из файла с shared-lock.
     *
     * @return array
     */
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

        return [
            'redis_disabled' => (bool) ($raw['redis_disabled'] ?? false),
            'mysql_disabled' => (bool) ($raw['mysql_disabled'] ?? false),
        ];
    }

    /**
     * Записывает состояние в файл с exclusive-lock.
     *
     * @param array $data Данные для записи
     */
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
