<?php
/**
 * Shared partial for create/edit recipe forms.
 * Expects: $recipe (Recipe), $categories (array), $tags (array),
 *          $errors (array), $formAction (string), $submitLabel (string)
 */
use App\Support\Csrf;

$difficulties = ['Легко', 'Средне', 'Сложно'];
?>

<form method="POST" action="<?= htmlspecialchars($formAction) ?>" novalidate>
    <?= Csrf::field() ?>
    <?php if ($recipe->id): ?>
        <input type="hidden" name="id" value="<?= $recipe->id ?>">
    <?php endif; ?>

    <div class="row g-3">

        <div class="col-md-8">
            <label class="form-label">Название <span class="text-danger">*</span></label>
            <input type="text" name="title"
                   class="form-control <?= isset($errors['title']) ? 'is-invalid' : '' ?>"
                   value="<?= htmlspecialchars($recipe->title) ?>" required>
            <?php if (isset($errors['title'])): ?>
                <div class="invalid-feedback"><?= htmlspecialchars($errors['title']) ?></div>
            <?php endif; ?>
        </div>

        <div class="col-md-4">
            <label class="form-label">Автор <span class="text-danger">*</span></label>
            <input type="text" name="author"
                   class="form-control <?= isset($errors['author']) ? 'is-invalid' : '' ?>"
                   value="<?= htmlspecialchars($recipe->author) ?>" required>
            <?php if (isset($errors['author'])): ?>
                <div class="invalid-feedback"><?= htmlspecialchars($errors['author']) ?></div>
            <?php endif; ?>
        </div>

        <div class="col-md-4">
            <label class="form-label">Категория <span class="text-danger">*</span></label>
            <select name="category"
                    class="form-select <?= isset($errors['category']) ? 'is-invalid' : '' ?>">
                <option value="">— выберите —</option>
                <?php foreach ($categories as $cat): ?>
                    <option value="<?= htmlspecialchars($cat['name']) ?>"
                        <?= $recipe->category === $cat['name'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($cat['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <?php if (isset($errors['category'])): ?>
                <div class="invalid-feedback"><?= htmlspecialchars($errors['category']) ?></div>
            <?php endif; ?>
        </div>

        <div class="col-md-4">
            <label class="form-label">Сложность <span class="text-danger">*</span></label>
            <select name="difficulty"
                    class="form-select <?= isset($errors['difficulty']) ? 'is-invalid' : '' ?>">
                <option value="">— выберите —</option>
                <?php foreach ($difficulties as $d): ?>
                    <option value="<?= $d ?>" <?= $recipe->difficulty === $d ? 'selected' : '' ?>>
                        <?= $d ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <?php if (isset($errors['difficulty'])): ?>
                <div class="invalid-feedback"><?= htmlspecialchars($errors['difficulty']) ?></div>
            <?php endif; ?>
        </div>

        <div class="col-md-4">
            <label class="form-label">Время приготовления (мин) <span class="text-danger">*</span></label>
            <input type="number" name="prep_time" min="1" max="1440"
                   class="form-control <?= isset($errors['prep_time']) ? 'is-invalid' : '' ?>"
                   value="<?= $recipe->prep_time ?: '' ?>" required>
            <?php if (isset($errors['prep_time'])): ?>
                <div class="invalid-feedback"><?= htmlspecialchars($errors['prep_time']) ?></div>
            <?php endif; ?>
        </div>

        <div class="col-md-4">
            <label class="form-label">Дата создания <span class="text-danger">*</span></label>
            <input type="date" name="created_at"
                   class="form-control <?= isset($errors['created_at']) ? 'is-invalid' : '' ?>"
                   value="<?= htmlspecialchars($recipe->created_at ?: date('Y-m-d')) ?>" required>
            <?php if (isset($errors['created_at'])): ?>
                <div class="invalid-feedback"><?= htmlspecialchars($errors['created_at']) ?></div>
            <?php endif; ?>
        </div>

        <div class="col-12">
            <label class="form-label">Ингредиенты <span class="text-danger">*</span></label>
            <textarea name="ingredients" rows="4"
                      class="form-control <?= isset($errors['ingredients']) ? 'is-invalid' : '' ?>"
                      required><?= htmlspecialchars($recipe->ingredients) ?></textarea>
            <?php if (isset($errors['ingredients'])): ?>
                <div class="invalid-feedback"><?= htmlspecialchars($errors['ingredients']) ?></div>
            <?php endif; ?>
        </div>

        <div class="col-12">
            <label class="form-label">Инструкции <span class="text-danger">*</span></label>
            <textarea name="instructions" rows="6"
                      class="form-control <?= isset($errors['instructions']) ? 'is-invalid' : '' ?>"
                      required><?= htmlspecialchars($recipe->instructions) ?></textarea>
            <?php if (isset($errors['instructions'])): ?>
                <div class="invalid-feedback"><?= htmlspecialchars($errors['instructions']) ?></div>
            <?php endif; ?>
        </div>

        <div class="col-12">
            <label class="form-label">Теги</label>
            <div class="d-flex flex-wrap gap-3 mb-2">
                <?php foreach ($tags as $tag): ?>
                    <div class="form-check">
                        <input type="checkbox" name="tags[]"
                               class="form-check-input"
                               id="tag_<?= $tag['id'] ?>"
                               value="<?= htmlspecialchars($tag['name']) ?>"
                               <?= in_array($tag['name'], $recipe->tags, true) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="tag_<?= $tag['id'] ?>">
                            <?= htmlspecialchars($tag['name']) ?>
                        </label>
                    </div>
                <?php endforeach; ?>
            </div>
            <input type="text" name="new_tags"
                   class="form-control form-control-sm"
                   placeholder="Новые теги через запятую (напр.: Без сахара, Пп-блюдо)">
            <div class="form-text">Введите новые теги через запятую, если нужных нет в списке.</div>
        </div>

        <div class="col-12 d-flex gap-2">
            <button type="submit" class="btn btn-primary"><?= htmlspecialchars($submitLabel) ?></button>
            <a href="/index.php" class="btn btn-outline-secondary">Отмена</a>
        </div>

    </div>
</form>
