<?php
// Standalone — no header/footer (they depend on MySQL which is unavailable).
// Variables injected by bootstrap.php catch block: $appEnv, $status, $adminLoggedIn.
$appEnv        = $appEnv        ?? ($_ENV['APP_ENV'] ?? 'dev');
$adminLoggedIn = $adminLoggedIn ?? false;
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="refresh" content="5">
    <title>Сервис временно недоступен</title>
    <link rel="stylesheet"
          href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css"
          integrity="sha384-T3c6CoIi6uLrA9TneNEoa7RxnatzjcDSCmG1MXxSR1GAsXEV/Dwwykc2MPK8M2HN"
          crossorigin="anonymous">
</head>
<body class="bg-light">

<?php if ($appEnv === 'demo'): ?>
<div style="position:sticky;top:0;z-index:1030;background:#dc3545;color:#fff;
            padding:10px 16px;display:flex;align-items:center;
            justify-content:space-between;font-size:1rem;font-weight:600;">
    <span>⚠️ DEMO MODE: MySQL отключён</span>
    <?php if ($adminLoggedIn): ?>
        <a href="/admin/chaos.php" class="btn btn-light btn-sm ms-3 fw-semibold">
            Включить обратно
        </a>
    <?php else: ?>
        <a href="/admin/login.php"
           class="text-white text-decoration-none small ms-3 opacity-75">admin</a>
    <?php endif; ?>
</div>
<?php endif; ?>

<div class="d-flex align-items-center justify-content-center" style="min-height:90vh">
    <div class="container text-center" style="max-width:520px">
        <div class="display-1 mb-3">🔧</div>
        <h2 class="mb-2">Сервис временно недоступен</h2>
        <p class="text-muted">База данных недоступна. Страница обновится автоматически.</p>

        <?php if ($appEnv === 'demo'): ?>
        <div class="alert alert-warning mt-4 text-start">
            <strong>Это симуляция падения MySQL.</strong><br>
            <a href="/admin/chaos.php" class="alert-link">Перейти в Chaos Panel</a>, чтобы вернуть сервис.
        </div>
        <?php endif; ?>
    </div>
</div>

</body>
</html>
