<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap.php';

use App\Storage\MySQLRecipeStorage;

$auth->requireAuth('/login.php');
$currentUser = $auth->currentUser();

$storage = new MySQLRecipeStorage($pdo);

$ids        = $favorites->getIds($currentUser->id);
$recipes    = $storage->findByIds($ids);
$viewCounts = $stats->getViewsForMany($ids);

$pageTitle = 'Избранное';
require dirname(__DIR__) . '/templates/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="h4 mb-0">Избранное
        <span class="badge bg-secondary ms-1"><?= count($recipes) ?></span>
    </h2>
</div>

<?php if (empty($recipes)): ?>
    <div class="text-center py-5 text-muted">
        <p class="fs-5">У вас пока нет избранных рецептов.</p>
        <a href="/index.php" class="btn btn-outline-primary">Перейти к рецептам</a>
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
                            <?php if ($views > 0): ?>
                                <span class="badge bg-light text-muted border views-badge ms-auto">👁 <?= $views ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php require dirname(__DIR__) . '/templates/footer.php'; ?>
