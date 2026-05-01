<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap.php';

use App\Storage\MySQLRecipeStorage;
use App\Support\Csrf;

$storage     = new MySQLRecipeStorage($pdo);
$currentUser = $auth->currentUser();

$id     = (int)($_GET['id'] ?? 0);
$recipe = $storage->getById($id);

if (!$recipe) {
    http_response_code(404);
    $pageTitle = 'Рецепт не найден';
    require dirname(__DIR__) . '/templates/header.php';
    echo '<div class="alert alert-warning">Рецепт #' . $id . ' не найден. <a href="/index.php">Назад</a></div>';
    require dirname(__DIR__) . '/templates/footer.php';
    exit;
}

// Count the view (before rendering so the number on the page is up to date)
$stats->incrementView($id);
$views = $stats->getViews($id);

$isOwner    = $currentUser && $currentUser->id === $recipe->user_id && $recipe->user_id !== 0;
$isFavorite = $currentUser ? $favorites->isFavorite($currentUser->id, $id) : false;

$pageTitle = $recipe->title;
require dirname(__DIR__) . '/templates/header.php';

$diffClass = match($recipe->difficulty) {
    'Легко'  => 'badge-easy',
    'Сложно' => 'badge-hard',
    default  => 'badge-med',
};
?>

<div class="d-flex justify-content-between align-items-start mb-3">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb mb-0">
            <li class="breadcrumb-item"><a href="/index.php">Все рецепты</a></li>
            <li class="breadcrumb-item active"><?= htmlspecialchars($recipe->title) ?></li>
        </ol>
    </nav>
    <div class="d-flex gap-2 flex-wrap justify-content-end">
        <?php if ($currentUser): ?>
            <form method="POST" action="/favorite_toggle.php">
                <?= Csrf::field() ?>
                <input type="hidden" name="recipe_id" value="<?= $recipe->id ?>">
                <button type="submit"
                        class="btn btn-sm <?= $isFavorite ? 'btn-warning' : 'btn-outline-warning' ?>">
                    <?= $isFavorite ? '♥ В избранном' : '♡ В избранное' ?>
                </button>
            </form>
        <?php endif; ?>
        <?php if ($isOwner): ?>
            <a href="/edit.php?id=<?= $recipe->id ?>" class="btn btn-sm btn-outline-primary">Редактировать</a>
            <form method="POST" action="/delete.php" id="deleteForm">
                <?= Csrf::field() ?>
                <input type="hidden" name="id" value="<?= $recipe->id ?>">
                <button type="button" class="btn btn-sm btn-outline-danger"
                        onclick="if(confirm('Удалить рецепт?')) document.getElementById('deleteForm').submit()">
                    Удалить
                </button>
            </form>
        <?php endif; ?>
    </div>
</div>

<div class="card shadow-sm mb-4">
    <div class="card-body">
        <h1 class="h3 mb-1"><?= htmlspecialchars($recipe->title) ?></h1>
        <p class="text-muted mb-3">
            Автор: <strong><?= htmlspecialchars($recipe->author) ?></strong> &bull;
            Создан: <?= htmlspecialchars($recipe->created_at) ?>
        </p>

        <div class="d-flex flex-wrap gap-2 mb-4 align-items-center">
            <span class="badge badge-cat text-white fs-6"><?= htmlspecialchars($recipe->category) ?></span>
            <span class="badge <?= $diffClass ?> text-white fs-6"><?= htmlspecialchars($recipe->difficulty) ?></span>
            <span class="badge bg-info text-dark fs-6">⏱ <?= $recipe->prep_time ?> мин</span>
            <?php foreach ($recipe->tags as $tag): ?>
                <span class="badge bg-secondary fs-6"><?= htmlspecialchars($tag) ?></span>
            <?php endforeach; ?>
            <span class="badge bg-light text-muted border ms-auto">👁 <?= $views ?></span>
        </div>

        <h5 class="mt-3">Ингредиенты</h5>
        <div class="card bg-light p-3 mb-4">
            <?= nl2br(htmlspecialchars($recipe->ingredients)) ?>
        </div>

        <h5>Приготовление</h5>
        <div class="card bg-light p-3">
            <?= nl2br(htmlspecialchars($recipe->instructions)) ?>
        </div>
    </div>
</div>

<a href="/index.php" class="btn btn-outline-secondary btn-sm">← Назад к списку</a>

<?php require dirname(__DIR__) . '/templates/footer.php'; ?>
