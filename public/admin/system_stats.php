<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/bootstrap.php';

use App\Services\AdminService;
use App\Support\AdminGuard;

$adminUser   = AdminGuard::check();
$currentUser = $adminUser;

$adminService = new AdminService($pdo, $redis);
$sysStats     = $adminService->getSystemStats();

$pageTitle = 'Админ-панель — Статистика системы';
require dirname(__DIR__, 2) . '/templates/header.php';
?>

<div class="d-flex gap-4 align-items-start">
    <?php require dirname(__DIR__, 2) . '/templates/admin_layout.php'; ?>
    <div class="flex-grow-1">

        <h2 class="mb-4">📈 Статистика системы</h2>

        <div class="row g-4">

            <!-- Top authors -->
            <div class="col-md-6">
                <div class="card shadow-sm h-100">
                    <div class="card-header fw-semibold">Топ-10 авторов по рецептам</div>
                    <div class="card-body p-0">
                        <table class="table table-sm mb-0">
                            <thead class="table-light">
                            <tr><th>#</th><th>Автор</th><th>Рецептов</th></tr>
                            </thead>
                            <tbody>
                            <?php foreach ($sysStats['top_authors'] as $i => $a): ?>
                            <tr>
                                <td><?= $i + 1 ?></td>
                                <td><?= htmlspecialchars($a['username']) ?></td>
                                <td><?= $a['recipe_count'] ?></td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if (empty($sysStats['top_authors'])): ?>
                            <tr><td colspan="3" class="text-muted text-center">Нет данных</td></tr>
                            <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Top recipes by views -->
            <div class="col-md-6">
                <div class="card shadow-sm h-100">
                    <div class="card-header fw-semibold">Топ-10 рецептов по просмотрам (Redis)</div>
                    <div class="card-body p-0">
                        <table class="table table-sm mb-0">
                            <thead class="table-light">
                            <tr><th>#</th><th>Рецепт</th><th>Автор</th><th>Просмотры</th></tr>
                            </thead>
                            <tbody>
                            <?php foreach ($sysStats['top_recipes_views'] as $i => $r): ?>
                            <tr>
                                <td><?= $i + 1 ?></td>
                                <td>
                                    <a href="/view.php?id=<?= $r['id'] ?>">
                                        <?= htmlspecialchars($r['title']) ?>
                                    </a>
                                </td>
                                <td><?= htmlspecialchars($r['author']) ?></td>
                                <td><?= $r['views'] ?></td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if (empty($sysStats['top_recipes_views'])): ?>
                            <tr><td colspan="4" class="text-muted text-center">Нет данных (Redis пуст)</td></tr>
                            <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Distribution by category -->
            <div class="col-md-6">
                <div class="card shadow-sm h-100">
                    <div class="card-header fw-semibold">Распределение по категориям</div>
                    <div class="card-body p-0">
                        <table class="table table-sm mb-0">
                            <thead class="table-light">
                            <tr><th>Категория</th><th>Рецептов</th><th>%</th></tr>
                            </thead>
                            <tbody>
                            <?php
                            $totalCatRec = array_sum(array_column($sysStats['by_category'], 'recipe_count')) ?: 1;
                            foreach ($sysStats['by_category'] as $c):
                                $pct = round($c['recipe_count'] / $totalCatRec * 100, 1);
                            ?>
                            <tr>
                                <td><?= htmlspecialchars($c['name']) ?></td>
                                <td><?= $c['recipe_count'] ?></td>
                                <td>
                                    <div class="progress" style="height:14px;min-width:80px">
                                        <div class="progress-bar" style="width:<?= $pct ?>%"><?= $pct ?>%</div>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Redis keys -->
            <div class="col-md-6">
                <div class="card shadow-sm h-100">
                    <div class="card-header fw-semibold">Redis-ключи (первые 100)</div>
                    <div class="card-body p-0" style="max-height:360px;overflow-y:auto">
                        <table class="table table-sm table-striped mb-0">
                            <thead class="table-light">
                            <tr><th>Ключ</th><th>Тип</th></tr>
                            </thead>
                            <tbody>
                            <?php foreach ($sysStats['redis_keys'] as $k): ?>
                            <tr>
                                <td><code class="small"><?= htmlspecialchars((string)$k['key']) ?></code></td>
                                <td>
                                    <span class="badge bg-secondary">
                                        <?= is_object($k['type']) ? $k['type']->getPayload() : htmlspecialchars((string)$k['type']) ?>
                                    </span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if (empty($sysStats['redis_keys'])): ?>
                            <tr><td colspan="2" class="text-muted text-center">Redis пуст или недоступен</td></tr>
                            <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

        </div>

    </div>
</div>

<?php require dirname(__DIR__, 2) . '/templates/footer.php'; ?>
