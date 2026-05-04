<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap.php';

use App\Support\Csrf;
use App\Support\Flash;
use App\Validators\UserValidator;

$currentUser = $auth->currentUser();
if ($currentUser) {
    header('Location: /index.php');
    exit;
}

$errors = [];
$old    = ['username' => '', 'email' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Csrf::verify($_POST['_token'] ?? '')) {
        Flash::error('Неверный CSRF-токен. Попробуйте снова.');
        header('Location: /register.php');
        exit;
    }

    $data = [
        'username' => trim($_POST['username'] ?? ''),
        'email'    => trim($_POST['email']    ?? ''),
        'password' => $_POST['password']      ?? '',
    ];
    $old = $data;

    $validator = new UserValidator();
    if (!$validator->validate($data)) {
        $errors = $validator->getErrors();
    } else {
        try {
            $user = $auth->register($data['username'], $data['email'], $data['password']);
            $auth->loginUser($user);
            Flash::success('Добро пожаловать, ' . $user->username . '!');
            header('Location: /index.php');
            exit;
        } catch (\PDOException $e) {
            if (str_contains($e->getMessage(), 'Duplicate')) {
                $errors['email'] = 'Этот email или логин уже занят.';
            } else {
                $errors['email'] = 'Ошибка при регистрации. Попробуйте позже.';
            }
        }
    }
}

$pageTitle = 'Регистрация';
require dirname(__DIR__) . '/templates/header.php';
?>

<div class="row justify-content-center">
    <div class="col-md-5">
        <div class="card shadow-sm">
            <div class="card-body p-4">
                <h4 class="card-title mb-4">Регистрация</h4>
                <form method="POST" action="/register.php" novalidate>
                    <?= Csrf::field() ?>

                    <div class="mb-3">
                        <label class="form-label">Логин</label>
                        <input type="text" name="username"
                               class="form-control <?= isset($errors['username']) ? 'is-invalid' : '' ?>"
                               value="<?= htmlspecialchars($old['username']) ?>"
                               required minlength="3" maxlength="30"
                               pattern="[a-zA-Z0-9_]+"
                               title="Только буквы, цифры и _">
                        <div class="invalid-feedback">
                            <?= isset($errors['username']) ? htmlspecialchars($errors['username']) : 'Только буквы, цифры и _, от 3 до 30 символов' ?>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Email</label>
                        <input type="email" name="email"
                               class="form-control <?= isset($errors['email']) ? 'is-invalid' : '' ?>"
                               value="<?= htmlspecialchars($old['email']) ?>"
                               required>
                        <div class="invalid-feedback">
                            <?= isset($errors['email']) ? htmlspecialchars($errors['email']) : 'Введите корректный email-адрес' ?>
                        </div>
                    </div>

                    <div class="mb-4">
                        <label class="form-label">Пароль</label>
                        <input type="password" name="password"
                               class="form-control <?= isset($errors['password']) ? 'is-invalid' : '' ?>"
                               required minlength="6">
                        <div class="invalid-feedback">
                            <?= isset($errors['password']) ? htmlspecialchars($errors['password']) : 'Минимум 6 символов' ?>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary w-100">Зарегистрироваться</button>
                </form>
                <p class="text-center mt-3 mb-0">
                    Уже есть аккаунт? <a href="/login.php">Войти</a>
                </p>
            </div>
        </div>
    </div>
</div>

<?php require dirname(__DIR__) . '/templates/footer.php'; ?>
