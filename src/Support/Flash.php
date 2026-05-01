<?php

declare(strict_types=1);

namespace App\Support;

class Flash
{
    public static function set(string $type, string $message): void
    {
        $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
    }

    public static function success(string $msg): void { self::set('success', $msg); }
    public static function error(string $msg): void   { self::set('danger',  $msg); }
    public static function info(string $msg): void    { self::set('info',    $msg); }

    /** Reads and clears all pending messages */
    public static function all(): array
    {
        $msgs = $_SESSION['flash'] ?? [];
        unset($_SESSION['flash']);
        return $msgs;
    }

    public static function has(): bool
    {
        return !empty($_SESSION['flash']);
    }
}
