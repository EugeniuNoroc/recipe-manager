<?php

declare(strict_types=1);

namespace App\Support;

use App\Database\NullRedisClient;
use App\Database\SafeRedis;

/**
 * CSRF-защита с Redis-хранилищем и SESSION-fallback.
 *
 * Токен хранится в Redis (ключ csrf:{session_id}) с TTL 1 час.
 * При недоступном Redis автоматически деградирует до $_SESSION['csrf_token'].
 * Защищает все POST-формы через hidden-поле _token.
 *
 * @package App\Support
 */
class Csrf
{
    /** @var SafeRedis|NullRedisClient|null Инжектируемый Redis-клиент */
    private static SafeRedis|NullRedisClient|null $redis = null;

    /**
     * Инжектирует Redis-клиент (вызывается в bootstrap.php после инициализации Redis).
     *
     * @param SafeRedis|NullRedisClient $redis
     */
    public static function setRedis(SafeRedis|NullRedisClient $redis): void
    {
        self::$redis = $redis;
    }

    /**
     * Возвращает текущий CSRF-токен, создавая его если необходимо.
     *
     * Приоритет хранилища: Redis → $_SESSION (при недоступном Redis).
     *
     * @return string Hex-строка токена (64 символа)
     */
    public static function token(): string
    {
        // Проверяем доступность Redis перед попыткой получить токен
        $alive = (self::$redis instanceof SafeRedis) && self::$redis->isAvailable();

        if ($alive) {
            // Ключ привязан к session_id, чтобы у каждой сессии был свой токен
            $key   = 'csrf:' . session_id();
            $token = self::$redis->get($key);
            // Повторная проверка доступности: Redis мог упасть в процессе вызова get()
            if (self::$redis->isAvailable()) {
                if ($token === null || $token === '') {
                    // Генерируем криптографически стойкий токен и сохраняем с TTL 1 час
                    $token = bin2hex(random_bytes(32));
                    self::$redis->setex($key, 3600, $token);
                }
                return (string) $token;
            }
        }

        // Деградируем на $_SESSION если Redis недоступен (Null Object или упал в процессе)
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    /**
     * Верифицирует CSRF-токен из POST-запроса.
     *
     * Использует hash_equals для защиты от timing-атак.
     *
     * @param  string $token Токен из $_POST['_token']
     * @return bool          true если токен корректен
     */
    public static function verify(string $token): bool
    {
        $expected = self::token();
        // hash_equals выполняет сравнение за постоянное время, исключая timing-атаки
        return $expected !== '' && hash_equals($expected, $token);
    }

    /**
     * Генерирует HTML hidden-поле с CSRF-токеном.
     *
     * @return string HTML: <input type="hidden" name="_token" value="...">
     */
    public static function field(): string
    {
        return '<input type="hidden" name="_token" value="'
            . htmlspecialchars(self::token(), ENT_QUOTES) . '">';
    }
}
