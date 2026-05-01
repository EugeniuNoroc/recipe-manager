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
    header('Location: /index.php');
    exit;
}

if (!Csrf::verify($_POST['_token'] ?? '')) {
    Flash::error('Неверный CSRF-токен.');
    header('Location: /index.php');
    exit;
}

$storage = new MySQLRecipeStorage($pdo);
$id      = (int)($_POST['id'] ?? 0);
$recipe  = $storage->getById($id);

if (!$recipe) {
    http_response_code(404);
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

$existingTags = array_filter(array_map('trim', (array)($_POST['tags'] ?? [])));
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
    'created_at'   => $recipe->created_at,
    'tags'         => $allTags,
];

$validator = new RecipeValidator();
if (!$validator->validate($data)) {
    $errors     = $validator->getErrors();
    $recipe     = Recipe::fromArray(array_merge($recipe->toArray(), $data));
    $categories = $storage->getCategories();
    $tags       = $storage->getTags();
    $pageTitle   = 'Редактировать';
    $formAction  = '/update.php';
    $submitLabel = 'Обновить рецепт';

    require dirname(__DIR__) . '/templates/header.php';
    echo '<div class="card shadow-sm"><div class="card-body p-4"><h2 class="h4 mb-4">Редактировать рецепт</h2>';
    require dirname(__DIR__) . '/templates/recipe_form.php';
    echo '</div></div>';
    require dirname(__DIR__) . '/templates/footer.php';
    exit;
}

$recipe->title        = $data['title'];
$recipe->author       = $data['author'];
$recipe->prep_time    = $data['prep_time'];
$recipe->category     = $data['category'];
$recipe->difficulty   = $data['difficulty'];
$recipe->ingredients  = $data['ingredients'];
$recipe->instructions = $data['instructions'];
$recipe->tags         = $data['tags'];

$storage->update($recipe);

Flash::success('Рецепт обновлён.');
header('Location: /view.php?id=' . $recipe->id);
exit;
