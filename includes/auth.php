<?php
declare(strict_types=1);

function authStartSession(): void
{
    if (PHP_SAPI === 'cli') {
        return;
    }
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
}

function authIsAdmin(): bool
{
    return !empty($_SESSION['portal_admin']) && $_SESSION['portal_admin'] === true;
}

function authSetAdmin(bool $on, ?string $username = null): void
{
    if ($on) {
        $_SESSION['portal_admin'] = true;
        if ($username !== null && $username !== '') {
            $_SESSION['portal_admin_user'] = $username;
        }
    } else {
        unset($_SESSION['portal_admin'], $_SESSION['portal_admin_user']);
    }
}

/** Kullanıcı adı (küçük harfe normalize) ve şifre config ile eşleşmeli (intranet). */
function authVerifyAdminLogin(string $username, string $password, string $expectedUser, string $expectedPass): bool
{
    if ($expectedPass === '' || $expectedUser === '' || $password === '') {
        return false;
    }
    $u = strtolower(trim($username));
    $eu = strtolower(trim($expectedUser));
    if ($u === '' || !hash_equals($eu, $u)) {
        return false;
    }

    return hash_equals($expectedPass, $password);
}

function authRequireAdmin(): void
{
    if (authIsAdmin()) {
        return;
    }
    $app = require __DIR__ . '/../config/app.php';
    $base = rtrim((string)($app['base_url'] ?? '/portal'), '/');

    header('Location: ' . $base . '/admin/login.php');
    exit;
}
