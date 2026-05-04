<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/bootstrap.php';

use App\Services\AdminService;
use App\Support\AdminGuard;
use App\Support\Csrf;
use App\Support\Flash;

$adminUser   = AdminGuard::check();
$currentUser = $adminUser;

$adminService = new AdminService($pdo, $redis);

// POST — delete recipe
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Csrf::verify($_POST['_token'] ?? '')) {
        Flash::error('Неверный CSRF-токен.');
        header('Location: /admin/recipes.php');
        exit;
    }
    if (($_POST['action'] ?? '') === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        $adminService->deleteRecipe($id);
        Flash::success('Рецепт удалён.');
    }
    header('Location: /admin/recipes.php?' . http_build_query([
        'author_id'   => $_POST['filter_author']   ?? '',
        'category_id' => $_POST['filter_category'] ?? '',
    ]));
    exit;
}

// Filters from GET
$filterAuthor   = (int)($_GET['author_id']   ?? 0);
$filterCategory = (int)($_GET['category_id'] ?? 0);

$recipes    = $adminService->getAllRecipes($filterAuthor, $filterCategory);
$authors    = $adminService->getAuthors();
$categories = $pdo->query('SELECT id, name FROM categories ORDER BY name')->fetchAll();

$pageTitle = 'Админ-панель — Рецепты';
require dirname(__DIR__, 2) . '/templates/header.php';
?>

<div class="d-flex gap-4 align-items-start">
    <?php require dirname(__DIR__, 2) . '/templates/admin_layout.php'; ?>
    <div class="flex-grow-1">

        <h2 class="mb-3">📋 Управление рецептами</h2>

        <!-- Filters -->
        <form method="GET" class="row g-2 mb-3">
            <div class="col-auto">
                <select name="author_id" class="form-select form-select-sm">
                    <option value="0">Все авторы</option>
                    <?php foreach ($authors as $a): ?>
                        <option value="<?= $a['id'] ?>" <?= $filterAuthor === (int)$a['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($a['username']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-auto">
                <select name="category_id" class="form-select form-select-sm">
                    <option value="0">Все категории</option>
                    <?php foreach ($categories as $c): ?>
                        <option value="<?= $c['id'] ?>" <?= $filterCategory === (int)$c['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($c['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-auto">
                <button class="btn btn-outline-secondary btn-sm">Фильтр</button>
                <a href="/admin/recipes.php" class="btn btn-link btn-sm">Сброс</a>
            </div>
        </form>

        <div class="table-responsive">
            <table class="table table-hover table-sm align-middle">
                <thead class="table-dark">
                <tr>
                    <th>ID</th><th>Название</th><th>Автор</th><th>Категория</th>
                    <th>Дата</th><th>Действия</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($recipes as $r): ?>
                <tr>
                    <td><?= $r['id'] ?></td>
                    <td><?= htmlspecialchars($r['title']) ?></td>
                    <td><?= htmlspecialchars($r['username']) ?></td>
                    <td><?= htmlspecialchars($r['category'] ?? '—') ?></td>
                    <td><small><?= substr($r['created_at'], 0, 10) ?></small></td>
                    <td>
                        <div class="d-flex gap-1">
                            <a href="/edit.php?id=<?= $r['id'] ?>" class="btn btn-outline-primary btn-sm">
                                Редактировать
                            </a>
                            <form method="POST" onsubmit="return confirm('Удалить рецепт?')">
                                <?= Csrf::field() ?>
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?= $r['id'] ?>">
                                <input type="hidden" name="filter_author"   value="<?= $filterAuthor ?>">
                                <input type="hidden" name="filter_category" value="<?= $filterCategory ?>">
                                <button class="btn btn-outline-danger btn-sm">Удалить</button>
                            </form>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($recipes)): ?>
                <tr><td colspan="6" class="text-center text-muted">Рецепты не найдены</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>

    </div>
</div>

<?php require dirname(__DIR__, 2) . '/templates/footer.php'; ?>
