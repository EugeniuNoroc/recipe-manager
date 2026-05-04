<?php

declare(strict_types=1);

namespace App\Storage;

use App\Models\Recipe;
use PDO;

/**
 * Реализация StorageInterface для MySQL через PDO.
 *
 * Все SELECT-запросы используют BASE_SELECT с JOIN на categories и tags,
 * возвращая денормализованные данные для Recipe::fromArray().
 * Теги синхронизируются через таблицу recipe_tags (delete + re-insert).
 *
 * @package App\Storage
 */
class MySQLRecipeStorage implements StorageInterface
{
    /**
     * @param PDO $pdo Активное PDO-соединение
     */
    public function __construct(private PDO $pdo) {}

    /** @var string Базовый SELECT с JOIN на категории и теги */
    private const BASE_SELECT = '
        SELECT r.*, c.name AS category,
               GROUP_CONCAT(t.name ORDER BY t.name SEPARATOR \',\') AS tags
        FROM recipes r
        LEFT JOIN categories c ON r.category_id = c.id
        LEFT JOIN recipe_tags rt ON rt.recipe_id = r.id
        LEFT JOIN tags t ON t.id = rt.tag_id';

    /**
     * Возвращает все рецепты, отсортированные по updated_at DESC.
     *
     * @return Recipe[]
     */
    public function getAll(): array
    {
        $stmt = $this->pdo->query(
            self::BASE_SELECT . ' GROUP BY r.id ORDER BY r.updated_at DESC'
        );
        return array_map(fn($row) => Recipe::fromArray($this->mapRow($row)), $stmt->fetchAll());
    }

    /**
     * Возвращает отфильтрованный список рецептов.
     *
     * Оба параметра опциональны; объединяются через AND если оба заданы.
     *
     * @param  int     $categoryId ID категории (0 = без фильтра)
     * @param  string  $search     Поисковая строка (LIKE по title и ingredients)
     * @return Recipe[]
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

    /**
     * Ищет рецепт по первичному ключу.
     *
     * @param  int         $id ID рецепта
     * @return Recipe|null
     */
    public function getById(int $id): ?Recipe
    {
        $stmt = $this->pdo->prepare(
            self::BASE_SELECT . ' WHERE r.id = ? GROUP BY r.id'
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ? Recipe::fromArray($this->mapRow($row)) : null;
    }

    /**
     * Возвращает рецепты по списку ID в одном MGET-подобном запросе.
     *
     * @param  int[]    $ids Массив ID
     * @return Recipe[]
     */
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

    /**
     * Сохраняет новый рецепт; устанавливает $recipe->id после INSERT.
     *
     * Категория разрешается или создаётся через resolveCategoryId().
     * Теги синхронизируются через syncTags().
     *
     * @param  Recipe $recipe
     * @return bool
     */
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

    /**
     * Обновляет существующий рецепт (все поля кроме user_id и created_at).
     *
     * @param  Recipe $recipe Рецепт с заполненным id
     * @return bool
     */
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

    /**
     * Удаляет рецепт по ID. Теги удаляются автоматически через ON DELETE CASCADE.
     *
     * @param  int  $id
     * @return bool
     */
    public function delete(int $id): bool
    {
        return $this->pdo->prepare('DELETE FROM recipes WHERE id = ?')->execute([$id]);
    }

    /**
     * Сортирует массив рецептов в памяти.
     *
     * @param  Recipe[] $recipes
     * @param  string   $field   Поле для сортировки
     * @param  string   $dir     'asc' или 'desc'
     * @return Recipe[]
     */
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

    /**
     * Возвращает все категории.
     *
     * @return array<array{id:int,name:string}>
     */
    public function getCategories(): array
    {
        return $this->pdo->query('SELECT id, name FROM categories ORDER BY name')->fetchAll();
    }

    /**
     * Возвращает все теги.
     *
     * @return array<array{id:int,name:string}>
     */
    public function getTags(): array
    {
        return $this->pdo->query('SELECT id, name FROM tags ORDER BY name')->fetchAll();
    }

    /**
     * Преобразует строку результата SQL: разбивает GROUP_CONCAT тегов в массив.
     *
     * @param  array $row Строка из fetchAll()
     * @return array      Строка с $row['tags'] как array
     */
    private function mapRow(array $row): array
    {
        $row['tags'] = $row['tags'] ? explode(',', $row['tags']) : [];
        return $row;
    }

    /**
     * Возвращает ID категории по имени, создавая её при необходимости.
     *
     * @param  string $name Название категории
     * @return int          ID категории
     */
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

    /**
     * Синхронизирует теги рецепта: удаляет старые, вставляет новые.
     *
     * Теги создаются автоматически если не существуют.
     *
     * @param int      $recipeId ID рецепта
     * @param string[] $tags     Массив названий тегов
     */
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
