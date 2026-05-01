<?php

declare(strict_types=1);

namespace App\Database;

use App\Services\ChaosFlags;
use PDO;

class MySQLConnection
{
    private static ?PDO $instance = null;

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
