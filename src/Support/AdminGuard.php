<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\User;

/**
 * Защитник административных страниц (паттерн Guard).
 *
 * Статический хелпер для первой строки каждой admin-страницы.
 * Гарантирует, что bootstrap.php загружен и текущий пользователь — администратор.
 * При отсутствии прав выполняет редирект и завершает запрос.
 *
 * @package App\Support
 */
class AdminGuard
{
    /**
     * Проверяет права администратора для текущего запроса.
     *
     * Подключает bootstrap.php если он ещё не загружен (проверяет $GLOBALS['auth']),
     * затем вызывает AuthService::requireAdmin() — при отсутствии прав выполняется редирект.
     *
     * @return User Текущий администратор
     */
    public static function check(): User
    {
        if (!isset($GLOBALS['auth'])) {
            require_once dirname(__DIR__, 2) . '/bootstrap.php';
        }
        /** @var \App\Services\AuthService $auth */
        return $GLOBALS['auth']->requireAdmin();
    }
}
