<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap.php';

use App\Support\Csrf;
use App\Support\Flash;

$currentUser = $auth->currentUser();
if ($currentUser) {
    header('Location: /index.php');
    exit;
}

$errors = [];
$email  = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Csrf::verify($_POST['_token'] ?? '')) {
        Flash::error('Неверный CSRF-токен.');
        header('Location: /login.php');
        exit;
    }

    $email    = trim($_POST['email']    ?? '');
    $password = $_POST['password']      ?? '';

    if (empty($email) || empty($password)) {
        $errors['form'] = 'Введите email и пароль.';
    } else {
        $user = $auth->login($email, $password);
        if ($user) {
            $auth->loginUser($user);
            Flash::success('Добро пожаловать, ' . $user->username . '!');
            header('Location: /index.php');
            exit;
        } else {
            $errors['form'] = 'Неверный email или пароль.';
        }
    }
}

$pageTitle = 'Вход';
require dirname(__DIR__) . '/templates/header.php';
?>

<div class="row justify-content-center">
    <div class="col-md-5">
        <div class="card shadow-sm">
            <div class="card-body p-4">
                <h4 class="card-title mb-4">Вход</h4>

                <?php if (isset($errors['form'])): ?>
                    <div class="alert alert-danger"><?= htmlspecialchars($errors['form']) ?></div>
                <?php endif; ?>

                <form method="POST" action="/login.php" novalidate>
                    <?= Csrf::field() ?>

                    <div class="mb-3">
                        <label class="form-label">Email</label>
                        <input type="email" name="email"
                               class="form-control"
                               value="<?= htmlspecialchars($email) ?>" required>
                    </div>

                    <div class="mb-4">
                        <label class="form-label">Пароль</label>
                        <input type="password" name="password" class="form-control" required>
                    </div>

                    <button type="submit" class="btn btn-primary w-100">Войти</button>
                </form>
                <p class="text-center mt-3 mb-0">
                    Нет аккаунта? <a href="/register.php">Зарегистрироваться</a>
                </p>
            </div>
        </div>
    </div>
</div>

<?php require dirname(__DIR__) . '/templates/footer.php'; ?>
