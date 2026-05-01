<?php
/**
 * save.php — Обработка формы добавления рецепта.
 *
 * Принимает POST-запрос из create.php,
 * валидирует данные через RecipeValidator,
 * сохраняет рецепт через RecipeStorage.
 */

session_start();

require_once 'Recipe.php';
require_once 'RecipeValidator.php';
require_once 'RecipeStorage.php';

// Защита: принимаем только POST-запросы
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: create.php');
    exit;
}

// ── Получаем и очищаем данные из $_POST ──
// trim() убирает пробелы по краям
// ?? '' — если ключ отсутствует, берём пустую строку
// (int)  — приводим числовое поле к целому
$data = [
    'title'        => trim($_POST['title']        ?? ''),
    'author'       => trim($_POST['author']        ?? ''),
    'prep_time'    => (int)($_POST['prep_time']    ?? 0),
    'category'     => trim($_POST['category']      ?? ''),
    'difficulty'   => trim($_POST['difficulty']    ?? ''),
    'ingredients'  => trim($_POST['ingredients']   ?? ''),
    'instructions' => trim($_POST['instructions']  ?? ''),
    'created_at'   => trim($_POST['created_at']    ?? ''),
    'tags'         => array_values(array_filter((array)($_POST['tags'] ?? []))),
];

// ── Валидация через класс RecipeValidator ──
$validator = new RecipeValidator();

if (!$validator->validate($data)) {
    // Если есть ошибки — сохраняем их в сессию и возвращаем на форму.
    // $_SESSION сохраняется между запросами, в отличие от $_POST.
    $_SESSION['errors'] = $validator->getErrors();
    $_SESSION['old']    = $data;
    header('Location: create.php');
    exit;
}

// ── Создаём объект Recipe и добавляем системные поля ──
$recipe             = Recipe::fromArray($data);
$recipe->id         = time();                   // уникальный ID = Unix timestamp
$recipe->updated_at = date('Y-m-d H:i:s');     // текущая дата и время

// ── Сохраняем через класс RecipeStorage ──
$storage = new RecipeStorage(__DIR__ . '/data.json');

if (!$storage->save($recipe)) {
    $_SESSION['errors'] = ['file' => 'Ошибка сервера: не удалось сохранить файл'];
    $_SESSION['old']    = $data;
    header('Location: create.php');
    exit;
}

// ── Успех: паттерн PRG (Post/Redirect/Get) ──
// Редирект после POST предотвращает повторную отправку при обновлении страницы
$_SESSION['success'] = 'Рецепт "' . $recipe->title . '" успешно сохранён!';
header('Location: create.php');
exit;
