<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap.php';

use App\Support\Csrf;
use App\Support\Flash;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /index.php');
    exit;
}

if (!Csrf::verify($_POST['_token'] ?? '')) {
    Flash::error('Неверный CSRF-токен.');
    header('Location: /index.php');
    exit;
}

$auth->logout();
header('Location: /index.php');
exit;
