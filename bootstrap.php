<?php

declare(strict_types=1);

use App\Database\MySQLConnection;
use App\Database\NullRedisClient;
use App\Database\SafeRedis;
use App\Services\AuthService;
use App\Services\ChaosFlags;
use App\Services\FavoritesService;
use App\Services\RateLimiter;
use App\Services\SessionStore;
use App\Services\StatsService;
use App\Support\Csrf;
use App\Support\Flash;

require_once __DIR__ . '/vendor/autoload.php';

$config = require __DIR__ . '/config/config.php';

// ── ChaosFlags: must init before any service (file-based; survives Redis "outage") ─
ChaosFlags::init(__DIR__ . '/storage', $config['app']['env']);

// ── Trusted host check (warning-only; IP-direct access must keep working) ────
if ($config['app']['url'] !== '') {
    $expectedHost = parse_url($config['app']['url'], PHP_URL_HOST) ?? '';
    $actualHost   = $_SERVER['HTTP_HOST'] ?? '';
    if ($expectedHost !== '' && $actualHost !== '' && $actualHost !== $expectedHost) {
        error_log("[Host] Unexpected Host header: '{$actualHost}', expected '{$expectedHost}'");
    }
}

// Flash messages still live in $_SESSION; user identity lives in Redis.
// Cookie params set explicitly — secure:false is required for plain HTTP deployments.
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

// ── Redis with graceful degradation ──────────────────────────────────────────
// SafeRedis wraps the real client and catches connection errors on every call,
// so the app keeps working even if Redis dies after startup.
// ChaosFlags.isRedisDisabled() is also checked inside SafeRedis per-call.
try {
    $predis = new \Predis\Client(
        [
            'scheme'             => 'tcp',
            'host'               => $config['redis']['host'],
            'port'               => $config['redis']['port'],
            'timeout'            => 1.0,
            'read_write_timeout' => 1.0,
        ],
        ['exceptions' => true]
    );
    $predis->ping(); // force connect; throws on failure
    $redis = new SafeRedis($predis);
} catch (\Exception $e) {
    $redis = new NullRedisClient();
}

// ── MySQL with maintenance page fallback ──────────────────────────────────────
// A PDOException here can mean either real DB failure or ChaosFlags simulation.
// Either way, show the maintenance page and exit — don't white-screen.
try {
    $pdo = MySQLConnection::getInstance($config['mysql']);
} catch (\PDOException $e) {
    $appEnv        = $config['app']['env'];
    $status        = ChaosFlags::getStatus();
    $adminLoggedIn = false;
    http_response_code(503);
    require __DIR__ . '/templates/maintenance.php';
    exit;
}

// ── Services ─────────────────────────────────────────────────────────────────
$sessionStore = new SessionStore($redis);
$auth         = new AuthService($pdo, $sessionStore);
$favorites    = new FavoritesService($redis);
$stats        = new StatsService($redis);
$rateLimiter  = new RateLimiter($redis);

// ── CSRF (Redis-backed; SESSION fallback when Redis is down or goes down) ─────
Csrf::setRedis($redis);

// ── Blocked user guard ────────────────────────────────────────────────────────
// Fires on every request where bootstrap.php is loaded.
// Blocked users are force-logged-out immediately.
$_bootstrapUser = $auth->currentUser();
if ($_bootstrapUser !== null && $_bootstrapUser->isBlocked()) {
    Flash::error('Ваш аккаунт заблокирован.');
    $auth->logout();
    header('Location: /login.php');
    exit;
}
unset($_bootstrapUser);
