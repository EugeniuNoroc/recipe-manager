<?php
/**
 * create.php — Форма добавления нового рецепта.
 *
 * При GET-запросе показывает пустую форму.
 * Ошибки и старые значения читает из сессии (переданные из save.php).
 * Данные отправляет на save.php методом POST.
 */

session_start();

require_once 'RecipeValidator.php';

// ── Читаем данные из сессии ──
// Они появляются здесь только если save.php нашёл ошибки и сделал редирект
$errors  = $_SESSION['errors']  ?? [];  // массив ошибок валидации
$success = $_SESSION['success'] ?? '';  // сообщение об успехе
$old     = $_SESSION['old']     ?? [];  // старые значения полей

// Очищаем сессию — данные нужны только один раз
unset($_SESSION['errors'], $_SESSION['success'], $_SESSION['old']);

// ── Берём списки из валидатора — единый источник правды ──
// Так списки не дублируются: они определены только в RecipeValidator
$validator    = new RecipeValidator();
$categories   = $validator->getAllowedCategories();
$difficulties = $validator->getAllowedDifficulties();
$tags         = $validator->getAllowedTags();
$today        = date('Y-m-d');

/**
 * Возвращает старое значение поля с экранированием HTML.
 * Используется в value="" полей формы чтобы не терять данные при ошибке.
 *
 * @param string $key     Имя поля (совпадает с name="" в HTML)
 * @param string $default Значение по умолчанию если поле пустое
 * @return string Безопасное для вывода в HTML значение
 */
function old(string $key, string $default = ''): string {
    global $old;
    return htmlspecialchars($old[$key] ?? $default);
}

/**
 * Возвращает CSS-класс ' field-error' если для поля есть ошибка валидации.
 * Добавляется к div.field чтобы подсветить поле красной рамкой.
 *
 * @param string $key Имя поля
 * @return string ' field-error' или пустая строка
 */
