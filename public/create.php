<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap.php';

use App\Models\Recipe;
use App\Storage\MySQLRecipeStorage;

$auth->requireAuth('/login.php');
$currentUser = $auth->currentUser();

$storage    = new MySQLRecipeStorage($pdo);
$categories = $storage->getCategories();
$tags       = $storage->getTags();

$recipe      = new Recipe();
$recipe->author = $currentUser->username;

$errors     = [];
$pageTitle  = 'Создать рецепт';
$formAction = '/save.php';
$submitLabel = 'Сохранить рецепт';

require dirname(__DIR__) . '/templates/header.php';
?>

<div class="mb-3">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="/index.php">Все рецепты</a></li>
            <li class="breadcrumb-item active">Создать</li>
        </ol>
    </nav>
</div>

<div class="card shadow-sm">
    <div class="card-body p-4">
        <h2 class="h4 mb-4">Новый рецепт</h2>
        <?php require dirname(__DIR__) . '/templates/recipe_form.php'; ?>
    </div>
</div>

<?php require dirname(__DIR__) . '/templates/footer.php'; ?>
