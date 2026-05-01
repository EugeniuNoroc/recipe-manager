<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap.php';

use App\Models\Recipe;
use App\Storage\MySQLRecipeStorage;
use App\Support\Csrf;
use App\Support\Flash;
use App\Validators\RecipeValidator;

$auth->requireAuth('/login.php');
$currentUser = $auth->currentUser();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /create.php');
    exit;
}

if (!Csrf::verify($_POST['_token'] ?? '')) {
    Flash::error('Неверный CSRF-токен.');
    header('Location: /create.php');
    exit;
}

// ── Rate limiter: 3 recipes per 60 s per user ─────────────────────────────────
if (!$rateLimiter->check("rate:create:{$currentUser->id}", 3, 60)) {
    Flash::error('Слишком много рецептов за короткое время. Подождите немного.');
    header('Location: /create.php');
    exit;
}

$storage = new MySQLRecipeStorage($pdo);

$existingTags = array_filter(array_map('trim', (array)($_POST['tags']     ?? [])));
$newTags      = array_filter(array_map('trim', explode(',', $_POST['new_tags'] ?? '')));
$allTags      = array_values(array_unique(array_merge($existingTags, $newTags)));

$data = [
    'title'        => trim($_POST['title']        ?? ''),
    'author'       => trim($_POST['author']       ?? ''),
    'prep_time'    => (int)($_POST['prep_time']   ?? 0),
    'category'     => trim($_POST['category']     ?? ''),
    'difficulty'   => trim($_POST['difficulty']   ?? ''),
    'ingredients'  => trim($_POST['ingredients']  ?? ''),
    'instructions' => trim($_POST['instructions'] ?? ''),
    'created_at'   => trim($_POST['created_at']   ?? ''),
    'tags'         => $allTags,
];

$validator = new RecipeValidator();
if (!$validator->validate($data)) {
    $errors      = $validator->getErrors();
    $recipe      = Recipe::fromArray($data);
    $categories  = $storage->getCategories();
    $tags        = $storage->getTags();
    $pageTitle   = 'Создать рецепт';
    $formAction  = '/save.php';
    $submitLabel = 'Сохранить рецепт';

    require dirname(__DIR__) . '/templates/header.php';
    echo '<div class="card shadow-sm"><div class="card-body p-4"><h2 class="h4 mb-4">Новый рецепт</h2>';
    require dirname(__DIR__) . '/templates/recipe_form.php';
    echo '</div></div>';
    require dirname(__DIR__) . '/templates/footer.php';
    exit;
}

$recipe          = Recipe::fromArray($data);
$recipe->user_id = $currentUser->id;
$storage->save($recipe);

Flash::success('Рецепт «' . $recipe->title . '» успешно создан!');
header('Location: /view.php?id=' . $recipe->id);
exit;
