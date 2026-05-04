<?php

declare(strict_types=1);

namespace App\Services;

use App\Database\NullRedisClient;
use App\Database\SafeRedis;
use App\Models\User;
use PDO;

/**
 * Сервис административных операций.
 *
 * Предоставляет методы для управления пользователями, рецептами, категориями,
 * тегами и получения системной статистики. Используется исключительно
 * страницами административного раздела /admin/.
 *
 * @package App\Services
 */
class AdminService
{
    /**
     * @param PDO                       $pdo   Соединение с MySQL
     * @param SafeRedis|NullRedisClient $redis Redis-клиент для статистики просмотров
     */
    public function __construct(
        private PDO                       $pdo,
        private SafeRedis|NullRedisClient $redis,
    ) {}

    // ── Dashboard ────────────────────────────────────────────────────────────

    /**
     * Возвращает сводную статистику для дашборда.
     *
     * @return array{
     *     total_users: int,
     *     admin_users: int,
     *     total_recipes: int,
     *     new_recipes_7d: int,
     *     total_categories: int,
     *     total_tags: int,
     *     redis_sessions: int
     * }
     */
    public function getDashboardStats(): array
    {
        $totalUsers  = (int) $this->pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
        $adminUsers  = (int) $this->pdo->query("SELECT COUNT(*) FROM users WHERE role='admin'")->fetchColumn();
        $totalRec    = (int) $this->pdo->query('SELECT COUNT(*) FROM recipes')->fetchColumn();
        $newRec7d    = (int) $this->pdo->query(
            "SELECT COUNT(*) FROM recipes WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)"
        )->fetchColumn();
        $totalCats   = (int) $this->pdo->query('SELECT COUNT(*) FROM categories')->fetchColumn();
        $totalTags   = (int) $this->pdo->query('SELECT COUNT(*) FROM tags')->fetchColumn();

        $sessionKeys = $this->redis->keys('session:*');
        $redisSessions = is_array($sessionKeys) ? count($sessionKeys) : 0;

        return [
            'total_users'      => $totalUsers,
            'admin_users'      => $adminUsers,
            'total_recipes'    => $totalRec,
            'new_recipes_7d'   => $newRec7d,
            'total_categories' => $totalCats,
            'total_tags'       => $totalTags,
            'redis_sessions'   => $redisSessions,
        ];
    }

    // ── Users ────────────────────────────────────────────────────────────────

    /**
     * Возвращает всех пользователей с количеством их рецептов.
     *
     * @return array<array{id:int,username:string,email:string,role:string,is_blocked:int,created_at:string,recipe_count:int}>
     */
    public function getAllUsers(): array
    {
        $stmt = $this->pdo->query(
            'SELECT u.id, u.username, u.email, u.role, u.is_blocked, u.created_at,
                    COUNT(r.id) AS recipe_count
             FROM users u
             LEFT JOIN recipes r ON r.user_id = u.id
             GROUP BY u.id
             ORDER BY u.created_at ASC'
        );
        return $stmt->fetchAll();
    }

    /**
     * Создаёт нового пользователя с заданной ролью.
     *
     * @param  string $username Имя пользователя
     * @param  string $email    Email
     * @param  string $password Пароль (хэшируется)
     * @param  string $role     'user' или 'admin'
     * @return User
     * @throws \PDOException При дублировании username/email
     */
    public function createUser(string $username, string $email, string $password, string $role): User
    {
        $hash = password_hash($password, PASSWORD_BCRYPT);
        $stmt = $this->pdo->prepare(
            'INSERT INTO users (username, email, password, role) VALUES (?, ?, ?, ?)'
        );
        $stmt->execute([$username, $email, $hash, $role]);

        $user             = new User();
        $user->id         = (int) $this->pdo->lastInsertId();
        $user->username   = $username;
        $user->email      = $email;
        $user->role       = $role;
        $user->created_at = date('Y-m-d H:i:s');
        return $user;
    }

    /**
     * Переключает роль пользователя: user ↔ admin.
     *
     * @param int $userId ID пользователя
     */
    public function toggleUserRole(int $userId): void
    {
        $this->pdo->prepare(
            "UPDATE users SET role = IF(role='admin','user','admin') WHERE id = ?"
        )->execute([$userId]);
    }

    /**
     * Переключает статус блокировки: 0 ↔ 1.
     *
     * @param int $userId ID пользователя
     */
    public function toggleUserBlock(int $userId): void
    {
        $this->pdo->prepare(
            'UPDATE users SET is_blocked = IF(is_blocked=1,0,1) WHERE id = ?'
        )->execute([$userId]);
    }

