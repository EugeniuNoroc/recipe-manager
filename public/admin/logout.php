<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/bootstrap.php';

use App\Support\Csrf;
use App\Support\Flash;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && Csrf::verify($_POST['_token'] ?? '')) {
    $auth->logout();
    Flash::info('Вы вышли из системы.');
}

header('Location: /login.php');
exit;
