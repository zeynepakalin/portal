<?php
declare(strict_types=1);

function renderLayout(string $title, string $activeMenu, callable $content): void
{
    $app = require __DIR__ . '/../config/app.php';
    $cssFile = __DIR__ . '/../assets/css/main.css';
    $weatherJsFile = __DIR__ . '/../assets/js/weather.js';
    $tabsJsFile = __DIR__ . '/../assets/js/admin-tabs.js';
    $cssVersion = is_file($cssFile) ? (string)filemtime($cssFile) : '1';
    $weatherJsVersion = is_file($weatherJsFile) ? (string)filemtime($weatherJsFile) : '1';
    $tabsJsVersion = is_file($tabsJsFile) ? (string)filemtime($tabsJsFile) : '1';
    $base = rtrim((string)($app['base_url'] ?? '/portal'), '/');
    $menus = [
        'dashboard' => ['label' => 'Ana sayfa', 'url' => $base . '/index.php'],
        'events' => ['label' => 'Etkinlik takvimi', 'url' => $base . '/events.php'],
        'news' => ['label' => 'Haberler ve duyurular', 'url' => $base . '/news.php'],
        'surveys' => ['label' => 'Anketler', 'url' => $base . '/surveys.php'],
        'trainings' => ['label' => 'Eğitimler', 'url' => $base . '/trainings.php'],
        'directory' => [
            'label' => 'Rehber',
            'url' => 'https://rehber.dedemmekatronik.com/',
            'external' => true,
        ],
    ];
    if (function_exists('authIsAdmin') && authIsAdmin()) {
        $menus['admin'] = ['label' => 'Yönetim paneli', 'url' => $base . '/admin/index.php'];
    }
    ?>
    <!doctype html>
    <html lang="tr">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title><?= e($title) ?></title>
        <link rel="stylesheet" href="<?= e($base) ?>/assets/css/main.css?v=<?= e($cssVersion) ?>">
    </head>
    <body>
    <div class="layout">
        <aside class="sidebar">
            <div class="brand"><?= e($app['app_name']) ?></div>
            <div class="menu-title">Ana menü</div>
            <?php foreach ($menus as $key => $menu): ?>
                <a
                    class="nav-link <?= $activeMenu === $key ? 'active' : '' ?>"
                    href="<?= e($menu['url']) ?>"
                    <?php if (!empty($menu['external'])): ?>target="_blank" rel="noopener noreferrer"<?php endif; ?>
                >
                    <?= e($menu['label']) ?>
                </a>
            <?php endforeach; ?>
        </aside>

        <main class="content">
            <section class="topbar">
                <h1><?= e($title) ?></h1>
                <div class="meta topbar-meta">
                    <?php if (function_exists('authIsAdmin') && authIsAdmin()): ?>
                        <a class="topbar-logout" href="<?= e($base) ?>/admin/logout.php">Oturumu kapat</a>
                    <?php else: ?>
                        <a class="topbar-logout" href="<?= e($base) ?>/admin/login.php">Yönetici girişi</a>
                    <?php endif; ?>
                    <span class="topbar-date">Bugün: <?= e(date('d.m.Y H:i')) ?></span>
                </div>
            </section>
            <?php $content(); ?>
        </main>
    </div>
    <script src="<?= e($base) ?>/assets/js/weather.js?v=<?= e($weatherJsVersion) ?>"></script>
    <script src="<?= e($base) ?>/assets/js/admin-tabs.js?v=<?= e($tabsJsVersion) ?>"></script>
    </body>
    </html>
    <?php
}
