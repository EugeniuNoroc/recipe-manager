<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/bootstrap.php';

use App\Services\AdminService;
use App\Support\AdminGuard;

$adminUser   = AdminGuard::check();
$currentUser = $adminUser;

$adminService = new AdminService($pdo, $redis);
$stats        = $adminService->getDashboardStats();

$pageTitle = 'Админ-панель — Дашборд';
require dirname(__DIR__, 2) . '/templates/header.php';
?>

<div class="d-flex gap-4 align-items-start">
    <?php require dirname(__DIR__, 2) . '/templates/admin_layout.php'; ?>
    <div class="flex-grow-1">

        <h2 class="mb-4">📊 Дашборд</h2>

        <div class="row g-3 mb-4">
            <div class="col-sm-6 col-lg-3">
                <div class="card text-center shadow-sm h-100">
                    <div class="card-body">
                        <div class="display-6 fw-bold text-primary"><?= $stats['total_users'] ?></div>
                        <div class="text-muted small mt-1">Всего пользователей</div>
                        <div class="text-muted small">из них адм.: <?= $stats['admin_users'] ?></div>
                    </div>
                    <div class="card-footer">
                        <a href="/admin/users.php" class="btn btn-outline-primary btn-sm w-100">Управление</a>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-lg-3">
                <div class="card text-center shadow-sm h-100">
                    <div class="card-body">
                        <div class="display-6 fw-bold text-success"><?= $stats['total_recipes'] ?></div>
                        <div class="text-muted small mt-1">Всего рецептов</div>
                        <div class="text-muted small">за 7 дней: +<?= $stats['new_recipes_7d'] ?></div>
                    </div>
                    <div class="card-footer">
                        <a href="/admin/recipes.php" class="btn btn-outline-success btn-sm w-100">Управление</a>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-lg-3">
                <div class="card text-center shadow-sm h-100">
                    <div class="card-body">
                        <div class="display-6 fw-bold text-warning"><?= $stats['total_categories'] ?></div>
                        <div class="text-muted small mt-1">Категорий</div>
                        <div class="text-muted small">тегов: <?= $stats['total_tags'] ?></div>
                    </div>
                    <div class="card-footer">
                        <a href="/admin/categories.php" class="btn btn-outline-warning btn-sm w-100">Категории</a>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-lg-3">
                <div class="card text-center shadow-sm h-100">
                    <div class="card-body">
                        <div class="display-6 fw-bold text-info"><?= $stats['redis_sessions'] ?></div>
                        <div class="text-muted small mt-1">Активных сессий</div>
                        <div class="text-muted small">(Redis session:*)</div>
                    </div>
                    <div class="card-footer">
                        <a href="/admin/system_stats.php" class="btn btn-outline-info btn-sm w-100">Статистика</a>
                    </div>
                </div>
            </div>
        </div>

        <h5 class="mb-3">Быстрые ссылки</h5>
        <div class="d-flex flex-wrap gap-2">
            <a href="/admin/users.php"       class="btn btn-outline-primary">👥 Пользователи</a>
            <a href="/admin/recipes.php"     class="btn btn-outline-success">📋 Рецепты</a>
            <a href="/admin/categories.php"  class="btn btn-outline-warning">🗂 Категории</a>
            <a href="/admin/tags.php"        class="btn btn-outline-secondary">🏷 Теги</a>
            <a href="/admin/system_stats.php" class="btn btn-outline-info">📈 Статистика</a>
            <a href="/admin/chaos.php"       class="btn btn-outline-danger">⚡ Chaos Panel</a>
        </div>

    </div>
</div>

<?php require dirname(__DIR__, 2) . '/templates/footer.php'; ?>
