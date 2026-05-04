<?php
use App\Services\ChaosFlags;
use App\Support\Csrf;
use App\Support\Flash;

/** @var string                  $pageTitle */
/** @var \App\Models\User|null   $currentUser */
$pageTitle   = $pageTitle   ?? 'Recipe Manager';
$currentUser = $currentUser ?? null;
$flashes     = Flash::all();

// Check Redis availability at render time (SafeRedis tracks live connection state).
$redisAvailable = isset($redis) && $redis instanceof \App\Database\SafeRedis
    ? $redis->isAvailable()
    : false;

// Chaos state — only computed in demo mode (file read; safe even when Redis is "down")
$appEnv      = $_ENV['APP_ENV'] ?? 'dev';
$chaosStatus = ($appEnv === 'demo') ? ChaosFlags::getStatus() : [];
$chaosRedis  = $chaosStatus['redis_disabled'] ?? false;
$chaosMySQL  = $chaosStatus['mysql_disabled'] ?? false;
$chaosAny    = $chaosRedis || $chaosMySQL;

// Build banner label with correct Russian grammar
if ($chaosRedis && $chaosMySQL) {
    $chaosLabel = 'Redis и MySQL отключены';
} elseif ($chaosRedis) {
    $chaosLabel = 'Redis отключён';
} else {
    $chaosLabel = 'MySQL отключён';
}

// Admin check for chaos banner — role-based, no separate session needed
$adminLoggedIn = ($currentUser !== null && $currentUser->isAdmin());
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?> — Recipe Manager</title>
    <link rel="stylesheet"
          href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css"
          integrity="sha384-T3c6CoIi6uLrA9TneNEoa7RxnatzjcDSCmG1MXxSR1GAsXEV/Dwwykc2MPK8M2HN"
          crossorigin="anonymous">
    <style>
        .badge-cat  { background: #6f42c1 !important; }
        .badge-easy { background: #198754 !important; }
        .badge-med  { background: #fd7e14 !important; }
        .badge-hard { background: #dc3545 !important; }
        .card { transition: box-shadow .15s; }
        .card:hover { box-shadow: 0 .25rem .75rem rgba(0,0,0,.1); }
        textarea { resize: vertical; }
        .views-badge { font-size: .7rem; opacity: .75; }
    </style>
    <?php if ($chaosAny): ?>
    <meta http-equiv="refresh" content="10">
    <?php endif; ?>
</head>
<body class="bg-light">

<?php if ($chaosAny): ?>
<div style="position:sticky;top:0;z-index:1030;background:#dc3545;color:#fff;
            padding:10px 16px;display:flex;align-items:center;
            justify-content:space-between;font-size:1rem;font-weight:600;">
    <span>⚠️ DEMO MODE: <?= htmlspecialchars($chaosLabel) ?></span>
    <?php if ($adminLoggedIn): ?>
        <a href="/admin/chaos.php" class="btn btn-light btn-sm ms-3 fw-semibold">
            Включить обратно
        </a>
    <?php else: ?>
        <a href="/login.php"
           class="text-white text-decoration-none small ms-3 opacity-75">admin</a>
    <?php endif; ?>
</div>
<?php endif; ?>

<nav class="navbar navbar-expand-lg navbar-dark bg-primary shadow-sm">
    <div class="container">
        <a class="navbar-brand fw-bold" href="/index.php">🍽 Recipe Manager</a>
        <button class="navbar-toggler" type="button"
                data-bs-toggle="collapse" data-bs-target="#navMain"
                aria-controls="navMain" aria-expanded="false">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navMain">
            <ul class="navbar-nav me-auto gap-1">
                <li class="nav-item">
                    <a class="nav-link" href="/index.php">Все рецепты</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="/stats.php">Статистика</a>
                </li>
                <?php if ($currentUser): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="/create.php">+ Создать</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="/favorites.php">♥ Избранное</a>
                    </li>
                    <?php if ($currentUser->isAdmin()): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="/admin/index.php">⚙️ Админ-панель</a>
                    </li>
                    <?php endif; ?>
                <?php endif; ?>
            </ul>
            <ul class="navbar-nav align-items-center gap-1">
                <?php if (!$redisAvailable): ?>
                    <li class="nav-item">
                        <span class="badge bg-warning text-dark">⚠ Redis offline</span>
                    </li>
                <?php endif; ?>
                <?php if ($currentUser): ?>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">
                            👤 <?= htmlspecialchars($currentUser->username) ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li>
                                <form method="POST" action="/logout.php" class="m-0">
                                    <?= Csrf::field() ?>
                                    <button type="submit" class="dropdown-item text-danger">Выйти</button>
                                </form>
                            </li>
                        </ul>
                    </li>
                <?php else: ?>
                    <li class="nav-item">
                        <a class="nav-link" href="/login.php">Войти</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="/register.php">Регистрация</a>
                    </li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>

<main class="container py-4">

<?php if (!$redisAvailable): ?>
    <div class="alert alert-warning alert-dismissible fade show py-2 mb-3" role="alert">
        <strong>Redis offline</strong> — избранное и статистика временно недоступны.
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php foreach ($flashes as $f): ?>
    <div class="alert alert-<?= htmlspecialchars($f['type']) ?> alert-dismissible fade show" role="alert">
        <?= htmlspecialchars($f['message']) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endforeach; ?>
