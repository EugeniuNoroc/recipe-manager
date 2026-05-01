<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap.php';

use App\Storage\MySQLRecipeStorage;

$currentUser = $auth->currentUser();
$storage     = new MySQLRecipeStorage($pdo);

$topViews = $stats->getTopPopular(10);

// Fetch recipe details for all IDs that have view data
$topRecipes = [];
if (!empty($topViews)) {
    $byId = [];
    foreach ($storage->findByIds(array_keys($topViews)) as $r) {
        $byId[$r->id] = $r;
    }
    // Keep the sorted order from topViews
    foreach ($topViews as $recipeId => $viewCount) {
        if (isset($byId[$recipeId])) {
            $topRecipes[] = ['recipe' => $byId[$recipeId], 'views' => $viewCount];
        }
    }
}

$pageTitle = 'Статистика просмотров';
require dirname(__DIR__) . '/templates/header.php';
?>

<h2 class="h4 mb-4">Топ-10 рецептов по просмотрам</h2>

<?php if (empty($topRecipes)): ?>
    <div class="text-center py-5 text-muted">
        <p class="fs-5">Пока нет данных.</p>
        <p>Просмотрите несколько рецептов, чтобы здесь появилась статистика.</p>
        <a href="/index.php" class="btn btn-outline-primary">К рецептам</a>
    </div>
<?php else: ?>
    <div class="card shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th style="width:3rem">#</th>
                        <th>Название</th>
                        <th>Автор</th>
                        <th>Категория</th>
                        <th class="text-end">👁 Просмотры</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($topRecipes as $rank => $entry): ?>
                        <?php $r = $entry['recipe']; ?>
                        <tr>
                            <td class="text-muted fw-bold"><?= $rank + 1 ?></td>
                            <td>
                                <a href="/view.php?id=<?= $r->id ?>" class="text-decoration-none">
                                    <?= htmlspecialchars($r->title) ?>
                                </a>
                            </td>
                            <td><?= htmlspecialchars($r->author) ?></td>
                            <td>
                                <span class="badge badge-cat text-white">
                                    <?= htmlspecialchars($r->category) ?>
                                </span>
                            </td>
                            <td class="text-end fw-bold"><?= $entry['views'] ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>

<?php require dirname(__DIR__) . '/templates/footer.php'; ?>
