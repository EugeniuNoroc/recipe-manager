<?php

declare(strict_types=1);

namespace App\Database;

use App\Services\ChaosFlags;
use PDO;

/**
 * Singleton-подключение к MySQL через PDO.
 *
 * Обеспечивает единственное PDO-соединение на протяжении всего HTTP-запроса.
 * Поддерживает симуляцию сбоя через ChaosFlags (демо-режим).
 *
 * @package App\Database
 */
class MySQLConnection
{
    /** @var PDO|null Единственный экземпляр соединения */
    private static ?PDO $instance = null;

    /**
     * Возвращает единственный экземпляр PDO-соединения (Singleton).
     *
     * При первом вызове создаёт соединение с параметрами из конфига.
     * При активном chaos-флаге MySQL выбрасывает PDOException для имитации сбоя.
     *
     * @param  array{host:string,port:int,database:string,user:string,password:string,charset?:string} $config
     * @return PDO          Активное PDO-соединение
     * @throws \PDOException При ошибке подключения или активном chaos-флаге
     */
    public static function getInstance(array $config): PDO
    {
        if (ChaosFlags::isMysqlDisabled()) {
            throw new \PDOException('[CHAOS] Simulated MySQL outage');
        }

        if (self::$instance === null) {
            $dsn = sprintf(
                'mysql:host=%s;port=%d;dbname=%s;charset=%s',
                $config['host'],
                $config['port'],
                $config['database'],
                $config['charset'] ?? 'utf8mb4',
            );

            self::$instance = new PDO($dsn, $config['user'], $config['password'], [
                PDO::ATTR_ERRMODE                  => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE       => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES         => false,
                PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true,
            ]);
        }

        return self::$instance;
    }

    private function __construct() {}
}
