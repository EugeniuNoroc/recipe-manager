<?php
/**
 * RecipeStorage.php — Сохранение и чтение рецептов из JSON-файла
 *
 * Отвечает за всю работу с файловой системой:
 * чтение, запись и сортировку списка рецептов.
 */
class RecipeStorage
{
    /** @var string Абсолютный путь к JSON-файлу */
    private string $file;

    /**
     * Конструктор. Принимает путь к файлу хранилища.
     *
     * @param string $file Путь к файлу (например __DIR__ . '/data.json')
     */
    public function __construct(string $file)
    {
        $this->file = $file;
    }

    /**
     * Читает все рецепты из файла и возвращает массив объектов Recipe.
     * Если файл не существует или повреждён — возвращает пустой массив.
     *
     * @return Recipe[] Массив объектов Recipe
     */
    public function getAll(): array
    {
        if (!file_exists($this->file)) {
            return [];
        }

        $json = file_get_contents($this->file);
        $raw  = json_decode($json, true);

        if (!is_array($raw)) {
            return [];
        }

        // Преобразуем каждый массив в объект Recipe
        return array_map(
            fn(array $item) => Recipe::fromArray($item),
            $raw
        );
    }

    /**
     * Сохраняет новый рецепт в файл.
     * Читает существующие записи, добавляет новую и перезаписывает файл.
     *
     * @param Recipe $recipe Объект рецепта для сохранения
     * @return bool true при успехе, false при ошибке записи
     */
    public function save(Recipe $recipe): bool
    {
        $existing   = $this->getRawArray();
        $existing[] = $recipe->toArray();
        return $this->writeFile($existing);
    }

    /**
     * Сортирует массив объектов Recipe по указанному полю и направлению.
     *
     * @param Recipe[] $recipes Массив объектов Recipe
     * @param string   $field   Поле для сортировки (title, prep_time и т.д.)
     * @param string   $dir     Направление: 'asc' или 'desc'
     * @return Recipe[] Отсортированный массив
     */
    public function sort(array $recipes, string $field, string $dir): array
    {
        usort($recipes, function (Recipe $a, Recipe $b) use ($field, $dir) {
            $valA = $a->$field ?? '';
            $valB = $b->$field ?? '';

            // Числовые поля сравниваем как числа, остальные — как строки
            $cmp = ($field === 'prep_time')
                ? (int)$valA <=> (int)$valB
                : strcmp((string)$valA, (string)$valB);

            return $dir === 'asc' ? $cmp : -$cmp;
        });

        return $recipes;
    }

    /**
     * Читает файл и возвращает сырой массив массивов (не объектов).
     * Вспомогательный приватный метод.
     *
     * @return array[]
     */
    private function getRawArray(): array
    {
        if (!file_exists($this->file)) {
            return [];
        }

        $raw = json_decode(file_get_contents($this->file), true);
        return is_array($raw) ? $raw : [];
    }

    /**
     * Записывает массив данных в JSON-файл с форматированием.
     *
     * @param array $data Массив для записи
     * @return bool true при успехе, false при ошибке записи
     */
    private function writeFile(array $data): bool
    {
        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        return file_put_contents($this->file, $json) !== false;
    }
}
