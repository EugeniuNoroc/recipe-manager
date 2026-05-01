<?php
/**
 * Recipe.php — Модель данных рецепта
 *
 * Хранит все поля одного рецепта и умеет
 * создавать себя из массива (например из JSON).
 */
class Recipe
{
    /** @var int Уникальный ID (Unix timestamp) */
    public int $id = 0;

    /** @var string Название рецепта */
    public string $title = '';

    /** @var string Автор рецепта */
    public string $author = '';

    /** @var int Время приготовления в минутах */
    public int $prep_time = 0;

    /** @var string Категория блюда */
    public string $category = '';

    /** @var string Уровень сложности */
    public string $difficulty = '';

    /** @var string Список ингредиентов */
    public string $ingredients = '';

    /** @var string Пошаговые инструкции */
    public string $instructions = '';

    /** @var string Дата создания в формате Y-m-d */
    public string $created_at = '';

    /** @var string Дата последнего обновления */
    public string $updated_at = '';

    /** @var string[] Теги рецепта (вегетарианское, острое и т.д.) */
    public array $tags = [];

    /**
     * Создаёт объект Recipe из ассоциативного массива.
     * Используется при чтении из JSON-файла или из $_POST.
     *
     * @param array $data Массив с полями рецепта
     * @return self Новый объект Recipe
     */
    public static function fromArray(array $data): self
    {
        $recipe               = new self();
        $recipe->id           = (int)($data['id']           ?? 0);
        $recipe->title        = (string)($data['title']        ?? '');
        $recipe->author       = (string)($data['author']       ?? '');
        $recipe->prep_time    = (int)($data['prep_time']    ?? 0);
        $recipe->category     = (string)($data['category']     ?? '');
        $recipe->difficulty   = (string)($data['difficulty']   ?? '');
        $recipe->ingredients  = (string)($data['ingredients']  ?? '');
        $recipe->instructions = (string)($data['instructions'] ?? '');
        $recipe->created_at   = (string)($data['created_at']   ?? '');
        $recipe->updated_at   = (string)($data['updated_at']   ?? '');
        $recipe->tags         = (array)($data['tags']          ?? []);
        return $recipe;
    }

    /**
     * Преобразует объект Recipe в ассоциативный массив.
     * Используется перед сохранением в JSON-файл.
     *
     * @return array Массив со всеми полями рецепта
     */
    public function toArray(): array
    {
        return [
            'id'           => $this->id,
            'title'        => $this->title,
            'author'       => $this->author,
            'prep_time'    => $this->prep_time,
            'category'     => $this->category,
            'difficulty'   => $this->difficulty,
            'ingredients'  => $this->ingredients,
            'instructions' => $this->instructions,
            'created_at'   => $this->created_at,
            'updated_at'   => $this->updated_at,
            'tags'         => $this->tags,
        ];
    }
}
