<?php
/**
 * index.php — Список всех рецептов с сортировкой по столбцам.
 *
 * Читает рецепты из data.json через RecipeStorage,
 * сортирует по полю из $_GET['sort'] и направлению $_GET['dir'].
 */

require_once 'Recipe.php';
require_once 'RecipeStorage.php';

$storage = new RecipeStorage(__DIR__ . '/data.json');

// ── Параметры сортировки из URL ──
// Пример URL: index.php?sort=title&dir=asc
$allowed = ['title', 'author', 'category', 'difficulty', 'prep_time', 'created_at', 'updated_at'];
$sort    = $_GET['sort'] ?? 'updated_at';
$dir     = $_GET['dir']  ?? 'desc';

// Защита от подделки параметров — только разрешённые значения
if (!in_array($sort, $allowed, true)) $sort = 'updated_at';
if (!in_array($dir, ['asc', 'desc'], true)) $dir = 'desc';

// ── Получаем и сортируем рецепты через класс ──
$recipes = $storage->getAll();
$recipes = $storage->sort($recipes, $sort, $dir);

/**
 * Строит URL для сортировки по указанному полю.
 * При повторном клике на то же поле — меняет направление на обратное.
 *
 * @param string $field Поле для сортировки
 * @return string URL вида ?sort=field&dir=asc
 */
function sortUrl(string $field): string {
    global $sort, $dir;
    $newDir = ($sort === $field && $dir === 'asc') ? 'desc' : 'asc';
    return '?sort=' . $field . '&dir=' . $newDir;
}

/**
 * Возвращает символ стрелки для активной колонки сортировки.
 *
 * @param string $field Поле для проверки
 * @return string '▲', '▼' или пустая строка
 */
function sortArrow(string $field): string {
    global $sort, $dir;
    if ($sort !== $field) return '';
    return $dir === 'asc' ? ' ▲' : ' ▼';
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Все рецепты</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: sans-serif; background: #f5f5f5; color: #222; padding: 2rem; }
        .container { max-width: 1100px; margin: 0 auto; }
        .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; }
        h1 { font-size: 1.4rem; font-weight: 500; }
        .btn { padding: 8px 18px; border-radius: 7px; font-size: 0.9rem;
               cursor: pointer; border: 1px solid #ccc; background: #fff;
               color: #444; text-decoration: none; display: inline-block; }
        .btn-primary { background: #185fa5; color: #fff; border-color: #185fa5; }
        .btn-primary:hover { background: #0c447c; }
        .table-wrap { background: #fff; border: 1px solid #ddd; border-radius: 10px; overflow: hidden; }
        table { width: 100%; border-collapse: collapse; font-size: 0.88rem; }
        thead { background: #f0f4f8; }
        th a { display: block; color: #333; text-decoration: none; font-weight: 600;
               font-size: 0.78rem; text-transform: uppercase; letter-spacing: .04em; white-space: nowrap; }
        th a:hover { color: #185fa5; }
        th, td { padding: 11px 14px; text-align: left; border-bottom: 1px solid #eee; }
        tr:last-child td { border-bottom: none; }
        tr:hover td { background: #f9fbfd; }
        th.active a { color: #185fa5; }
        .badge { display: inline-block; font-size: 0.72rem; padding: 2px 8px;
                 border-radius: 4px; font-weight: 500; }
        .badge-cat  { background: #eeedfe; color: #3c3489; }
        .badge-easy { background: #eaf3de; color: #27500a; }
        .badge-med  { background: #faeeda; color: #633806; }
        .badge-hard { background: #fcebeb; color: #791f1f; }
        .badge-tag  { background: #f0f0f0; color: #555; }
        .empty { text-align: center; padding: 3rem; color: #999; font-size: 0.95rem; }
        .count { font-size: 0.85rem; color: #888; margin-bottom: 1rem; }
    </style>
</head>
<body>
<div class="container">

    <div class="header">
        <h1>Все рецепты</h1>
        <a href="create.php" class="btn btn-primary">+ Добавить рецепт</a>
    </div>

    <div class="count">
        Найдено записей: <strong><?= count($recipes) ?></strong>
    </div>

    <div class="table-wrap">
        <?php if (empty($recipes)): ?>
            <div class="empty">
                Рецептов пока нет. <a href="create.php">Добавить первый?</a>
            </div>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th class="<?= $sort === 'title'      ? 'active' : '' ?>">
                            <a href="<?= sortUrl('title') ?>">Название<?= sortArrow('title') ?></a>
                        </th>
                        <th class="<?= $sort === 'author'     ? 'active' : '' ?>">
                            <a href="<?= sortUrl('author') ?>">Автор<?= sortArrow('author') ?></a>
                        </th>
                        <th class="<?= $sort === 'category'   ? 'active' : '' ?>">
                            <a href="<?= sortUrl('category') ?>">Категория<?= sortArrow('category') ?></a>
                        </th>
                        <th class="<?= $sort === 'difficulty' ? 'active' : '' ?>">
                            <a href="<?= sortUrl('difficulty') ?>">Сложность<?= sortArrow('difficulty') ?></a>
                        </th>
                        <th class="<?= $sort === 'prep_time'  ? 'active' : '' ?>">
                            <a href="<?= sortUrl('prep_time') ?>">Время (мин)<?= sortArrow('prep_time') ?></a>
                        </th>
                        <th class="<?= $sort === 'created_at' ? 'active' : '' ?>">
                            <a href="<?= sortUrl('created_at') ?>">Создан<?= sortArrow('created_at') ?></a>
                        </th>
                        <th><span style="font-size:.78rem;font-weight:600;text-transform:uppercase;letter-spacing:.04em;color:#333;">Теги</span></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recipes as $r): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($r->title) ?></strong></td>
                            <td><?= htmlspecialchars($r->author) ?></td>
                            <td>
                                <span class="badge badge-cat">
                                    <?= htmlspecialchars($r->category) ?>
                                </span>
                            </td>
                            <td>
                                <?php
                                // match() — компактная замена switch (PHP 8+)
                                $diffClass = match($r->difficulty) {
                                    'Легко'  => 'badge-easy',
                                    'Средне' => 'badge-med',
                                    'Сложно' => 'badge-hard',
                                    default  => ''
                                };
                                ?>
                                <span class="badge <?= $diffClass ?>">
                                    <?= htmlspecialchars($r->difficulty) ?>
                                </span>
                            </td>
                            <td><?= $r->prep_time ?> мин</td>
                            <td><?= htmlspecialchars($r->created_at) ?></td>
                            <td>
                                <?php foreach ($r->tags as $tag): ?>
                                    <span class="badge badge-tag"><?= htmlspecialchars($tag) ?></span>
                                <?php endforeach; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

</div>
</body>
</html>