function errClass(string $key): string {
    global $errors;
    return isset($errors[$key]) ? ' field-error' : '';
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Новый рецепт</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: sans-serif; background: #f5f5f5; color: #222; padding: 2rem; }
        .container { max-width: 720px; margin: 0 auto; background: #fff;
                     border: 1px solid #ddd; border-radius: 10px; padding: 2rem; }
        h1 { font-size: 1.4rem; font-weight: 500; margin-bottom: 1.5rem; }
        .section-title { font-size: 0.75rem; font-weight: 600; text-transform: uppercase;
                         letter-spacing: .07em; color: #888; border-bottom: 1px solid #eee;
                         padding-bottom: 6px; margin: 1.5rem 0 1rem; }
        label { display: block; font-size: 0.85rem; font-weight: 500; color: #555; margin-bottom: 5px; }
        .badge { display: inline-block; font-size: 0.7rem; padding: 1px 7px;
                 border-radius: 4px; margin-left: 5px; font-weight: 400; }
        .badge-string { background: #e6f1fb; color: #0c447c; }
        .badge-text   { background: #eaf3de; color: #27500a; }
        .badge-enum   { background: #eeedfe; color: #3c3489; }
        .badge-date   { background: #faeeda; color: #633806; }
        input[type="text"], input[type="number"], input[type="date"], select, textarea {
            width: 100%; padding: 9px 12px; font-size: 0.9rem;
            border: 1px solid #ccc; border-radius: 7px;
            background: #fafafa; color: #222; transition: border-color .15s;
        }
        input:focus, select:focus, textarea:focus { outline: none; border-color: #378add; background: #fff; }
        textarea { resize: vertical; line-height: 1.6; }
        .field { margin-bottom: 1.1rem; }
        .grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
        .hint { font-size: 0.75rem; color: #999; margin-top: 4px; }
        .radio-group { display: flex; gap: 8px; flex-wrap: wrap; margin-top: 4px; }
        .radio-item { display: flex; align-items: center; gap: 6px; background: #f5f5f5;
                      border: 1px solid #ddd; border-radius: 7px; padding: 7px 14px;
                      cursor: pointer; font-size: 0.85rem; }
        .checkbox-group { display: flex; gap: 8px; flex-wrap: wrap; margin-top: 4px; }
        .checkbox-item { display: flex; align-items: center; gap: 6px; background: #f5f5f5;
                         border: 1px solid #ddd; border-radius: 7px; padding: 7px 14px;
                         cursor: pointer; font-size: 0.85rem; }
        .checkbox-item input[type="checkbox"] { width: auto; }
        .actions { display: flex; justify-content: flex-end; gap: 10px;
                   border-top: 1px solid #eee; padding-top: 1.25rem; margin-top: 1rem; }
        .btn { padding: 9px 22px; border-radius: 7px; font-size: 0.9rem;
               cursor: pointer; border: 1px solid #ccc; background: #fff; color: #444;
               text-decoration: none; display: inline-block; }
        .btn-primary { background: #185fa5; color: #fff; border-color: #185fa5; }
        .btn-primary:hover { background: #0c447c; }
        .required { color: #e24b4a; }
        .alert-success { background: #eaf3de; border: 1px solid #97c459; color: #27500a;
                         border-radius: 7px; padding: 12px 16px; margin-bottom: 1.5rem; font-size: 0.9rem; }
        .alert-error   { background: #fcebeb; border: 1px solid #f09595; color: #791f1f;
                         border-radius: 7px; padding: 12px 16px; margin-bottom: 1.5rem; font-size: 0.9rem; }
        .field-error input, .field-error select, .field-error textarea { border-color: #e24b4a; }
        .error-msg { font-size: 0.75rem; color: #e24b4a; margin-top: 4px; }
    </style>
</head>
<body>
<div class="container">
    <h1>Новый рецепт</h1>

    <?php if ($success): ?>
        <div class="alert-success"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>

    <?php if (!empty($errors)): ?>
        <div class="alert-error">Исправьте ошибки — найдено: <?= count($errors) ?>.</div>
    <?php endif; ?>

    <form action="save.php" method="POST">

        <div class="section-title">Основная информация</div>

        <div class="field<?= errClass('title') ?>">
            <label>Название рецепта <span class="badge badge-string">string</span> <span class="required">*</span></label>
            <input type="text" name="title"
                   placeholder="Например: Борщ классический"
                   value="<?= old('title') ?>"
                   required minlength="3" maxlength="255">
            <?php if (isset($errors['title'])): ?>
                <div class="error-msg"><?= $errors['title'] ?></div>
            <?php endif; ?>
        </div>

        <div class="grid-2">
            <div class="field<?= errClass('author') ?>">
                <label>Автор рецепта <span class="badge badge-string">string</span> <span class="required">*</span></label>
                <input type="text" name="author"
                       placeholder="Ваше имя"
                       value="<?= old('author') ?>"
                       required minlength="2" maxlength="100">
                <?php if (isset($errors['author'])): ?>
                    <div class="error-msg"><?= $errors['author'] ?></div>
                <?php endif; ?>
            </div>

            <div class="field<?= errClass('prep_time') ?>">
                <label>Время приготовления (мин) <span class="required">*</span></label>
                <input type="number" name="prep_time"
                       placeholder="60"
                       value="<?= old('prep_time') ?>"
                       required min="1" max="1440">
                <?php if (isset($errors['prep_time'])): ?>
                    <div class="error-msg"><?= $errors['prep_time'] ?></div>
                <?php endif; ?>
            </div>
        </div>

        <div class="grid-2">
            <div class="field<?= errClass('category') ?>">
                <label>Категория блюда <span class="badge badge-enum">enum</span> <span class="required">*</span></label>
                <select name="category" required>
                    <option value="" disabled <?= empty($old['category']) ? 'selected' : '' ?>>— выберите —</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= htmlspecialchars($cat) ?>"
                                <?= ($old['category'] ?? '') === $cat ? 'selected' : '' ?>>
                            <?= htmlspecialchars($cat) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php if (isset($errors['category'])): ?>
                    <div class="error-msg"><?= $errors['category'] ?></div>
                <?php endif; ?>
            </div>

            <div class="field<?= errClass('difficulty') ?>">
                <label>Сложность <span class="badge badge-enum">enum</span> <span class="required">*</span></label>
                <div class="radio-group">
                    <?php foreach ($difficulties as $diff): ?>
                        <label class="radio-item">
                            <input type="radio" name="difficulty"
                                   value="<?= htmlspecialchars($diff) ?>"
                                   <?= ($old['difficulty'] ?? 'Средне') === $diff ? 'checked' : '' ?>>
                            <?= htmlspecialchars($diff) ?>
                        </label>
                    <?php endforeach; ?>
                </div>
                <?php if (isset($errors['difficulty'])): ?>
                    <div class="error-msg"><?= $errors['difficulty'] ?></div>
                <?php endif; ?>
            </div>
        </div>

        <div class="section-title">Содержимое рецепта</div>

        <div class="field<?= errClass('ingredients') ?>">
            <label>Ингредиенты <span class="badge badge-text">text</span> <span class="required">*</span></label>
            <textarea name="ingredients" rows="5"
                      placeholder="- 500г говядины&#10;- 2 моркови&#10;- 3 картофелины"
                      required minlength="10"><?= old('ingredients') ?></textarea>
            <div class="hint">Каждый ингредиент с новой строки</div>
            <?php if (isset($errors['ingredients'])): ?>
                <div class="error-msg"><?= $errors['ingredients'] ?></div>
            <?php endif; ?>
        </div>

        <div class="field<?= errClass('instructions') ?>">
            <label>Инструкции приготовления <span class="badge badge-text">text</span> <span class="required">*</span></label>
            <textarea name="instructions" rows="7"
                      placeholder="1. Нарежьте мясо кубиками...&#10;2. Обжарьте лук..."
                      required minlength="20"><?= old('instructions') ?></textarea>
            <div class="hint">Пошаговое описание процесса</div>
            <?php if (isset($errors['instructions'])): ?>
                <div class="error-msg"><?= $errors['instructions'] ?></div>
            <?php endif; ?>
        </div>

        <div class="section-title">Теги</div>

        <div class="field<?= errClass('tags') ?>">
            <label>Особенности блюда <span class="badge badge-enum">checkbox</span></label>
            <div class="checkbox-group">
                <?php foreach ($tags as $tag): ?>
                    <label class="checkbox-item">
                        <input type="checkbox" name="tags[]"
                               value="<?= htmlspecialchars($tag) ?>"
                               <?= in_array($tag, (array)($old['tags'] ?? []), true) ? 'checked' : '' ?>>
                        <?= htmlspecialchars($tag) ?>
                    </label>
                <?php endforeach; ?>
            </div>
            <?php if (isset($errors['tags'])): ?>
                <div class="error-msg"><?= $errors['tags'] ?></div>
            <?php endif; ?>
            <div class="hint">Необязательно — можно выбрать несколько</div>
        </div>

        <div class="section-title">Дата</div>

        <div class="field<?= errClass('created_at') ?>" style="max-width: 340px">
            <label>Дата создания <span class="badge badge-date">date</span> <span class="required">*</span></label>
            <input type="date" name="created_at"
                   value="<?= old('created_at', $today) ?>"
                   required max="<?= $today ?>">
            <?php if (isset($errors['created_at'])): ?>
                <div class="error-msg"><?= $errors['created_at'] ?></div>
            <?php endif; ?>
        </div>

        <div class="actions">
            <a href="index.php" class="btn">Отмена</a>
            <button type="submit" class="btn btn-primary">Сохранить рецепт</button>
        </div>

    </form>
</div>
</body>
</html>
