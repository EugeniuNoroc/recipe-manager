<?php

declare(strict_types=1);

namespace App\Validators;

/**
 * Валидатор данных рецепта.
 *
 * Проверяет все поля формы рецепта перед сохранением.
 * Собирает ошибки по полям; возвращает true если данные корректны.
 *
 * @package App\Validators
 */
class RecipeValidator
{
    /** @var array<string,string> Карта ошибок: поле → сообщение */
    private array $errors = [];

    /** @var string[] Допустимые категории */
    private array $allowedCategories = [
        'Завтрак', 'Обед', 'Ужин', 'Десерт', 'Напитки',
        'Супы', 'Салаты', 'Выпечка', 'Основные блюда',
    ];

    /** @var string[] Допустимые уровни сложности */
    private array $allowedDifficulties = ['Легко', 'Средне', 'Сложно'];

    /** @var string[] Допустимые теги */
    private array $allowedTags = [
        'Вегетарианское', 'Без глютена', 'Острое', 'Быстрый рецепт', 'Диетическое',
    ];

    /**
     * Валидирует массив данных рецепта.
     *
     * @param  array $data Поля: title, author, prep_time, category, difficulty,
     *                     ingredients, instructions, created_at, tags
     * @return bool        true если ошибок нет
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
     * Возвращает карту ошибок после validate().
     *
     * @return array<string,string>
     */
    public function getErrors(): array { return $this->errors; }

    /**
     * @return string[]
     */
    public function getAllowedCategories(): array  { return $this->allowedCategories; }

    /**
     * @return string[]
     */
    public function getAllowedDifficulties(): array { return $this->allowedDifficulties; }

    /**
     * @return string[]
     */
    public function getAllowedTags(): array         { return $this->allowedTags; }

    /**
     * @param string $value
     */
    private function validateTitle(string $value): void
    {
        if (empty($value))          { $this->errors['title'] = 'Введите название рецепта'; }
        elseif (strlen($value) < 3) { $this->errors['title'] = 'Минимум 3 символа'; }
        elseif (strlen($value) > 255){ $this->errors['title'] = 'Максимум 255 символов'; }
    }

    /**
     * @param string $value
     */
    private function validateAuthor(string $value): void
    {
        if (empty($value))          { $this->errors['author'] = 'Введите имя автора'; }
        elseif (strlen($value) < 2) { $this->errors['author'] = 'Минимум 2 символа'; }
    }

    /**
     * @param int $value Время в минутах
     */
    private function validatePrepTime(int $value): void
    {
        if ($value < 1 || $value > 1440) {
            $this->errors['prep_time'] = 'Время: от 1 до 1440 минут';
        }
    }

    /**
     * @param string $value
     */
    private function validateCategory(string $value): void
    {
        if (!in_array($value, $this->allowedCategories, true)) {
            $this->errors['category'] = 'Выберите категорию из списка';
        }
    }

    /**
     * @param string $value
     */
    private function validateDifficulty(string $value): void
    {
        if (!in_array($value, $this->allowedDifficulties, true)) {
            $this->errors['difficulty'] = 'Выберите уровень сложности';
        }
    }

    /**
     * @param string $value
     */
    private function validateIngredients(string $value): void
    {
        if (empty($value))           { $this->errors['ingredients'] = 'Заполните список ингредиентов'; }
        elseif (strlen($value) < 10) { $this->errors['ingredients'] = 'Минимум 10 символов'; }
    }

    /**
     * @param string $value
     */
    private function validateInstructions(string $value): void
    {
        if (empty($value))           { $this->errors['instructions'] = 'Заполните инструкции'; }
        elseif (strlen($value) < 20) { $this->errors['instructions'] = 'Минимум 20 символов'; }
    }

    /**
     * @param string[] $values
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
     * @param string $value Дата в формате Y-m-d
     */
    private function validateCreatedAt(string $value): void
    {
        if (empty($value)) {
            $this->errors['created_at'] = 'Укажите дату создания';
            return;
        }
        $dt = \DateTime::createFromFormat('Y-m-d', $value);
        if (!$dt || $dt->format('Y-m-d') !== $value) {
            $this->errors['created_at'] = 'Неверный формат даты (ожидается ГГГГ-ММ-ДД)';
        } elseif ($value > date('Y-m-d')) {
            $this->errors['created_at'] = 'Дата не может быть в будущем';
        }
    }
}
