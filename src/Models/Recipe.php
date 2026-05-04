<?php

declare(strict_types=1);

namespace App\Models;

/**
 * Модель рецепта.
 *
 * Представляет запись из таблицы recipes с присоединёнными данными категории и тегов.
 * Создаётся из результатов PDO через fromArray() или заполняется вручную.
 *
 * @package App\Models
 */
class Recipe
{
    /** @var int Первичный ключ */
    public int    $id           = 0;

    /** @var int ID владельца рецепта (FK → users.id, 0 если не привязан) */
    public int    $user_id      = 0;

    /** @var string Название рецепта */
    public string $title        = '';

    /** @var string Имя автора (текстовое поле, не FK) */
    public string $author       = '';

    /** @var int Время приготовления в минутах */
    public int    $prep_time    = 0;

    /** @var string Название категории (join из categories) */
    public string $category     = '';

    /** @var int ID категории (FK → categories.id) */
    public int    $category_id  = 0;

    /** @var string Сложность: 'Легко', 'Средне', 'Сложно' */
    public string $difficulty   = '';

    /** @var string Список ингредиентов */
    public string $ingredients  = '';

    /** @var string Пошаговые инструкции */
    public string $instructions = '';

    /** @var string Дата создания (Y-m-d) */
    public string $created_at   = '';

    /** @var string Дата последнего обновления (TIMESTAMP) */
    public string $updated_at   = '';

    /** @var string[] Список тегов (join из tags через recipe_tags) */
    public array  $tags         = [];

    /**
     * Создаёт экземпляр из ассоциативного массива (результат PDO::fetch).
     *
     * @param  array $data Массив данных из БД (включая join-поля)
     * @return self
     */
    public static function fromArray(array $data): self
    {
        $r               = new self();
        $r->id           = (int)($data['id']           ?? 0);
        $r->user_id      = (int)($data['user_id']      ?? 0);
        $r->title        = (string)($data['title']        ?? '');
        $r->author       = (string)($data['author']       ?? '');
        $r->prep_time    = (int)($data['prep_time']    ?? 0);
        $r->category     = (string)($data['category']     ?? '');
        $r->category_id  = (int)($data['category_id']  ?? 0);
        $r->difficulty   = (string)($data['difficulty']   ?? '');
        $r->ingredients  = (string)($data['ingredients']  ?? '');
        $r->instructions = (string)($data['instructions'] ?? '');
        $r->created_at   = (string)($data['created_at']   ?? '');
        $r->updated_at   = (string)($data['updated_at']   ?? '');
        $r->tags         = (array)($data['tags']           ?? []);
        return $r;
    }

    /**
     * Возвращает все поля рецепта в виде массива.
     *
     * @return array<string,mixed>
     */
    public function toArray(): array
    {
        return [
            'id'           => $this->id,
            'user_id'      => $this->user_id,
            'title'        => $this->title,
            'author'       => $this->author,
            'prep_time'    => $this->prep_time,
            'category'     => $this->category,
            'category_id'  => $this->category_id,
            'difficulty'   => $this->difficulty,
            'ingredients'  => $this->ingredients,
            'instructions' => $this->instructions,
            'created_at'   => $this->created_at,
            'updated_at'   => $this->updated_at,
            'tags'         => $this->tags,
        ];
    }
}
