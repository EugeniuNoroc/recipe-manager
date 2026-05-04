<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/bootstrap.php';

use App\Services\AdminService;
use App\Support\AdminGuard;
use App\Support\Csrf;
use App\Support\Flash;
use App\Validators\UserValidator;

$adminUser   = AdminGuard::check();
$currentUser = $adminUser;

$adminService = new AdminService($pdo, $redis);

// POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Csrf::verify($_POST['_token'] ?? '')) {
        Flash::error('Неверный CSRF-токен.');
        header('Location: /admin/users.php');
        exit;
    }

    $action = $_POST['action'] ?? '';

    if ($action === 'toggle_role') {
        $uid = (int)($_POST['user_id'] ?? 0);
        if ($uid === $adminUser->id) {
            Flash::error('Нельзя изменить собственную роль.');
        } elseif ($adminService->getAdminCount() <= 1) {
            $stmt = $pdo->prepare('SELECT role FROM users WHERE id = ?');
            $stmt->execute([$uid]);
            $row = $stmt->fetch();
            if ($row && $row['role'] === 'admin') {
                Flash::error('Нельзя снять права у последнего администратора.');
            } else {
                $adminService->toggleUserRole($uid);
                Flash::success('Роль изменена.');
            }
        } else {
            $adminService->toggleUserRole($uid);
            Flash::success('Роль изменена.');
        }
    } elseif ($action === 'toggle_block') {
        $uid = (int)($_POST['user_id'] ?? 0);
        if ($uid === $adminUser->id) {
            Flash::error('Нельзя заблокировать собственный аккаунт.');
        } else {
            $adminService->toggleUserBlock($uid);
            Flash::success('Статус блокировки изменён.');
        }
    } elseif ($action === 'delete_user') {
        $uid = (int)($_POST['user_id'] ?? 0);
        $err = $adminService->deleteUser($uid, $adminUser->id);
        if ($err) {
            Flash::error($err);
        } else {
            Flash::success('Пользователь удалён.');
        }
    } elseif ($action === 'create_user') {
        $data = [
            'username' => trim($_POST['username'] ?? ''),
            'email'    => trim($_POST['email']    ?? ''),
            'password' => $_POST['password'] ?? '',
        ];
        $role      = in_array($_POST['role'] ?? '', ['user', 'admin'], true) ? $_POST['role'] : 'user';
        $validator = new UserValidator();
        if (!$validator->validate($data)) {
            foreach ($validator->getErrors() as $err) {
                Flash::error($err);
            }
        } else {
            try {
                $adminService->createUser($data['username'], $data['email'], $data['password'], $role);
                Flash::success('Пользователь «' . htmlspecialchars($data['username']) . '» создан.');
            } catch (\PDOException $e) {
                Flash::error('Ошибка: пользователь с таким именем или email уже существует.');
            }
        }
    }

    header('Location: /admin/users.php');
    exit;
}

$users     = $adminService->getAllUsers();
$pageTitle = 'Админ-панель — Пользователи';
require dirname(__DIR__, 2) . '/templates/header.php';
?>

<div class="d-flex gap-4 align-items-start">
    <?php require dirname(__DIR__, 2) . '/templates/admin_layout.php'; ?>
    <div class="flex-grow-1">

        <h2 class="mb-4">👥 Управление пользователями</h2>

        <div class="table-responsive mb-5">
            <table class="table table-hover table-sm align-middle">
                <thead class="table-dark">
                <tr>
                    <th>ID</th><th>Username</th><th>Email</th><th>Роль</th>
                    <th>Блок.</th><th>Рецептов</th><th>Регистрация</th><th>Действия</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($users as $u): ?>
                <tr class="<?= $u['is_blocked'] ? 'table-danger' : '' ?>">
                    <td><?= $u['id'] ?></td>
                    <td><?= htmlspecialchars($u['username']) ?></td>
                    <td><small><?= htmlspecialchars($u['email']) ?></small></td>
                    <td>
                        <span class="badge <?= $u['role'] === 'admin' ? 'bg-danger' : 'bg-secondary' ?>">
                            <?= $u['role'] ?>
                        </span>
                    </td>
                    <td><?= $u['is_blocked'] ? '🔒' : '—' ?></td>
                    <td><?= $u['recipe_count'] ?></td>
                    <td><small><?= substr($u['created_at'], 0, 10) ?></small></td>
                    <td>
                        <div class="d-flex gap-1 flex-wrap">
                            <?php if ((int)$u['id'] !== $adminUser->id): ?>
                            <form method="POST">
                                <?= Csrf::field() ?>
                                <input type="hidden" name="action" value="toggle_role">
                                <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                <button class="btn btn-outline-warning btn-sm" title="Переключить роль">
                                    <?= $u['role'] === 'admin' ? 'Снять admin' : 'Сделать admin' ?>
                                </button>
                            </form>
                            <form method="POST">
                                <?= Csrf::field() ?>
                                <input type="hidden" name="action" value="toggle_block">
                                <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                <button class="btn btn-outline-<?= $u['is_blocked'] ? 'success' : 'secondary' ?> btn-sm">
                                    <?= $u['is_blocked'] ? 'Разблокировать' : 'Заблокировать' ?>
                                </button>
                            </form>
                            <form method="POST" onsubmit="return confirm('Удалить пользователя?')">
                                <?= Csrf::field() ?>
                                <input type="hidden" name="action" value="delete_user">
                                <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                <button class="btn btn-outline-danger btn-sm">Удалить</button>
                            </form>
                            <?php else: ?>
                            <span class="text-muted small">это вы</span>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <h5 class="mb-3">Создать пользователя</h5>
        <div class="card shadow-sm">
            <div class="card-body">
                <form method="POST" class="row g-3">
                    <?= Csrf::field() ?>
                    <input type="hidden" name="action" value="create_user">
                    <div class="col-md-3">
                        <label class="form-label">Username</label>
                        <input type="text" name="username" class="form-control" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Email</label>
                        <input type="email" name="email" class="form-control" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Пароль</label>
                        <input type="password" name="password" class="form-control" required minlength="6">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Роль</label>
                        <select name="role" class="form-select">
                            <option value="user">user</option>
                            <option value="admin">admin</option>
                        </select>
                    </div>
                    <div class="col-md-1 d-flex align-items-end">
                        <button class="btn btn-primary w-100">Создать</button>
                    </div>
                </form>
            </div>
        </div>

    </div>
</div>

<?php require dirname(__DIR__, 2) . '/templates/footer.php'; ?>
