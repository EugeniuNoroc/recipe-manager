<?php

declare(strict_types=1);

namespace App\Models;

/**
 * Модель пользователя системы.
 *
 * Представляет запись из таблицы users. Содержит данные об аутентификации,
 * роли и статусе блокировки.
 *
 * @package App\Models
 */
class User
{
    /** @var int Первичный ключ */
    public int    $id         = 0;

    /** @var string Уникальное имя пользователя */
    public string $username   = '';

    /** @var string Email-адрес (уникальный) */
    public string $email      = '';

    /** @var string Хэш пароля (bcrypt) */
    public string $password   = '';

    /** @var string Роль пользователя: 'user' или 'admin' */
    public string $role       = 'user';

    /** @var int Флаг блокировки: 1 — заблокирован, 0 — активен */
    public int    $is_blocked = 0;

    /** @var string Дата и время регистрации (TIMESTAMP) */
    public string $created_at = '';

    /**
     * Создаёт экземпляр из ассоциативного массива (результат PDO::fetch).
     *
     * @param  array $data Массив данных из БД
     * @return self
     */
    public static function fromArray(array $data): self
    {
        $user             = new self();
        $user->id         = (int)($data['id']         ?? 0);
        $user->username   = (string)($data['username'] ?? '');
        $user->email      = (string)($data['email']    ?? '');
        $user->password   = (string)($data['password'] ?? '');
        $user->role       = (string)($data['role']       ?? 'user');
        $user->is_blocked = (int)($data['is_blocked']  ?? 0);
        $user->created_at = (string)($data['created_at'] ?? '');
        return $user;
    }

    /**
     * Возвращает публичные поля пользователя в виде массива (без пароля).
     *
     * @return array<string,mixed>
     */
    public function toArray(): array
    {
        return [
            'id'         => $this->id,
            'username'   => $this->username,
            'email'      => $this->email,
            'role'       => $this->role,
            'is_blocked' => $this->is_blocked,
            'created_at' => $this->created_at,
        ];
    }

    /**
     * Проверяет, является ли пользователь администратором.
     *
     * @return bool
     */
    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    /**
     * Проверяет, заблокирован ли пользователь.
     *
     * @return bool
     */
    public function isBlocked(): bool
    {
        return $this->is_blocked === 1;
    }
}
