<?php

declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/vendor/autoload.php';

use Dotenv\Dotenv;

$dotenv = Dotenv::createImmutable($root);
$dotenv->load();

$host     = $_ENV['MYSQL_HOST']     ?? '127.0.0.1';
$port     = (int)($_ENV['MYSQL_PORT']     ?? 3306);
$database = $_ENV['MYSQL_DATABASE'] ?? 'recipe_manager';
$user     = $_ENV['MYSQL_USER']     ?? 'root';
$password = $_ENV['MYSQL_PASSWORD'] ?? '';

echo "Connecting to MySQL at {$host}:{$port} (db: {$database})...\n";

try {
    // Connect without database first to create it if needed
    $pdo = new PDO(
        "mysql:host={$host};port={$port};charset=utf8mb4",
        $user,
        $password,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$database}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    echo "[OK] Database '{$database}' ready.\n";

    $pdo->exec("USE `{$database}`");
} catch (PDOException $e) {
    echo "[ERROR] Connection failed: " . $e->getMessage() . "\n";
    exit(1);
}

$files = [
    __DIR__ . '/schema.sql' => 'schema',
    __DIR__ . '/seed.sql'   => 'seed',
];

foreach ($files as $file => $label) {
    if (!file_exists($file)) {
        echo "[SKIP] {$label}.sql not found.\n";
        continue;
    }

    echo "Applying {$label}.sql...\n";
    $sql = file_get_contents($file);

    // Strip single-line comments, then split on semicolons
    $sql = preg_replace('/^\s*--.*$/m', '', $sql);
    $statements = array_filter(
        array_map('trim', explode(';', $sql)),
        fn(string $s) => $s !== ''
    );

    $ok = 0;
    foreach ($statements as $stmt) {
        try {
            $pdo->exec($stmt);
            $ok++;
        } catch (PDOException $e) {
            echo "  [WARN] " . $e->getMessage() . "\n";
            echo "  Statement: " . substr($stmt, 0, 120) . "...\n";
        }
    }

    echo "[OK] {$label}.sql — {$ok} statement(s) applied.\n";
}

echo "\nMigration complete.\n";