    /**
     * Удаляет пользователя.
     *
     * Нельзя удалить себя и последнего администратора.
     *
     * @param  int    $userId         ID удаляемого пользователя
     * @param  int    $currentAdminId ID текущего администратора (себя удалить нельзя)
     * @return string Пустая строка при успехе или сообщение об ошибке
     */
    public function deleteUser(int $userId, int $currentAdminId): string
    {
        if ($userId === $currentAdminId) {
            return 'Нельзя удалить собственную учётную запись.';
        }
        $adminCount = (int) $this->pdo->query("SELECT COUNT(*) FROM users WHERE role='admin'")->fetchColumn();
        $stmt = $this->pdo->prepare("SELECT role FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $row = $stmt->fetch();
        if ($row && $row['role'] === 'admin' && $adminCount <= 1) {
            return 'Нельзя удалить последнего администратора.';
        }
        $this->pdo->prepare('DELETE FROM users WHERE id = ?')->execute([$userId]);
        return '';
    }

    /**
     * Возвращает количество администраторов в системе.
     *
     * @return int
     */
    public function getAdminCount(): int
    {
        return (int) $this->pdo->query("SELECT COUNT(*) FROM users WHERE role='admin'")->fetchColumn();
    }

    // ── Recipes ──────────────────────────────────────────────────────────────

    /**
     * Возвращает все рецепты с фильтрацией по автору и категории.
     *
     * @param  int    $authorId   ID автора (0 = все)
     * @param  int    $categoryId ID категории (0 = все)
     * @return array<array{id:int,title:string,username:string,category:string,created_at:string}>
     */
    public function getAllRecipes(int $authorId = 0, int $categoryId = 0): array
    {
        $where  = [];
        $params = [];

        if ($authorId > 0) {
            $where[]  = 'r.user_id = ?';
            $params[] = $authorId;
        }
        if ($categoryId > 0) {
            $where[]  = 'r.category_id = ?';
            $params[] = $categoryId;
        }

        $sql = 'SELECT r.id, r.title, r.created_at, r.user_id,
                       COALESCE(u.username, r.author) AS username,
                       c.name AS category
                FROM recipes r
                LEFT JOIN users u ON u.id = r.user_id
                LEFT JOIN categories c ON c.id = r.category_id';
        if ($where) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $sql .= ' ORDER BY r.created_at DESC';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * Удаляет рецепт по ID.
     *
     * @param int $id ID рецепта
     */
    public function deleteRecipe(int $id): void
    {
        $this->pdo->prepare('DELETE FROM recipes WHERE id = ?')->execute([$id]);
    }

    /**
     * Возвращает список авторов (users с рецептами).
     *
     * @return array<array{id:int,username:string}>
     */
    public function getAuthors(): array
    {
        return $this->pdo->query(
            'SELECT DISTINCT u.id, u.username FROM users u
             INNER JOIN recipes r ON r.user_id = u.id
             ORDER BY u.username'
        )->fetchAll();
    }

    // ── Categories ───────────────────────────────────────────────────────────

    /**
     * Возвращает категории с количеством рецептов.
     *
     * @return array<array{id:int,name:string,recipe_count:int}>
     */
    public function getCategoriesWithCount(): array
    {
        return $this->pdo->query(
            'SELECT c.id, c.name, COUNT(r.id) AS recipe_count
             FROM categories c
             LEFT JOIN recipes r ON r.category_id = c.id
             GROUP BY c.id
             ORDER BY c.name'
        )->fetchAll();
    }

    /**
     * Создаёт новую категорию.
     *
     * @param  string $name Название категории
     * @throws \PDOException При дублировании имени
     */
    public function createCategory(string $name): void
    {
        $this->pdo->prepare('INSERT INTO categories (name) VALUES (?)')->execute([trim($name)]);
    }

    /**
     * Переименовывает категорию.
     *
     * @param int    $id   ID категории
     * @param string $name Новое название
     */
    public function renameCategory(int $id, string $name): void
    {
        $this->pdo->prepare('UPDATE categories SET name = ? WHERE id = ?')->execute([trim($name), $id]);
    }

    /**
     * Удаляет категорию, если в ней нет рецептов.
     *
     * @param  int    $id ID категории
     * @return string Пустая строка при успехе или сообщение об ошибке
     */
    public function deleteCategory(int $id): string
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM recipes WHERE category_id = ?');
        $stmt->execute([$id]);
        if ((int) $stmt->fetchColumn() > 0) {
            return 'В категории есть рецепты. Удалите или переместите рецепты сначала.';
        }
        $this->pdo->prepare('DELETE FROM categories WHERE id = ?')->execute([$id]);
        return '';
    }

    // ── Tags ─────────────────────────────────────────────────────────────────

    /**
     * Возвращает теги с количеством рецептов.
     *
     * @return array<array{id:int,name:string,recipe_count:int}>
     */
    public function getTagsWithCount(): array
    {
        return $this->pdo->query(
            'SELECT t.id, t.name, COUNT(rt.recipe_id) AS recipe_count
             FROM tags t
             LEFT JOIN recipe_tags rt ON rt.tag_id = t.id
             GROUP BY t.id
             ORDER BY t.name'
        )->fetchAll();
    }

    /**
     * Создаёт новый тег.
     *
     * @param  string $name Название тега
     * @throws \PDOException При дублировании имени
     */
    public function createTag(string $name): void
    {
        $this->pdo->prepare('INSERT INTO tags (name) VALUES (?)')->execute([trim($name)]);
    }

    /**
     * Переименовывает тег.
     *
     * @param int    $id   ID тега
     * @param string $name Новое название
     */
    public function renameTag(int $id, string $name): void
    {
        $this->pdo->prepare('UPDATE tags SET name = ? WHERE id = ?')->execute([trim($name), $id]);
    }

    /**
     * Удаляет тег и все связи в recipe_tags (cascade через FK).
     *
     * @param int $id ID тега
     */
    public function deleteTag(int $id): void
    {
        $this->pdo->prepare('DELETE FROM tags WHERE id = ?')->execute([$id]);
    }

    // ── System stats ─────────────────────────────────────────────────────────

    /**
     * Возвращает системную статистику: топ авторов, топ рецептов, распределение по категориям.
     *
     * @return array{
     *     top_authors: array,
     *     top_recipes_views: array,
     *     by_category: array,
     *     redis_keys: array
     * }
     */
    public function getSystemStats(): array
    {
        $topAuthors = $this->pdo->query(
            'SELECT u.username, COUNT(r.id) AS recipe_count
             FROM users u
             INNER JOIN recipes r ON r.user_id = u.id
             GROUP BY u.id
             ORDER BY recipe_count DESC
             LIMIT 10'
        )->fetchAll();

        $byCategory = $this->pdo->query(
            'SELECT c.name, COUNT(r.id) AS recipe_count
             FROM categories c
             LEFT JOIN recipes r ON r.category_id = c.id
             GROUP BY c.id
             ORDER BY recipe_count DESC'
        )->fetchAll();

        // Top recipes by Redis views
        $viewKeys = $this->redis->keys('recipe:*:views');
        $topViews = [];
        if (is_array($viewKeys) && !empty($viewKeys)) {
            $values = $this->redis->mget($viewKeys);
            $raw    = [];
            foreach ($viewKeys as $i => $key) {
                if (preg_match('/recipe:(\d+):views/', (string) $key, $m)) {
                    $raw[(int) $m[1]] = (int) ($values[$i] ?? 0);
                }
            }
            arsort($raw);
            $topIds = array_slice(array_keys($raw), 0, 10, true);
            if ($topIds) {
                $ph   = implode(',', array_fill(0, count($topIds), '?'));
                $stmt = $this->pdo->prepare(
                    "SELECT r.id, r.title, COALESCE(u.username, r.author) AS author
                     FROM recipes r LEFT JOIN users u ON u.id = r.user_id
                     WHERE r.id IN ({$ph})"
                );
                $stmt->execute(array_values($topIds));
                $recipeMap = [];
                foreach ($stmt->fetchAll() as $row) {
                    $recipeMap[(int) $row['id']] = $row;
                }
                foreach ($topIds as $id) {
                    if (isset($recipeMap[$id])) {
                        $topViews[] = array_merge($recipeMap[$id], ['views' => $raw[$id]]);
                    }
                }
            }
        }

        // All Redis keys with types
        $redisKeys = [];
        $allKeys   = $this->redis->keys('*');
        if (is_array($allKeys)) {
            foreach (array_slice($allKeys, 0, 100) as $key) {
                $redisKeys[] = ['key' => $key, 'type' => $this->redis->type($key)];
            }
        }

        return [
            'top_authors'       => $topAuthors,
            'top_recipes_views' => $topViews,
            'by_category'       => $byCategory,
            'redis_keys'        => $redisKeys,
        ];
    }
}
