<?php
/**
 * Боковая навигация для административного раздела.
 *
 * Использование в admin-страницах:
 *   $pageTitle = '...';
 *   require dirname(__DIR__, 2) . '/templates/header.php';
 *   ?><div class="d-flex gap-4 align-items-start"><?php
 *   require dirname(__DIR__, 2) . '/templates/admin_layout.php';
 *   ?><div class="flex-grow-1"> ... контент ... </div></div><?php
 *   require dirname(__DIR__, 2) . '/templates/footer.php';
 */
$_adminPage = basename($_SERVER['PHP_SELF']);

if (!function_exists('adminNavLink')) {
function adminNavLink(string $href, string $icon, string $label, string $current): string
{
    $file   = basename($href);
    $active = ($file === $current) ? ' active' : '';
    return '<a href="' . $href . '" class="list-group-item list-group-item-action d-flex align-items-center gap-2' . $active . '">'
        . $icon . ' ' . htmlspecialchars($label)
        . '</a>';
}
}
?>
<nav class="flex-shrink-0" style="min-width:190px">
    <div class="list-group shadow-sm small">
        <?= adminNavLink('/admin/index.php',        '📊', 'Дашборд',      $_adminPage) ?>
        <?= adminNavLink('/admin/users.php',        '👥', 'Пользователи', $_adminPage) ?>
        <?= adminNavLink('/admin/recipes.php',      '📋', 'Рецепты',      $_adminPage) ?>
        <?= adminNavLink('/admin/categories.php',   '🗂',  'Категории',    $_adminPage) ?>
        <?= adminNavLink('/admin/tags.php',         '🏷',  'Теги',         $_adminPage) ?>
        <?= adminNavLink('/admin/system_stats.php', '📈', 'Статистика',   $_adminPage) ?>
        <?= adminNavLink('/admin/chaos.php',        '⚡', 'Chaos Panel',  $_adminPage) ?>
    </div>
    <div class="mt-3">
        <a href="/index.php" class="btn btn-outline-secondary btn-sm w-100">← На сайт</a>
    </div>
</nav>
