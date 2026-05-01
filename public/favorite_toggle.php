<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap.php';

use App\Support\Csrf;
use App\Support\Flash;

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

$recipeId = (int)($_POST['recipe_id'] ?? 0);
if ($recipeId <= 0) {
    header('Location: /index.php');
    exit;
}

if ($favorites->isFavorite($currentUser->id, $recipeId)) {
    $favorites->remove($currentUser->id, $recipeId);
    Flash::info('Удалено из избранного.');
} else {
    $favorites->add($currentUser->id, $recipeId);
    Flash::success('Добавлено в избранное.');
}

// Redirect back to where the user came from
$back = $_SERVER['HTTP_REFERER'] ?? '/view.php?id=' . $recipeId;
// Sanitize: only allow same-origin redirects
if (!str_starts_with($back, '/') && !str_starts_with($back, 'http://localhost')) {
    $back = '/view.php?id=' . $recipeId;
}
header('Location: ' . $back);
exit;
