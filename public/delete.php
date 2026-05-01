<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap.php';

use App\Storage\MySQLRecipeStorage;
use App\Support\Csrf;
use App\Support\Flash;

$auth->requireAuth('/login.php');
$currentUser = $auth->currentUser();

$storage = new MySQLRecipeStorage($pdo);

// POST — perform deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Csrf::verify($_POST['_token'] ?? '')) {
        Flash::error('Неверный CSRF-токен.');
        header('Location: /index.php');
        exit;
    }

    $id     = (int)($_POST['id'] ?? 0);
    $recipe = $storage->getById($id);

    if (!$recipe) {
        Flash::error('Рецепт не найден.');
        header('Location: /index.php');
        exit;
    }

    if ($recipe->user_id !== $currentUser->id || $recipe->user_id === 0) {
        http_response_code(403);
        Flash::error('Доступ запрещён.');
        header('Location: /index.php');
        exit;
    }

    $storage->delete($id);
    Flash::success('Рецепт «' . $recipe->title . '» удалён.');
    header('Location: /index.php');
    exit;
}

// GET — show confirmation page
$id     = (int)($_GET['id'] ?? 0);
$recipe = $storage->getById($id);

if (!$recipe) {
    http_response_code(404);
    $pageTitle = 'Не найдено';
    require dirname(__DIR__) . '/templates/header.php';
    echo '<div class="alert alert-warning">Рецепт не найден. <a href="/index.php">Назад</a></div>';
    require dirname(__DIR__) . '/templates/footer.php';
    exit;
}

if ($recipe->user_id !== $currentUser->id || $recipe->user_id === 0) {
    http_response_code(403);
    $pageTitle = 'Доступ запрещён';
    require dirname(__DIR__) . '/templates/header.php';
    echo '<div class="alert alert-danger">Нет прав для удаления.</div>';
    require dirname(__DIR__) . '/templates/footer.php';
    exit;
}

$pageTitle = 'Удалить: ' . $recipe->title;
require dirname(__DIR__) . '/templates/header.php';
?>

<div class="row justify-content-center">
    <div class="col-md-6">
        <div class="card border-danger shadow-sm">
            <div class="card-body text-center p-5">
                <h4 class="text-danger mb-3">Удалить рецепт?</h4>
                <p class="mb-1 fs-5"><strong><?= htmlspecialchars($recipe->title) ?></strong></p>
                <p class="text-muted mb-4">Это действие необратимо.</p>
                <div class="d-flex justify-content-center gap-3">
                    <form method="POST" action="/delete.php">
                        <?= Csrf::field() ?>
                        <input type="hidden" name="id" value="<?= $recipe->id ?>">
                        <button type="submit" class="btn btn-danger">Да, удалить</button>
                    </form>
                    <a href="/view.php?id=<?= $recipe->id ?>" class="btn btn-outline-secondary">Отмена</a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require dirname(__DIR__) . '/templates/footer.php'; ?>
