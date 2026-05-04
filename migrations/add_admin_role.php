<?php

declare(strict_types=1);

use Dotenv\Dotenv;

// Can be run standalone: php migrations/add_admin_role.php
// Or included from run.php (where $pdo is already available in scope).

if (!isset($pdo)) {
    $root = dirname(__DIR__);
    require_once $root . '/vendor/autoload.php';

    $dotenv = Dotenv::createImmutable($root);
    $dotenv->load();

    $host     = $_ENV['MYSQL_HOST']     ?? '127.0.0.1';
    $port     = (int)($_ENV['MYSQL_PORT']     ?? 3306);
    $database = $_ENV['MYSQL_DATABASE'] ?? 'recipe_manager';
    $user     = $_ENV['MYSQL_USER']     ?? 'root';
    $password = $_ENV['MYSQL_PASSWORD'] ?? '';

    echo "Connecting to MySQL at {$host}:{$port} (db: {$database})...\n";

    try {
        $pdo = new PDO(
            "mysql:host={$host};port={$port};dbname={$database};charset=utf8mb4",
            $user,
            $password,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
        echo "[OK] Connected.\n";
    } catch (PDOException $e) {
        echo "[ERROR] Connection failed: " . $e->getMessage() . "\n";
        exit(1);
    }
}

echo "Running add_admin_role migration...\n";

$migrations = [
    'role' => [
        'sql'  => "ALTER TABLE users ADD COLUMN role ENUM('user','admin') NOT NULL DEFAULT 'user'",
        'desc' => "ENUM('user','admin') DEFAULT 'user'",
    ],
    'is_blocked' => [
        'sql'  => 'ALTER TABLE users ADD COLUMN is_blocked TINYINT(1) NOT NULL DEFAULT 0',
        'desc' => 'TINYINT(1) DEFAULT 0',
    ],
];

foreach ($migrations as $column => $migration) {
    $check = $pdo->prepare('SHOW COLUMNS FROM users LIKE ?');
    $check->execute([$column]);

    if ($check->fetch()) {
        echo "[SKIP] Column '{$column}' already exists, skipping.\n";
    } else {
        try {
            $pdo->exec($migration['sql']);
            echo "[OK]   Column '{$column}' ({$migration['desc']}) added.\n";
        } catch (PDOException $e) {
            echo "[ERROR] Failed to add '{$column}': " . $e->getMessage() . "\n";
        }
    }
}

echo "[DONE] add_admin_role migration complete.\n";
