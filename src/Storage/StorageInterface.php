<?php

declare(strict_types=1);

namespace App\Storage;

use App\Models\Recipe;

interface StorageInterface
{
    /** @return Recipe[] */
    public function getAll(): array;

    public function getById(int $id): ?Recipe;

    public function save(Recipe $recipe): bool;

    public function update(Recipe $recipe): bool;

    public function delete(int $id): bool;

    /** @return Recipe[] */
    public function sort(array $recipes, string $field, string $dir): array;
}
