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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Csrf::verify($_POST['_token'] ?? '')) {
        Flash::error('Неверный CSRF-токен.');
        header('Location: /admin/categories.php');
        exit;
    }

    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $name = trim($_POST['name'] ?? '');
        if ($name === '') {
            Flash::error('Название не может быть пустым.');
        } else {
            try {
                $adminService->createCategory($name);
                Flash::success('Категория «' . htmlspecialchars($name) . '» создана.');
            } catch (\PDOException $e) {
                Flash::error('Категория с таким названием уже существует.');
            }
        }
    } elseif ($action === 'rename') {
        $id   = (int)($_POST['id']   ?? 0);
        $name = trim($_POST['name'] ?? '');
        if ($name === '') {
            Flash::error('Название не может быть пустым.');
        } else {
            try {
                $adminService->renameCategory($id, $name);
                Flash::success('Категория переименована.');
            } catch (\PDOException $e) {
                Flash::error('Категория с таким названием уже существует.');
            }
        }
    } elseif ($action === 'delete') {
        $id  = (int)($_POST['id'] ?? 0);
        $err = $adminService->deleteCategory($id);
        if ($err) {
            Flash::error($err);
        } else {
            Flash::success('Категория удалена.');
        }
    }

    header('Location: /admin/categories.php');
    exit;
}

$categories = $adminService->getCategoriesWithCount();
$pageTitle  = 'Админ-панель — Категории';
require dirname(__DIR__, 2) . '/templates/header.php';
?>

<div class="d-flex gap-4 align-items-start">
    <?php require dirname(__DIR__, 2) . '/templates/admin_layout.php'; ?>
    <div class="flex-grow-1">

        <h2 class="mb-4">🗂 Управление категориями</h2>

        <div class="row g-4">
            <div class="col-md-7">
                <table class="table table-hover table-sm align-middle shadow-sm">
                    <thead class="table-dark">
                    <tr><th>ID</th><th>Название</th><th>Рецептов</th><th>Действия</th></tr>
                    </thead>
                    <tbody>
                    <?php foreach ($categories as $cat): ?>
                    <tr>
                        <td><?= $cat['id'] ?></td>
                        <td>
                            <form method="POST" class="d-flex gap-2 align-items-center">
                                <?= Csrf::field() ?>
                                <input type="hidden" name="action" value="rename">
                                <input type="hidden" name="id" value="<?= $cat['id'] ?>">
                                <input type="text" name="name" value="<?= htmlspecialchars($cat['name']) ?>"
                                       class="form-control form-control-sm" style="max-width:160px" required>
                                <button class="btn btn-outline-primary btn-sm">Сохранить</button>
                            </form>
                        </td>
                        <td><?= $cat['recipe_count'] ?></td>
                        <td>
                            <form method="POST" onsubmit="return confirm('Удалить категорию?')">
                                <?= Csrf::field() ?>
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?= $cat['id'] ?>">
                                <button class="btn btn-outline-danger btn-sm">Удалить</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="col-md-5">
                <div class="card shadow-sm">
                    <div class="card-header fw-semibold">Создать категорию</div>
                    <div class="card-body">
                        <form method="POST">
                            <?= Csrf::field() ?>
                            <input type="hidden" name="action" value="create">
                            <div class="mb-3">
                                <label class="form-label">Название</label>
                                <input type="text" name="name" class="form-control" required>
                            </div>
                            <button class="btn btn-primary w-100">Создать</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>

<?php require dirname(__DIR__, 2) . '/templates/footer.php'; ?>
