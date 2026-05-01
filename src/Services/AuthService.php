<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use PDO;

class AuthService
{
    private ?User $resolved = null;

    public function __construct(
        private PDO          $pdo,
        private SessionStore $sessionStore,
    ) {}

    // ── Registration / login (DB) ─────────────────────────────────────────────

    public function register(string $username, string $email, string $password): User
    {
        $hash = password_hash($password, PASSWORD_BCRYPT);
        $stmt = $this->pdo->prepare(
            'INSERT INTO users (username, email, password) VALUES (?, ?, ?)'
        );
        $stmt->execute([$username, $email, $hash]);

        $user             = new User();
        $user->id         = (int) $this->pdo->lastInsertId();
        $user->username   = $username;
        $user->email      = $email;
        $user->created_at = date('Y-m-d H:i:s');
        return $user;
    }

    public function login(string $email, string $password): ?User
    {
        $stmt = $this->pdo->prepare('SELECT * FROM users WHERE email = ? LIMIT 1');
        $stmt->execute([$email]);
        $row = $stmt->fetch();
        if (!$row || !password_verify($password, $row['password'])) {
            return null;
        }
        return User::fromArray($row);
    }

    public function findById(int $id): ?User
    {
        $stmt = $this->pdo->prepare('SELECT * FROM users WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ? User::fromArray($row) : null;
    }

    // ── Session / cookie ─────────────────────────────────────────────────────

    /** Creates Redis session + sets httpOnly cookie */
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

    /** Resolves user from cookie → Redis → MySQL (lazy, cached per request) */
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

    public function check(): bool
    {
        return $this->currentUser() !== null;
    }

    /** Removes Redis session key + clears cookie */
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

    public function requireAuth(string $redirect = '/login.php'): void
    {
        if (!$this->check()) {
            header('Location: ' . $redirect);
            exit;
        }
    }
}
