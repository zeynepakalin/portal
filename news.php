<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/layout.php';
require_once __DIR__ . '/includes/repository.php';

$news = getNews($pdo, 20);

renderLayout('Haberler ve duyurular', 'news', function () use ($news): void {
    ?>
    <section class="grid">
        <article class="card span-12">
            <h2>Tüm kayıtlar</h2>
            <?php if (!$news): ?>
                <p class="item-sub">Kayıt bulunmuyor.</p>
            <?php endif; ?>
            <?php foreach ($news as $item): ?>
                <div class="item">
                    <span class="badge"><?= e($item['category']) ?></span>
                    <p class="item-title"><?= e($item['title']) ?></p>
                    <p class="item-sub"><?= e($item['summary']) ?></p>
                    <p class="item-sub"><?= e(date('d.m.Y H:i', strtotime($item['published_at']))) ?></p>
                </div>
            <?php endforeach; ?>
        </article>
    </section>
    <?php
});
