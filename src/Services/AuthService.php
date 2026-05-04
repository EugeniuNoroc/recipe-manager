<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use App\Support\Flash;
use PDO;

/**
 * Сервис аутентификации и авторизации пользователей.
 *
 * Управляет регистрацией, входом, сессиями через Redis и проверкой прав.
 * Первый зарегистрированный пользователь автоматически получает роль admin.
 *
 * @package App\Services
 */
class AuthService
{
    /** @var User|null Кэш текущего пользователя в рамках запроса */
    private ?User $resolved = null;

    /**
     * @param PDO          $pdo          Соединение с MySQL
     * @param SessionStore $sessionStore Хранилище сессий (Redis)
     */
    public function __construct(
        private PDO          $pdo,
        private SessionStore $sessionStore,
    ) {}

    /**
     * Регистрирует нового пользователя.
     *
     * Если это первый пользователь в БД — автоматически назначает роль admin.
     *
     * @param  string $username Имя пользователя
     * @param  string $email    Email
     * @param  string $password Пароль в открытом виде (хэшируется через bcrypt)
     * @return User             Созданный пользователь
     * @throws \PDOException    При ошибке INSERT (дублирующийся username или email)
     */
    public function register(string $username, string $email, string $password): User
    {
        $hash = password_hash($password, PASSWORD_BCRYPT);

        $stmt = $this->pdo->prepare(
            'INSERT INTO users (username, email, password, role) VALUES (?, ?, ?, ?)'
        );
        $stmt->execute([$username, $email, $hash, 'user']);
        $newId = (int) $this->pdo->lastInsertId();

        // If this is the only user in the table, promote to admin.
        $total = (int) $this->pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
        $role  = 'user';
        if ($total === 1) {
            $this->pdo->prepare('UPDATE users SET role = ? WHERE id = ?')
                ->execute(['admin', $newId]);
            $role = 'admin';
        }

        $user             = new User();
        $user->id         = $newId;
        $user->username   = $username;
        $user->email      = $email;
        $user->role       = $role;
        $user->created_at = date('Y-m-d H:i:s');
        return $user;
    }

    /**
     * Выполняет вход по email и паролю.
     *
     * Возвращает null если пользователь не найден, пароль неверен или аккаунт заблокирован.
     *
     * @param  string    $email    Email пользователя
     * @param  string    $password Пароль в открытом виде
     * @return User|null           Пользователь при успехе, null при отказе
     */
    public function login(string $email, string $password): ?User
    {
        $stmt = $this->pdo->prepare('SELECT * FROM users WHERE email = ? LIMIT 1');
        $stmt->execute([$email]);
        $row = $stmt->fetch();
        if (!$row || !password_verify($password, $row['password'])) {
            return null;
        }
        if ((bool) ($row['is_blocked'] ?? false)) {
            return null;
        }
        return User::fromArray($row);
    }

    /**
     * Ищет пользователя по ID.
     *
     * @param  int       $id ID пользователя
     * @return User|null
     */
    public function findById(int $id): ?User
    {
        $stmt = $this->pdo->prepare('SELECT * FROM users WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ? User::fromArray($row) : null;
    }

    /**
     * Создаёт Redis-сессию и устанавливает httpOnly cookie.
     *
     * @param User $user Аутентифицированный пользователь
     */
    public function loginUser(User $user): void
    {
        $token = $this->sessionStore->create($user->id);
        setcookie('session_token', $token, [
            'expires'  => time() + 86400,
            'path'     => '/',
            'secure'   => false,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        $this->resolved = $user;
    }

    /**
     * Возвращает текущего аутентифицированного пользователя (lazy, кэшируется на запрос).
     *
     * Цепочка разрешения: cookie → Redis session → MySQL.
     *
     * @return User|null Пользователь или null если не аутентифицирован
     */
    public function currentUser(): ?User
    {
        if ($this->resolved !== null) {
            return $this->resolved;
        }
        $token = $_COOKIE['session_token'] ?? null;
        if (!$token) {
            return null;
        }
        $userId = $this->sessionStore->get($token);
        if (!$userId) {
            return null;
        }
        $this->resolved = $this->findById($userId);
        return $this->resolved;
    }

    /**
     * Проверяет, аутентифицирован ли текущий пользователь.
     *
     * @return bool
     */
    public function check(): bool
    {
        return $this->currentUser() !== null;
    }

    /**
     * Уничтожает Redis-сессию и очищает cookie.
     */
    public function logout(): void
    {
        $token = $_COOKIE['session_token'] ?? null;
        if ($token) {
            $this->sessionStore->destroy($token);
        }
        setcookie('session_token', '', [
            'expires'  => time() - 3600,
            'path'     => '/',
            'secure'   => false,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        $this->resolved = null;
    }

    /**
     * Требует аутентификации; перенаправляет на страницу входа если не залогинен.
     *
     * @param string $redirect URL для перенаправления (по умолчанию /login.php)
     */
    public function requireAuth(string $redirect = '/login.php'): void
    {
        if (!$this->check()) {
            header('Location: ' . $redirect);
            exit;
        }
    }

    /**
     * Требует роли admin; перенаправляет если не залогинен или недостаточно прав.
     *
     * Не залогинен → редирект на /login.php.
     * Залогинен, но не admin → flash-ошибка + редирект на /.
     *
     * @return User Текущий администратор
     */
    public function requireAdmin(): User
    {
        $user = $this->currentUser();
        if ($user === null) {
            header('Location: /login.php');
            exit;
        }
        if (!$user->isAdmin()) {
            Flash::error('Доступ запрещён. Требуются права администратора.');
            header('Location: /');
            exit;
        }
        return $user;
    }

    /**
     * Проверяет, является ли текущий пользователь администратором.
     *
     * @return bool
     */
    public function isAdmin(): bool
    {
        return ($this->currentUser()?->isAdmin()) ?? false;
    }
}
