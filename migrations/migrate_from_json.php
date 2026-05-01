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
    $pdo = new PDO(
        "mysql:host={$host};port={$port};dbname={$database};charset=utf8mb4",
        $user,
        $password,
        [
            PDO::ATTR_ERRMODE          => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );
    echo "[OK] Connected.\n\n";
} catch (PDOException $e) {
    echo "[ERROR] Connection failed: " . $e->getMessage() . "\n";
    exit(1);
}

// ── 1. Create / find legacy system user ───────────────────────────────────────
$legacyEmail = 'legacy@local';
$stmt = $pdo->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
$stmt->execute([$legacyEmail]);
$legacyRow = $stmt->fetch();

if ($legacyRow) {
    $legacyId = (int) $legacyRow['id'];
    echo "[INFO] Системный пользователь legacy@local уже существует (id={$legacyId}).\n";
} else {
    $randomPassword = bin2hex(random_bytes(16));
    $hash = password_hash($randomPassword, PASSWORD_BCRYPT);
    $pdo->prepare('INSERT INTO users (username, email, password) VALUES (?, ?, ?)')
        ->execute(['legacy', $legacyEmail, $hash]);
    $legacyId = (int) $pdo->lastInsertId();
    echo "[INFO] Создан системный пользователь legacy@local.\n";
    echo "       Случайный пароль (сохрани или сбрось): {$randomPassword}\n";
}

// ── 2. Load data.json ─────────────────────────────────────────────────────────
$jsonPath = $root . '/data.json';
if (!file_exists($jsonPath)) {
    echo "[ERROR] data.json не найден: {$jsonPath}\n";
    exit(1);
}

$recipes = json_decode(file_get_contents($jsonPath), true);
if (!is_array($recipes)) {
    echo "[ERROR] Не удалось разобрать data.json (JSON некорректен).\n";
    exit(1);
}

$total = count($recipes);

// ── 3. Resolve first category as fallback ────────────────────────────────────
$firstCat = $pdo->query('SELECT id FROM categories ORDER BY id LIMIT 1')->fetch();
if (!$firstCat) {
    echo "[ERROR] В БД нет категорий. Сначала выполните: php migrations/run.php\n";
    exit(1);
}
$defaultCategoryId = (int) $firstCat['id'];

// ── Helpers ───────────────────────────────────────────────────────────────────
$categoryCache = [];
$getCategoryId = function (string $name) use ($pdo, &$categoryCache, $defaultCategoryId): int {
    if ($name === '') {
        return $defaultCategoryId;
    }
    if (isset($categoryCache[$name])) {
        return $categoryCache[$name];
    }
    $stmt = $pdo->prepare('SELECT id FROM categories WHERE name = ? LIMIT 1');
    $stmt->execute([$name]);
    $row = $stmt->fetch();
    if ($row) {
        return $categoryCache[$name] = (int) $row['id'];
    }
    $pdo->prepare('INSERT INTO categories (name) VALUES (?)')->execute([$name]);
    return $categoryCache[$name] = (int) $pdo->lastInsertId();
};

$tagCache = [];
$getTagId = function (string $name) use ($pdo, &$tagCache): int {
    if (isset($tagCache[$name])) {
        return $tagCache[$name];
    }
    $stmt = $pdo->prepare('SELECT id FROM tags WHERE name = ? LIMIT 1');
    $stmt->execute([$name]);
    $row = $stmt->fetch();
    if ($row) {
        return $tagCache[$name] = (int) $row['id'];
    }
    $pdo->prepare('INSERT INTO tags (name) VALUES (?)')->execute([$name]);
    return $tagCache[$name] = (int) $pdo->lastInsertId();
};

// ── 4. Import ─────────────────────────────────────────────────────────────────
echo "\nИмпорт рецептов из data.json ({$total} шт.):\n";

$imported = 0;
$skipped  = 0;

foreach ($recipes as $i => $item) {
    $num   = $i + 1;
    $title = trim((string) ($item['title'] ?? ''));

    echo "[{$num}/{$total}] Импортирую \"{$title}\"... ";

    // Check for duplicate: same title + same legacy user_id
    $stmt = $pdo->prepare('SELECT id FROM recipes WHERE title = ? AND user_id = ? LIMIT 1');
    $stmt->execute([$title, $legacyId]);
    if ($stmt->fetch()) {
        echo "ПРОПУЩЕН (дубликат)\n";
        $skipped++;
        continue;
    }

    $catName    = trim((string) ($item['category'] ?? ''));
    $categoryId = $getCategoryId($catName);

    $createdAt = (string) ($item['created_at'] ?? date('Y-m-d'));
    if (strlen($createdAt) > 10) {
        $createdAt = substr($createdAt, 0, 10);
    }

    $difficulty = (string) ($item['difficulty'] ?? 'Средне');
    if (!in_array($difficulty, ['Легко', 'Средне', 'Сложно'], true)) {
        $difficulty = 'Средне';
    }

    $stmt = $pdo->prepare(
        'INSERT INTO recipes
            (user_id, title, author, prep_time, category_id, difficulty,
             ingredients, instructions, created_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([
        $legacyId,
        $title,
        trim((string) ($item['author'] ?? '')),
        (int) ($item['prep_time'] ?? 0),
        $categoryId,
        $difficulty,
        trim((string) ($item['ingredients'] ?? '')),
        trim((string) ($item['instructions'] ?? '')),
        $createdAt,
    ]);
    $recipeId = (int) $pdo->lastInsertId();

    foreach ((array) ($item['tags'] ?? []) as $tagName) {
        $tagName = trim((string) $tagName);
        if ($tagName === '') {
            continue;
        }
        $tagId = $getTagId($tagName);
        $pdo->prepare('INSERT IGNORE INTO recipe_tags (recipe_id, tag_id) VALUES (?, ?)')
            ->execute([$recipeId, $tagId]);
    }

    echo "OK\n";
    $imported++;
}

echo "\nГотово: импортировано {$imported} новых, пропущено {$skipped} дубликатов.\n";
