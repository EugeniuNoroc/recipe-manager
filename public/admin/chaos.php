<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/vendor/autoload.php';

$config = require dirname(__DIR__, 2) . '/config/config.php';

use App\Services\ChaosFlags;

ChaosFlags::init(dirname(__DIR__, 2) . '/storage', $config['app']['env']);

if ($config['app']['env'] !== 'demo') {
    http_response_code(404);
    exit('Not Found');
}

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => $config['app']['cookie_secure'],
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

// Validate admin session (1 hour TTL)
$adminValid = isset($_SESSION['chaos_admin'])
    && $_SESSION['chaos_admin'] === true
    && isset($_SESSION['chaos_admin_time'])
    && (time() - (int) $_SESSION['chaos_admin_time']) < 3600;

if (!$adminValid) {
    header('Location: /admin/login.php');
    exit;
}

$csrfToken = $_SESSION['chaos_csrf'] ?? ($_SESSION['chaos_csrf'] = bin2hex(random_bytes(16)));

// Handle POST actions (PRG pattern)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postedCsrf = $_POST['csrf'] ?? '';
    if (!hash_equals($csrfToken, $postedCsrf)) {
        $_SESSION['chaos_flash'] = ['type' => 'danger', 'msg' => 'Неверный CSRF-токен.'];
    } else {
        $action = $_GET['action'] ?? '';
        switch ($action) {
            case 'disable_redis':
                ChaosFlags::disableRedis(30);
                $until = date('H:i:s', time() + 30);
                $_SESSION['chaos_flash'] = ['type' => 'warning', 'msg' => "Redis отключён до {$until}. Автовосстановление через 30 сек."];
                break;
            case 'enable_redis':
                ChaosFlags::enableRedis();
                $_SESSION['chaos_flash'] = ['type' => 'success', 'msg' => 'Redis восстановлен.'];
                break;
            case 'disable_mysql':
                ChaosFlags::disableMysql(30);
                $until = date('H:i:s', time() + 30);
                $_SESSION['chaos_flash'] = ['type' => 'warning', 'msg' => "MySQL отключён до {$until}. Автовосстановление через 30 сек."];
                break;
            case 'enable_mysql':
                ChaosFlags::enableMysql();
                $_SESSION['chaos_flash'] = ['type' => 'success', 'msg' => 'MySQL восстановлен.'];
                break;
        }
    }
    header('Location: /admin/chaos.php');
    exit;
}

// Read flash after redirect
$flash = [];
if (!empty($_SESSION['chaos_flash'])) {
    $flash = $_SESSION['chaos_flash'];
    unset($_SESSION['chaos_flash']);
}

$status = ChaosFlags::getStatus();

function statusBadge(bool $disabled, int $secondsLeft): string
{
    if ($disabled) {
        return '<span class="badge bg-danger fs-6">🔴 Симуляция падения'
            . ($secondsLeft > 0 ? " (восстановление через {$secondsLeft} сек)" : '')
            . '</span>';
    }
    return '<span class="badge bg-success fs-6">🟢 Доступен</span>';
}

?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chaos Engineering Panel</title>
    <link rel="stylesheet"
          href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css"
          integrity="sha384-T3c6CoIi6uLrA9TneNEoa7RxnatzjcDSCmG1MXxSR1GAsXEV/Dwwykc2MPK8M2HN"
          crossorigin="anonymous">
    <?php if ($status['redis_disabled'] || $status['mysql_disabled']): ?>
    <meta http-equiv="refresh" content="5">
    <?php endif; ?>
</head>
<body class="bg-light">

<div class="container py-4" style="max-width:780px">

    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2 class="mb-0">🛠 Chaos Engineering Panel</h2>
        <div class="d-flex gap-2">
            <a href="/" class="btn btn-outline-secondary btn-sm">← На сайт</a>
            <form method="POST" action="/admin/logout.php" class="m-0">
                <button type="submit" class="btn btn-outline-danger btn-sm">Выйти</button>
            </form>
        </div>
    </div>

    <div class="alert alert-warning d-flex align-items-start gap-2 mb-4">
        <span class="fs-5">⚠️</span>
        <div>
            <strong>Панель симуляции сбоев для демонстрации graceful degradation.</strong><br>
            Не использовать на продакшне. Все изменения автоматически откатываются через <strong>30 секунд</strong>.
        </div>
    </div>

    <?php if ($flash): ?>
    <div class="alert alert-<?= htmlspecialchars($flash['type']) ?> alert-dismissible fade show">
        <?= htmlspecialchars($flash['msg']) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <div class="row g-4">

        <!-- Redis card -->
        <div class="col-md-6">
            <div class="card h-100 shadow-sm <?= $status['redis_disabled'] ? 'border-danger' : 'border-success' ?> border-2">
                <div class="card-header fw-semibold <?= $status['redis_disabled'] ? 'bg-danger text-white' : 'bg-success text-white' ?>">
                    Redis
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <?= statusBadge($status['redis_disabled'], $status['redis_seconds_left']) ?>
                    </div>
                    <p class="text-muted small mb-3">
                        <strong>При отключении деградируют:</strong><br>
                        сессии пользователей, избранное, статистика просмотров, rate limiter
                    </p>
                    <form method="POST"
                          action="/admin/chaos.php?action=<?= $status['redis_disabled'] ? 'enable_redis' : 'disable_redis' ?>">
                        <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrfToken) ?>">
                        <?php if ($status['redis_disabled']): ?>
                            <button type="submit" class="btn btn-success w-100">
                                ✅ Включить обратно сейчас
                            </button>
                        <?php else: ?>
                            <button type="submit" class="btn btn-danger w-100">
                                🔴 Отключить (на 30 сек)
                            </button>
                        <?php endif; ?>
                    </form>
                </div>
            </div>
        </div>

        <!-- MySQL card -->
        <div class="col-md-6">
            <div class="card h-100 shadow-sm <?= $status['mysql_disabled'] ? 'border-danger' : 'border-success' ?> border-2">
                <div class="card-header fw-semibold <?= $status['mysql_disabled'] ? 'bg-danger text-white' : 'bg-success text-white' ?>">
                    MySQL
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <?= statusBadge($status['mysql_disabled'], $status['mysql_seconds_left']) ?>
                    </div>
                    <p class="text-muted small mb-3">
                        <strong>При отключении:</strong><br>
                        весь сайт переходит в режим maintenance (HTTP 503), автовозврат через 30 сек
                    </p>
                    <form method="POST"
                          action="/admin/chaos.php?action=<?= $status['mysql_disabled'] ? 'enable_mysql' : 'disable_mysql' ?>">
                        <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrfToken) ?>">
                        <?php if ($status['mysql_disabled']): ?>
                            <button type="submit" class="btn btn-success w-100">
                                ✅ Включить обратно сейчас
                            </button>
                        <?php else: ?>
                            <button type="submit" class="btn btn-danger w-100">
                                🔴 Отключить (на 30 сек)
                            </button>
                        <?php endif; ?>
                    </form>
                </div>
            </div>
        </div>

    </div><!-- /row -->

    <div class="text-muted small text-center mt-4">
        Статус обновляется автоматически каждые 5 сек пока что-то отключено.
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-C6RzsynM9kWDrMNeT87bh95OGNyZPhcTNXj1NW7RuBCsyN/o0jlpcV8Qyq46cDfL"
        crossorigin="anonymous"></script>
</body>
</html>
