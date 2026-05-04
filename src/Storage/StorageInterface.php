<?php

declare(strict_types=1);

namespace App\Storage;

use App\Models\Recipe;

/**
 * Контракт хранилища рецептов.
 *
 * Определяет CRUD-операции и вспомогательные методы для работы с рецептами.
 * Реализуется MySQLRecipeStorage; интерфейс позволяет подменять хранилище
 * (например, для тестов или будущих реализаций).
 *
 * @package App\Storage
 */
interface StorageInterface
{
    /**
     * Возвращает все рецепты, отсортированные по updated_at DESC.
     *
     * @return Recipe[]
     */
    public function getAll(): array;

    /**
     * Ищет рецепт по первичному ключу.
     *
     * @param  int         $id ID рецепта
     * @return Recipe|null     Рецепт или null если не найден
     */
    public function getById(int $id): ?Recipe;

    /**
     * Сохраняет новый рецепт в хранилище.
     *
     * @param  Recipe $recipe Рецепт для сохранения (id будет установлен после INSERT)
     * @return bool           true при успехе
     */
    public function save(Recipe $recipe): bool;

    /**
     * Обновляет существующий рецепт.
     *
     * @param  Recipe $recipe Рецепт с заполненным id
     * @return bool           true при успехе
     */
    public function update(Recipe $recipe): bool;

    /**
     * Удаляет рецепт по ID.
     *
     * @param  int  $id ID рецепта
     * @return bool     true при успехе
     */
    public function delete(int $id): bool;

    /**
     * Сортирует массив рецептов в памяти.
     *
     * @param  Recipe[] $recipes Массив рецептов
     * @param  string   $field   Поле для сортировки (title, prep_time, created_at и т.д.)
     * @param  string   $dir     Направление: 'asc' или 'desc'
     * @return Recipe[]          Отсортированный массив
     */
    public function sort(array $recipes, string $field, string $dir): array;
}
