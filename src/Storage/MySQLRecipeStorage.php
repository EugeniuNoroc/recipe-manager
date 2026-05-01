<?php

declare(strict_types=1);

namespace App\Storage;

use App\Models\Recipe;
use PDO;

class MySQLRecipeStorage implements StorageInterface
{
    public function __construct(private PDO $pdo) {}

    private const BASE_SELECT = '
        SELECT r.*, c.name AS category,
               GROUP_CONCAT(t.name ORDER BY t.name SEPARATOR \',\') AS tags
        FROM recipes r
        LEFT JOIN categories c ON r.category_id = c.id
        LEFT JOIN recipe_tags rt ON rt.recipe_id = r.id
        LEFT JOIN tags t ON t.id = rt.tag_id';

    public function getAll(): array
    {
        $stmt = $this->pdo->query(
            self::BASE_SELECT . ' GROUP BY r.id ORDER BY r.updated_at DESC'
        );
        return array_map(fn($row) => Recipe::fromArray($this->mapRow($row)), $stmt->fetchAll());
    }

    /**
     * Filtered list. Both params are optional (zero / empty = no filter).
     * Combines with AND when both are provided.
     */
    public function getFiltered(int $categoryId = 0, string $search = ''): array
    {
        $where  = [];
        $params = [];

        if ($categoryId > 0) {
            $where[]  = 'r.category_id = ?';
            $params[] = $categoryId;
        }
        if ($search !== '') {
            $like     = '%' . $search . '%';
            $where[]  = '(r.title LIKE ? OR r.ingredients LIKE ?)';
            $params[] = $like;
            $params[] = $like;
        }

        $sql = self::BASE_SELECT;
        if ($where) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $sql .= ' GROUP BY r.id ORDER BY r.updated_at DESC';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return array_map(fn($row) => Recipe::fromArray($this->mapRow($row)), $stmt->fetchAll());
    }

    public function getById(int $id): ?Recipe
    {
        $stmt = $this->pdo->prepare(
            self::BASE_SELECT . ' WHERE r.id = ? GROUP BY r.id'
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ? Recipe::fromArray($this->mapRow($row)) : null;
    }

    /** @param int[] $ids */
    public function findByIds(array $ids): array
    {
        if (empty($ids)) {
            return [];
        }
        $ph   = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $this->pdo->prepare(
            self::BASE_SELECT . " WHERE r.id IN ({$ph}) GROUP BY r.id"
        );
        $stmt->execute(array_values($ids));
        return array_map(fn($row) => Recipe::fromArray($this->mapRow($row)), $stmt->fetchAll());
    }

    public function save(Recipe $recipe): bool
    {
        $categoryId = $this->resolveCategoryId($recipe->category);

        $stmt = $this->pdo->prepare(
            'INSERT INTO recipes
                (user_id, title, author, prep_time, category_id, difficulty,
                 ingredients, instructions, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $recipe->user_id ?: null,
            $recipe->title, $recipe->author, $recipe->prep_time,
            $categoryId, $recipe->difficulty,
            $recipe->ingredients, $recipe->instructions,
            $recipe->created_at ?: date('Y-m-d'),
        ]);
        $recipe->id = (int) $this->pdo->lastInsertId();
        $this->syncTags($recipe->id, $recipe->tags);
        return true;
    }

    public function update(Recipe $recipe): bool
    {
        $categoryId = $this->resolveCategoryId($recipe->category);

        $stmt = $this->pdo->prepare(
            'UPDATE recipes
             SET title=?, author=?, prep_time=?, category_id=?, difficulty=?,
                 ingredients=?, instructions=?, updated_at=NOW()
             WHERE id=?'
        );
        $stmt->execute([
            $recipe->title, $recipe->author, $recipe->prep_time,
            $categoryId, $recipe->difficulty,
            $recipe->ingredients, $recipe->instructions,
            $recipe->id,
        ]);
        $this->syncTags($recipe->id, $recipe->tags);
        return true;
    }

    public function delete(int $id): bool
    {
        return $this->pdo->prepare('DELETE FROM recipes WHERE id = ?')->execute([$id]);
    }

    public function sort(array $recipes, string $field, string $dir): array
    {
        usort($recipes, function (Recipe $a, Recipe $b) use ($field, $dir) {
            $cmp = ($field === 'prep_time')
                ? $a->$field <=> $b->$field
                : strcmp((string) $a->$field, (string) $b->$field);
            return $dir === 'asc' ? $cmp : -$cmp;
        });
        return $recipes;
    }

    /** @return array<array{id:int,name:string}> */
    public function getCategories(): array
    {
        return $this->pdo->query('SELECT id, name FROM categories ORDER BY name')->fetchAll();
    }

    /** @return array<array{id:int,name:string}> */
    public function getTags(): array
    {
        return $this->pdo->query('SELECT id, name FROM tags ORDER BY name')->fetchAll();
    }

    private function mapRow(array $row): array
    {
        $row['tags'] = $row['tags'] ? explode(',', $row['tags']) : [];
        return $row;
    }

    private function resolveCategoryId(string $name): int
    {
        $stmt = $this->pdo->prepare('SELECT id FROM categories WHERE name = ?');
        $stmt->execute([$name]);
        $row = $stmt->fetch();
        if ($row) {
            return (int) $row['id'];
        }
        $stmt = $this->pdo->prepare('INSERT INTO categories (name) VALUES (?)');
        $stmt->execute([$name]);
        return (int) $this->pdo->lastInsertId();
    }

    private function syncTags(int $recipeId, array $tags): void
    {
        $this->pdo->prepare('DELETE FROM recipe_tags WHERE recipe_id = ?')->execute([$recipeId]);

        foreach ($tags as $name) {
            $name = trim($name);
            if ($name === '') {
                continue;
            }
            $stmt = $this->pdo->prepare('SELECT id FROM tags WHERE name = ?');
            $stmt->execute([$name]);
            $row   = $stmt->fetch();
            if ($row) {
                $tagId = (int) $row['id'];
            } else {
                $ins = $this->pdo->prepare('INSERT INTO tags (name) VALUES (?)');
                $ins->execute([$name]);
                $tagId = (int) $this->pdo->lastInsertId();
            }
            $this->pdo->prepare('INSERT IGNORE INTO recipe_tags (recipe_id, tag_id) VALUES (?, ?)')
                ->execute([$recipeId, $tagId]);
        }
    }
}
