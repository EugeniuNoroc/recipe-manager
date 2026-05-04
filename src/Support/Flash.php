<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Однократные flash-сообщения через сессию.
 *
 * Сообщения сохраняются в $_SESSION['flash'] и автоматически удаляются
 * при первом чтении через all(). Используются для PRG-паттерна.
 *
 * @package App\Support
 */
class Flash
{
    /**
     * Добавляет flash-сообщение заданного типа.
     *
     * @param string $type    Bootstrap-тип: 'success', 'danger', 'warning', 'info'
     * @param string $message Текст сообщения
     */
    public static function set(string $type, string $message): void
    {
        $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
    }

    /**
     * Добавляет сообщение об успехе (Bootstrap: success).
     *
     * @param string $msg
     */
    public static function success(string $msg): void { self::set('success', $msg); }

    /**
     * Добавляет сообщение об ошибке (Bootstrap: danger).
     *
     * @param string $msg
     */
    public static function error(string $msg): void   { self::set('danger',  $msg); }

    /**
     * Добавляет информационное сообщение (Bootstrap: info).
     *
     * @param string $msg
     */
    public static function info(string $msg): void    { self::set('info',    $msg); }

    /**
     * Читает и очищает все pending flash-сообщения.
     *
     * @return array<array{type:string,message:string}>
     */
    public static function all(): array
    {
        $msgs = $_SESSION['flash'] ?? [];
        unset($_SESSION['flash']);
        return $msgs;
    }

    /**
     * Проверяет, есть ли непрочитанные flash-сообщения.
     *
     * @return bool
     */
    public static function has(): bool
    {
        return !empty($_SESSION['flash']);
    }
}
