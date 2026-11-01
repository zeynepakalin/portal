<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

$app = require __DIR__ . '/../config/app.php';
$base = rtrim((string)($app['base_url'] ?? '/portal'), '/');
$cssFile = __DIR__ . '/../assets/css/main.css';
$cssVersion = is_file($cssFile) ? (string) filemtime($cssFile) : '1';
$error = '';

if (authIsAdmin()) {
    header('Location: ' . $base . '/admin/index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user = (string) ($_POST['username'] ?? '');
    $pass = (string) ($_POST['password'] ?? '');
    $expectUser = (string) ($app['admin_username'] ?? 'ikadmin');
    $expectPass = (string) ($app['admin_password'] ?? '');
    if (authVerifyAdminLogin($user, $pass, $expectUser, $expectPass)) {
        authSetAdmin(true, $expectUser);
        session_regenerate_id(true);
        header('Location: ' . $base . '/admin/index.php');
        exit;
    }
    $error = 'Kullanıcı adı veya şifre hatalı.';
}
?>
<!doctype html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Yönetici girişi — <?= e((string) ($app['app_name'] ?? 'Portal')) ?></title>
    <link rel="stylesheet" href="<?= e($base) ?>/assets/css/main.css?v=<?= e($cssVersion) ?>">
</head>
<body>
<div class="layout" style="min-height:100vh;display:flex;align-items:center;justify-content:center;padding:24px;">
    <div class="card" style="max-width:380px;width:100%;">
        <h1 style="margin:0 0 8px;font-size:18px;">Yönetici girişi</h1>
        <p class="item-sub" style="margin:0 0 16px;">Yönetim paneline yalnızca yetkili kullanıcı erişebilir.</p>
        <?php if ($error !== ''): ?>
            <p class="admin-flash item-sub" style="margin-bottom:12px;"><?= e($error) ?></p>
        <?php endif; ?>
        <form method="post" class="form">
            <label for="admin_user">Kullanıcı adı</label>
            <input type="text" id="admin_user" name="username" required autocomplete="username" autofocus placeholder="ikadmin">
            <label for="admin_pw">Şifre</label>
            <input type="password" id="admin_pw" name="password" required autocomplete="current-password">
            <button type="submit">Giriş</button>
        </form>
        <p class="item-sub" style="margin-top:14px;margin-bottom:0;">
            <a href="<?= e($base) ?>/index.php">Ana sayfaya dön</a>
        </p>
    </div>
</div>
</body>
</html>
