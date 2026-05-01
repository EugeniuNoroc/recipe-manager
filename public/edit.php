<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap.php';

use App\Storage\MySQLRecipeStorage;

$auth->requireAuth('/login.php');
$currentUser = $auth->currentUser();

$storage = new MySQLRecipeStorage($pdo);
$id      = (int)($_GET['id'] ?? 0);
$recipe  = $storage->getById($id);

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
    echo '<div class="alert alert-danger">У вас нет прав редактировать этот рецепт.</div>';
    require dirname(__DIR__) . '/templates/footer.php';
    exit;
}

$categories  = $storage->getCategories();
$tags        = $storage->getTags();
$errors      = [];
$pageTitle   = 'Редактировать: ' . $recipe->title;
$formAction  = '/update.php';
$submitLabel = 'Обновить рецепт';

require dirname(__DIR__) . '/templates/header.php';
?>

<div class="mb-3">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="/index.php">Все рецепты</a></li>
            <li class="breadcrumb-item"><a href="/view.php?id=<?= $recipe->id ?>"><?= htmlspecialchars($recipe->title) ?></a></li>
            <li class="breadcrumb-item active">Редактировать</li>
        </ol>
    </nav>
</div>

<div class="card shadow-sm">
    <div class="card-body p-4">
        <h2 class="h4 mb-4">Редактировать рецепт</h2>
        <?php require dirname(__DIR__) . '/templates/recipe_form.php'; ?>
    </div>
</div>

<?php require dirname(__DIR__) . '/templates/footer.php'; ?>
