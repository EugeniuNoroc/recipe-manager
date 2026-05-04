<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/bootstrap.php';

use App\Services\ChaosFlags;
use App\Support\AdminGuard;
use App\Support\Csrf;
use App\Support\Flash;

$adminUser = AdminGuard::check();
$currentUser = $adminUser;

// Handle POST actions (PRG pattern)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Csrf::verify($_POST['_token'] ?? '')) {
        Flash::error('Неверный CSRF-токен.');
        header('Location: /admin/chaos.php');
        exit;
    }

    $action = $_GET['action'] ?? '';
    switch ($action) {
        case 'disable_redis':
            ChaosFlags::disableRedis();
            Flash::set('warning', 'Redis отключён. Не забудь нажать «Включить обратно» после демонстрации.');
            break;
        case 'enable_redis':
            ChaosFlags::enableRedis();
            Flash::success('Redis восстановлен.');
            break;
        case 'disable_mysql':
            ChaosFlags::disableMysql();
            Flash::set('warning', 'MySQL отключён — сайт в режиме maintenance. Не забудь нажать «Включить обратно» после демонстрации.');
            break;
        case 'enable_mysql':
            ChaosFlags::enableMysql();
            Flash::success('MySQL восстановлен.');
            break;
    }
    header('Location: /admin/chaos.php');
    exit;
}

$status    = ChaosFlags::getStatus();
$pageTitle = 'Chaos Engineering Panel';

function chaosStatusBadge(bool $disabled): string
{
    return $disabled
        ? '<span class="badge bg-danger fs-6">🔴 Симуляция падения</span>'
        : '<span class="badge bg-success fs-6">🟢 Доступен</span>';
}

require dirname(__DIR__, 2) . '/templates/header.php';
?>

<div class="d-flex gap-4 align-items-start">
    <?php require dirname(__DIR__, 2) . '/templates/admin_layout.php'; ?>
    <div class="flex-grow-1">

        <div class="d-flex justify-content-between align-items-center mb-3">
            <h2 class="mb-0">⚡ Chaos Engineering Panel</h2>
        </div>

        <div class="alert alert-warning d-flex align-items-start gap-2 mb-4">
            <span class="fs-5">⚠️</span>
            <div>
                <strong>Панель симуляции сбоев для демонстрации graceful degradation.</strong><br>
                Не использовать на продакшне.
                <strong>Отключение действует до явного включения.</strong>
                Не забудь нажать «Включить обратно» после демонстрации.
            </div>
        </div>

        <div class="row g-4">

            <!-- Redis card -->
            <div class="col-md-6">
                <div class="card h-100 shadow-sm border-2 <?= $status['redis_disabled'] ? 'border-danger' : 'border-success' ?>">
                    <div class="card-header fw-semibold <?= $status['redis_disabled'] ? 'bg-danger text-white' : 'bg-success text-white' ?>">
                        Redis
                    </div>
                    <div class="card-body d-flex flex-column">
                        <div class="mb-3">
                            <?= chaosStatusBadge($status['redis_disabled']) ?>
                        </div>
                        <p class="text-muted small mb-1">
                            <strong>При отключении деградируют:</strong><br>
                            сессии пользователей, избранное, статистика просмотров, rate limiter
                        </p>
                        <p class="text-danger small mb-3">
                            Отключение действует до явного включения.
                        </p>
                        <div class="mt-auto">
                            <form method="POST"
                                  action="/admin/chaos.php?action=<?= $status['redis_disabled'] ? 'enable_redis' : 'disable_redis' ?>">
                                <?= Csrf::field() ?>
                                <?php if ($status['redis_disabled']): ?>
                                    <button type="submit" class="btn btn-success w-100">✅ Включить обратно</button>
                                <?php else: ?>
                                    <button type="submit" class="btn btn-danger w-100">🔴 Отключить</button>
                                <?php endif; ?>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            <!-- MySQL card -->
            <div class="col-md-6">
                <div class="card h-100 shadow-sm border-2 <?= $status['mysql_disabled'] ? 'border-danger' : 'border-success' ?>">
                    <div class="card-header fw-semibold <?= $status['mysql_disabled'] ? 'bg-danger text-white' : 'bg-success text-white' ?>">
                        MySQL
                    </div>
                    <div class="card-body d-flex flex-column">
                        <div class="mb-3">
                            <?= chaosStatusBadge($status['mysql_disabled']) ?>
                        </div>
                        <p class="text-muted small mb-1">
                            <strong>При отключении:</strong><br>
                            весь сайт переходит в режим maintenance (HTTP 503)
                        </p>
                        <p class="text-danger small mb-3">
                            Отключение действует до явного включения.
                        </p>
                        <div class="mt-auto">
                            <form method="POST"
                                  action="/admin/chaos.php?action=<?= $status['mysql_disabled'] ? 'enable_mysql' : 'disable_mysql' ?>">
                                <?= Csrf::field() ?>
                                <?php if ($status['mysql_disabled']): ?>
                                    <button type="submit" class="btn btn-success w-100">✅ Включить обратно</button>
                                <?php else: ?>
                                    <button type="submit" class="btn btn-danger w-100">🔴 Отключить</button>
                                <?php endif; ?>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

        </div><!-- /row -->
    </div>
</div>

<?php require dirname(__DIR__, 2) . '/templates/footer.php'; ?>
