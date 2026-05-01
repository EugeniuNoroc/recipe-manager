<?php
/**
 * RecipeValidator.php — Валидация данных рецепта
 *
 * Проверяет данные из $_POST перед сохранением.
 * Содержит правила для каждого поля модели.
 */
class RecipeValidator
{
    /** @var array<string,string> Массив ошибок: поле => сообщение */
    private array $errors = [];

    /** @var string[] Допустимые категории блюд */
    private array $allowedCategories = [
        'Супы', 'Салаты', 'Выпечка', 'Десерты',
        'Завтраки', 'Основные блюда', 'Напитки'
    ];

    /** @var string[] Допустимые уровни сложности */
    private array $allowedDifficulties = ['Легко', 'Средне', 'Сложно'];

    /** @var string[] Допустимые теги рецепта */
    private array $allowedTags = [
        'Вегетарианское', 'Без глютена', 'Острое', 'Быстрый рецепт', 'Диетическое'
    ];

    /**
     * Запускает полную валидацию массива данных.
     * Проверяет каждое поле и заполняет массив $errors.
     *
     * @param array $data Очищенные данные из $_POST
     * @return bool true если все поля валидны, false если есть ошибки
     */
    public function validate(array $data): bool
    {
        $this->errors = [];

        $this->validateTitle($data['title'] ?? '');
        $this->validateAuthor($data['author'] ?? '');
        $this->validatePrepTime((int)($data['prep_time'] ?? 0));
        $this->validateCategory($data['category'] ?? '');
        $this->validateDifficulty($data['difficulty'] ?? '');
        $this->validateIngredients($data['ingredients'] ?? '');
        $this->validateInstructions($data['instructions'] ?? '');
        $this->validateCreatedAt($data['created_at'] ?? '');
        $this->validateTags($data['tags'] ?? []);

        return empty($this->errors);
    }

    /**
     * Возвращает все найденные ошибки валидации.
     *
     * @return array<string,string> Массив ошибок: поле => сообщение
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * Возвращает список допустимых категорий блюд.
     *
     * @return string[]
     */
    public function getAllowedCategories(): array
    {
        return $this->allowedCategories;
    }

    /**
     * Возвращает список допустимых уровней сложности.
     *
     * @return string[]
     */
    public function getAllowedDifficulties(): array
    {
        return $this->allowedDifficulties;
    }

    /**
     * Возвращает список допустимых тегов рецепта.
     *
     * @return string[]
     */
    public function getAllowedTags(): array
    {
        return $this->allowedTags;
    }

    /**
     * Проверяет поле title: обязательное, длина 3–255 символов.
     *
     * @param string $value Значение поля
     */
    private function validateTitle(string $value): void
    {
        if (empty($value)) {
            $this->errors['title'] = 'Введите название рецепта';
        } elseif (strlen($value) < 3) {
            $this->errors['title'] = 'Минимум 3 символа';
        } elseif (strlen($value) > 255) {
            $this->errors['title'] = 'Максимум 255 символов';
        }
    }

    /**
     * Проверяет поле author: обязательное, длина 2–100 символов.
     *
     * @param string $value Значение поля
     */
    private function validateAuthor(string $value): void
    {
        if (empty($value)) {
            $this->errors['author'] = 'Введите имя автора';
        } elseif (strlen($value) < 2) {
            $this->errors['author'] = 'Минимум 2 символа';
        }
    }

    /**
     * Проверяет поле prep_time: целое число от 1 до 1440.
     *
     * @param int $value Значение поля в минутах
     */
    private function validatePrepTime(int $value): void
    {
        if ($value < 1 || $value > 1440) {
            $this->errors['prep_time'] = 'Время: от 1 до 1440 минут';
        }
    }

    /**
     * Проверяет поле category: должно быть из допустимого списка.
     *
     * @param string $value Значение поля
     */
    private function validateCategory(string $value): void
    {
        if (!in_array($value, $this->allowedCategories, true)) {
            $this->errors['category'] = 'Выберите категорию из списка';
        }
    }

    /**
     * Проверяет поле difficulty: должно быть из допустимого списка.
     *
     * @param string $value Значение поля
     */
    private function validateDifficulty(string $value): void
    {
        if (!in_array($value, $this->allowedDifficulties, true)) {
            $this->errors['difficulty'] = 'Выберите уровень сложности';
        }
    }

    /**
     * Проверяет поле ingredients: обязательное, минимум 10 символов.
     *
     * @param string $value Значение поля
     */
    private function validateIngredients(string $value): void
    {
        if (empty($value)) {
            $this->errors['ingredients'] = 'Заполните список ингредиентов';
        } elseif (strlen($value) < 10) {
            $this->errors['ingredients'] = 'Минимум 10 символов';
        }
    }

    /**
     * Проверяет поле instructions: обязательное, минимум 20 символов.
     *
     * @param string $value Значение поля
     */
    private function validateInstructions(string $value): void
    {
        if (empty($value)) {
            $this->errors['instructions'] = 'Заполните инструкции';
        } elseif (strlen($value) < 20) {
            $this->errors['instructions'] = 'Минимум 20 символов';
        }
    }

    /**
     * Проверяет поле tags: каждый тег должен быть из допустимого списка.
     *
     * @param array $values Массив выбранных тегов
     */
    private function validateTags(array $values): void
    {
        foreach ($values as $tag) {
            if (!in_array($tag, $this->allowedTags, true)) {
                $this->errors['tags'] = 'Выбран недопустимый тег';
                return;
            }
        }
    }

    /**
     * Проверяет поле created_at: формат Y-m-d, не может быть в будущем.
     *
     * @param string $value Значение поля в формате YYYY-MM-DD
     */
    private function validateCreatedAt(string $value): void
    {
        if (empty($value)) {
            $this->errors['created_at'] = 'Укажите дату создания';
            return;
        }

        $dateObj = DateTime::createFromFormat('Y-m-d', $value);

        if (!$dateObj || $dateObj->format('Y-m-d') !== $value) {
            $this->errors['created_at'] = 'Неверный формат даты (ожидается ГГГГ-ММ-ДД)';
        } elseif ($value > date('Y-m-d')) {
            $this->errors['created_at'] = 'Дата не может быть в будущем';
        }
    }
}
