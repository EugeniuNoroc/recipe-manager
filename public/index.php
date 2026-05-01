<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap.php';

use App\Storage\MySQLRecipeStorage;

$storage     = new MySQLRecipeStorage($pdo);
$currentUser = $auth->currentUser();

// Category filter uses integer ID; search is a string
$categoryId = (int)($_GET['category'] ?? 0);
$search     = trim($_GET['search'] ?? '');

$recipes    = $storage->getFiltered($categoryId, $search);
$categories = $storage->getCategories();

// Batch-fetch view counts for all recipes in one MGET
$recipeIds  = array_map(fn($r) => $r->id, $recipes);
$viewCounts = $stats->getViewsForMany($recipeIds);

$pageTitle = 'Все рецепты';
require dirname(__DIR__) . '/templates/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h2 class="h4 mb-0">Все рецепты
        <span class="badge bg-secondary ms-1"><?= count($recipes) ?></span>
    </h2>
    <?php if ($currentUser): ?>
        <a href="/create.php" class="btn btn-primary btn-sm">+ Создать рецепт</a>
    <?php endif; ?>
</div>

<form method="GET" action="/index.php" class="row g-2 mb-4">
    <div class="col-sm-6 col-md-5">
        <input type="search" name="search" class="form-control form-control-sm"
               placeholder="Поиск по названию или ингредиентам…"
               value="<?= htmlspecialchars($search) ?>">
    </div>
    <div class="col-sm-4 col-md-3">
        <select name="category" class="form-select form-select-sm">
            <option value="0">Все категории</option>
            <?php foreach ($categories as $cat): ?>
                <option value="<?= (int)$cat['id'] ?>"
                        <?= $categoryId === (int)$cat['id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($cat['name']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-auto">
        <button type="submit" class="btn btn-outline-secondary btn-sm">Найти</button>
        <?php if ($categoryId || $search): ?>
            <a href="/index.php" class="btn btn-outline-danger btn-sm">✕</a>
        <?php endif; ?>
    </div>
</form>

<?php if (empty($recipes)): ?>
    <div class="text-center py-5 text-muted">
        <p class="fs-5">Рецептов не найдено.</p>
        <?php if ($currentUser): ?>
            <a href="/create.php" class="btn btn-primary">Создать первый</a>
        <?php endif; ?>
    </div>
<?php else: ?>
    <div class="row row-cols-1 row-cols-md-2 row-cols-xl-3 g-4">
        <?php foreach ($recipes as $r): ?>
            <?php $views = $viewCounts[$r->id] ?? 0; ?>
            <div class="col">
                <div class="card h-100 shadow-sm">
                    <div class="card-body d-flex flex-column">
                        <h5 class="card-title">
                            <a href="/view.php?id=<?= $r->id ?>"
                               class="text-decoration-none text-dark stretched-link">
                                <?= htmlspecialchars($r->title) ?>
                            </a>
                        </h5>
                        <p class="card-text text-muted small mb-1">
                            <?= htmlspecialchars($r->author) ?> &bull; <?= $r->prep_time ?> мин
                        </p>
                        <p class="card-text text-muted small mb-3">
                            <?= htmlspecialchars($r->created_at) ?>
                        </p>
                        <div class="mt-auto d-flex flex-wrap gap-1 align-items-center">
                            <span class="badge badge-cat text-white"><?= htmlspecialchars($r->category) ?></span>
                            <?php
                            $dc = match($r->difficulty) {
                                'Легко'  => 'badge-easy',
                                'Сложно' => 'badge-hard',
                                default  => 'badge-med',
                            };
                            ?>
                            <span class="badge <?= $dc ?> text-white"><?= htmlspecialchars($r->difficulty) ?></span>
                            <?php foreach ($r->tags as $tag): ?>
                                <span class="badge bg-secondary"><?= htmlspecialchars($tag) ?></span>
                            <?php endforeach; ?>
                            <?php if ($views > 0): ?>
                                <span class="badge bg-light text-muted border views-badge ms-auto">
                                    👁 <?= $views ?>
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php require dirname(__DIR__) . '/templates/footer.php'; ?>
