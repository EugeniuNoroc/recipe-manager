<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/vendor/autoload.php';

$config = require dirname(__DIR__, 2) . '/config/config.php';

use App\Services\ChaosFlags;

ChaosFlags::init(dirname(__DIR__, 2) . '/storage', $config['app']['env']);

if ($config['app']['env'] !== 'demo') {
    http_response_code(404);
    exit('Not Found');
}

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => $config['app']['cookie_secure'],
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

if (isset($_SESSION['chaos_admin']) && $_SESSION['chaos_admin'] === true
    && isset($_SESSION['chaos_admin_time'])
    && (time() - (int) $_SESSION['chaos_admin_time']) < 3600
) {
    header('Location: /admin/chaos.php');
    exit;
}

// File-based rate limiter (not Redis — Redis may be disabled via chaos)
function chaosRateLimit(string $ip, string $storageDir): bool
{
    $dir  = $storageDir . '/admin_rl';
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    $file = $dir . '/' . md5($ip) . '.json';
    $now  = time();
    $data = [];
    if (file_exists($file)) {
        $raw  = file_get_contents($file);
        $data = json_decode((string) $raw, true) ?? [];
    }
    $data = array_values(array_filter($data, static fn($t) => $t > $now - 60));
    if (count($data) >= 3) {
        return false;
    }
    $data[] = $now;
    file_put_contents($file, json_encode($data), LOCK_EX);
    return true;
}

$error      = '';
$csrfToken  = $_SESSION['chaos_csrf'] ?? ($_SESSION['chaos_csrf'] = bin2hex(random_bytes(16)));
$storageDir = dirname(__DIR__, 2) . '/storage';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postedCsrf = $_POST['csrf'] ?? '';
    if (!hash_equals($csrfToken, $postedCsrf)) {
        $error = 'Неверный CSRF-токен.';
    } elseif (!chaosRateLimit($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0', $storageDir)) {
        $error = 'Слишком много попыток. Подождите 1 минуту.';
    } else {
        $password       = $_POST['password'] ?? '';
        $correctPassword = $config['app']['chaos_admin_password'];
        if (hash_equals($correctPassword, $password)) {
            $_SESSION['chaos_admin']      = true;
            $_SESSION['chaos_admin_time'] = time();
            session_regenerate_id(true);
            header('Location: /admin/chaos.php');
            exit;
        }
        $error = 'Неверный пароль.';
    }
}

?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chaos Panel — Вход</title>
    <link rel="stylesheet"
          href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css"
          integrity="sha384-T3c6CoIi6uLrA9TneNEoa7RxnatzjcDSCmG1MXxSR1GAsXEV/Dwwykc2MPK8M2HN"
          crossorigin="anonymous">
</head>
<body class="bg-light d-flex align-items-center justify-content-center" style="min-height:100vh">
<div class="card shadow-sm" style="width:380px">
    <div class="card-body p-4">
        <h4 class="card-title mb-1">🛠 Chaos Admin</h4>
        <p class="text-muted small mb-4">Только для демонстрационного окружения</p>

        <?php if ($error): ?>
            <div class="alert alert-danger py-2 small"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST">
            <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrfToken) ?>">
            <div class="mb-3">
                <label class="form-label fw-semibold">Пароль</label>
                <input type="password" name="password" class="form-control" autofocus required>
            </div>
            <button type="submit" class="btn btn-danger w-100">Войти в панель</button>
        </form>

        <div class="mt-3 text-center">
            <a href="/" class="text-muted small">← Вернуться на сайт</a>
        </div>
    </div>
</div>
</body>
</html>
