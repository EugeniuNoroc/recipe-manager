<?php

declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

unset($_SESSION['chaos_admin'], $_SESSION['chaos_admin_time'], $_SESSION['chaos_flash']);
session_regenerate_id(true);

header('Location: /admin/login.php');
exit;
