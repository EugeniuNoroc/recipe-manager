<?php
// Standalone — no header/footer (they depend on MySQL which is unavailable)
$appEnv      = $appEnv ?? ($_ENV['APP_ENV'] ?? 'dev');
$status      = $status ?? [];
$secondsLeft = (int) ($status['mysql_seconds_left'] ?? 0);
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
<body class="bg-light d-flex align-items-center justify-content-center" style="min-height:100vh">
<div class="container text-center" style="max-width:520px">
    <div class="display-1 mb-3">🔧</div>
    <h2 class="mb-2">Сервис временно недоступен</h2>
    <p class="text-muted">База данных недоступна. Попробуйте через 30 секунд.</p>

    <?php if ($appEnv === 'demo'): ?>
    <div class="alert alert-warning mt-4 text-start">
        <strong>Это симуляция падения MySQL.</strong><br>
        <?php if ($secondsLeft > 0): ?>
            Восстановление через <strong><?= $secondsLeft ?></strong> сек.
        <?php endif; ?>
        <br>
        <a href="/admin/chaos.php" class="alert-link">Перейти в Chaos Panel</a>, чтобы вернуть сервис.
    </div>
    <?php endif; ?>
</div>
</body>
</html>
