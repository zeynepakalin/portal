<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

authSetAdmin(false);
if (session_status() === PHP_SESSION_ACTIVE) {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'] ?? '', $p['secure'], $p['httponly']);
    }
    session_destroy();
}

$app = require __DIR__ . '/../config/app.php';
$base = rtrim((string)($app['base_url'] ?? '/portal'), '/');

header('Location: ' . $base . '/index.php');
exit;
