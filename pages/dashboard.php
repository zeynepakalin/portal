<?php if (!empty($banner)): ?>
    <section class="banner banner--<?= e($banner['level']) ?>">
        <strong><?= e($banner['title']) ?></strong>
        <span><?= e($banner['message']) ?></span>
    </section>
<?php endif; ?>

<?php
$moodEmoji = [
    '1' => '😔',
    '2' => '😕',
    '3' => '😐',
    '4' => '🙂',
    '5' => '😄',
];
$moodFormAction = rtrim((string)($app['base_url'] ?? '/portal'), '/') . '/index.php';
?>

<section class="grid">
    <article class="card span-8 card-elevated">
        <div class="card-head">
            <h2>Haberler ve duyurular</h2>
            <a class="card-head-link" href="/portal/news.php">Tümü ›</a>
        </div>
        <?php if (!$news): ?>
            <p class="item-sub">Henüz haber eklenmedi.</p>
        <?php endif; ?>
        <?php if ($news): ?>
            <div class="news-carousel" data-news-carousel>
                <div class="news-track" data-news-track>
                    <?php foreach ($news as $item): ?>
                        <article class="news-slide">
                            <img src="<?= e($item['image_url']) ?>" alt="<?= e($item['title']) ?>" loading="lazy">
                            <div class="news-slide-overlay">
                                <span class="badge"><?= e($item['category']) ?></span>
                                <p class="item-title"><?= e($item['title']) ?></p>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
                <button type="button" class="carousel-btn prev" data-carousel-prev aria-label="Önceki haber">‹</button>
                <button type="button" class="carousel-btn next" data-carousel-next aria-label="Sonraki haber">›</button>
                <div class="carousel-dots" data-carousel-dots></div>
            </div>
        <?php endif; ?>
        <?php if ($news): ?>
            <?php $latest = $news[0]; ?>
            <div class="latest-news-box">
                <p class="latest-news-heading">Son haber / son duyuru</p>
                <div class="latest-news-row">
                    <span class="badge"><?= e($latest['category']) ?></span>
                    <span class="latest-news-date"><?= e(date('d.m.Y', strtotime($latest['published_at']))) ?></span>
                </div>
                <p class="item-title"><?= e($latest['title']) ?></p>
                <p class="item-sub"><?= e($latest['summary']) ?></p>
            </div>
        <?php endif; ?>
    </article>

    <?php
    $weatherCfg = $app['weather'] ?? [
        'latitude' => 38.634,
        'longitude' => 27.382,
        'address' => 'Yunusemre/Manisa',
    ];
    ?>
    <article
        class="card span-4 weather-card weather-card--loading"
        id="weather-card"
        data-lat="<?= e((string)$weatherCfg['latitude']) ?>"
        data-lon="<?= e((string)$weatherCfg['longitude']) ?>"
    >
        <div class="weather-card-body">
            <div class="weather-widget-tabs" role="tablist" aria-label="Kart sekmeleri">
                <button type="button" class="widget-tab is-active" data-widget-tab="weather" aria-selected="true">Hava durumu</button>
                <button type="button" class="widget-tab" data-widget-tab="market" aria-selected="false">Döviz / Altın</button>
            </div>

            <div class="weather-widget-scroller" id="weather-widget">
                <section class="widget-pane widget-pane--weather" data-widget-pane="weather">
                    <div class="weather-card-decoration" id="weather-symbol" aria-hidden="true">☀</div>
                    <h2 class="weather-card-title">Hava durumu</h2>
                    <p class="weather-address"><?= e($weatherCfg['address']) ?></p>
                    <p class="weather-temp" id="weather-status">…</p>
                    <p class="weather-condition" id="weather-condition"></p>
                    <p class="weather-meta" id="weather-desc">Güncelleniyor…</p>
                    <div class="weather-forecast" id="weather-forecast"></div>
                </section>

                <section class="widget-pane widget-pane--market" data-widget-pane="market">
                    <div class="market-tabs" role="tablist" aria-label="Piyasa verileri">
                        <button type="button" class="market-tab is-active" data-market-tab="fx" aria-selected="true">Döviz</button>
                        <button type="button" class="market-tab" data-market-tab="gold" aria-selected="false">Altın</button>
                    </div>
                    <div class="market-pane" data-market-pane="fx">
                        <div class="market-row"><span class="market-label"><i class="market-icon" aria-hidden="true">$</i>USD/TRY</span><strong id="fx-usdtry">--</strong></div>
                        <div class="market-row"><span class="market-label"><i class="market-icon" aria-hidden="true">€</i>EUR/TRY</span><strong id="fx-eurtry">--</strong></div>
                        <div class="market-row"><span class="market-label"><i class="market-icon" aria-hidden="true">£</i>GBP/TRY</span><strong id="fx-gbptry">--</strong></div>
                    </div>
                    <div class="market-pane" data-market-pane="gold" hidden>
                        <div class="market-row"><span class="market-label"><i class="market-icon market-icon--gold" aria-hidden="true">Au</i>Gram Altın</span><strong id="gold-gram">--</strong></div>
                        <div class="market-row"><span class="market-label"><i class="market-icon market-icon--gold" aria-hidden="true">Au</i>Çeyrek Altın</span><strong id="gold-quarter">--</strong></div>
                        <div class="market-row"><span class="market-label"><i class="market-icon market-icon--gold" aria-hidden="true">Au</i>Cumhuriyet</span><strong id="gold-republic">--</strong></div>
                    </div>
                </section>
            </div>
            <section class="mood-checkin mood-checkin-inline">
                <div class="mood-checkin-head">
                    <h2>Bugün nasıl hissediyorsunuz?</h2>
                </div>
                <?php if ($moodMessage !== ''): ?>
                    <p class="mood-checkin-flash"><?= e($moodMessage) ?></p>
                <?php endif; ?>
                <?php if (!$hasMoodToday): ?>
                    <form method="post" class="mood-checkin-form mood-checkin-form--scores" action="<?= e($moodFormAction) ?>">
                        <input type="hidden" name="action" value="mood_submit">
                        <?php foreach ($moodScoreKeys as $score): ?>
                            <button type="submit" name="mood" value="<?= e($score) ?>" class="mood-btn mood-btn--score" title="Puan <?= e($score) ?>">
                                <span class="mood-btn-emoji" aria-hidden="true"><?= e($moodEmoji[$score] ?? '•') ?></span>
                                <span class="mood-btn-label mood-btn-label--score"><?= e($score) ?></span>
                            </button>
                        <?php endforeach; ?>
                    </form>
                <?php endif; ?>
                <?php if ($moodTotal > 0): ?>
                    <p class="item-sub mood-checkin-summary">Bugün toplam <?= (int)$moodTotal ?> yanıt</p>
                    <div class="mood-bars">
                        <?php foreach ($moodCounts as $row): ?>
                            <?php
                            $pct = $moodTotal > 0 ? round(100 * $row['count'] / $moodTotal) : 0;
                            $label = ($moodEmoji[$row['mood']] ?? '') . ' Puan ' . $row['mood'];
                            ?>
                            <div class="mood-bar-row">
                                <span class="mood-bar-label"><?= e($label) ?></span>
                                <div class="mood-bar-track">
                                    <div class="mood-bar-fill" style="width: <?= (int)$pct ?>%;"></div>
                                </div>
                                <span class="mood-bar-count"><?= (int)$row['count'] ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>
        </div>
    </article>

    <article class="card span-6 panel-events">
        <div class="panel-birthdays-head">
            <h2>Etkinlik takvimi</h2>
            <a class="card-head-link" href="/portal/events.php">Takvim görünümü ›</a>
        </div>
        <?php
        $monthNames = [1 => 'Ocak', 2 => 'Şubat', 3 => 'Mart', 4 => 'Nisan', 5 => 'Mayıs', 6 => 'Haziran', 7 => 'Temmuz', 8 => 'Ağustos', 9 => 'Eylül', 10 => 'Ekim', 11 => 'Kasım', 12 => 'Aralık'];
        $dayNames = ['Paz', 'Pzt', 'Sal', 'Çar', 'Per', 'Cum', 'Cmt'];
        $monthStart = strtotime(sprintf('%04d-%02d-01', $calendarYear, $calendarMonth));
        $firstWeekday = (int)date('N', $monthStart);
        $daysInMonth = (int)date('t', $monthStart);
        $eventMap = [];
        $holidayList = [];
        foreach ($calendarEvents as $ev) {
            $dayNum = (int)date('j', strtotime($ev['event_date']));
            $eventMap[$dayNum][] = $ev;
            if (($ev['category'] ?? '') === 'Resmi tatil') {
                $holidayList[] = $ev;
            }
        }
        ?>
        <div class="mini-cal-head">
            <span class="mini-cal-title"><?= e($monthNames[$calendarMonth] . ' ' . $calendarYear) ?></span>
        </div>
        <div class="mini-cal-grid">
            <?php foreach (['Pzt', 'Sal', 'Çar', 'Per', 'Cum', 'Cmt', 'Paz'] as $wd): ?>
                <div class="mini-cal-weekday"><?= e($wd) ?></div>
            <?php endforeach; ?>
            <?php for ($i = 1; $i < $firstWeekday; $i++): ?>
                <div class="mini-cal-cell mini-cal-cell--empty"></div>
            <?php endfor; ?>
            <?php for ($d = 1; $d <= $daysInMonth; $d++): ?>
                <?php
                $isToday = $d === (int)date('j') && $calendarMonth === (int)date('n') && $calendarYear === (int)date('Y');
                $hasEvent = !empty($eventMap[$d]);
                $hasHoliday = false;
                if ($hasEvent) {
                    foreach ($eventMap[$d] as $evt) {
                        if (($evt['category'] ?? '') === 'Resmi tatil') {
                            $hasHoliday = true;
                            break;
                        }
                    }
                }
                ?>
                <div class="mini-cal-cell<?= $isToday ? ' is-today' : '' ?><?= $hasHoliday ? ' is-holiday' : '' ?><?= $hasEvent ? ' has-event' : '' ?>">
                    <?= e((string)$d) ?>
                </div>
            <?php endfor; ?>
        </div>
        <div class="mini-cal-legend">
            <span><i class="dot dot-holiday"></i>Resmi tatil</span>
            <span><i class="dot dot-event"></i>Diğer etkinlik</span>
        </div>

        <h3 class="holiday-subhead">Bu ay resmi tatiller</h3>
        <?php if (empty($holidayList)): ?>
            <p class="item-sub panel-empty">Bu ay resmi tatil yok.</p>
        <?php else: ?>
            <?php foreach ($holidayList as $holiday): ?>
                <?php $weekday = $dayNames[(int)date('w', strtotime($holiday['event_date']))]; ?>
                <div class="holiday-row">
                    <strong><?= e($holiday['title']) ?></strong>
                    <span><?= e(date('d.m.Y', strtotime($holiday['event_date'])) . ' • ' . $weekday) ?></span>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </article>

    <article class="card span-6 panel-poll" id="panel-anketler">
        <div class="panel-birthdays-head">
            <h2>Anketler</h2>
            <?php if (!empty($latestSurvey)): ?>
                <a class="card-head-link" href="<?= e((string)$latestSurvey['survey_url']) ?>" target="_blank" rel="noopener noreferrer">Yeni sekmede aç ›</a>
            <?php endif; ?>
        </div>
        <?php if (empty($latestSurvey)): ?>
            <p class="item-sub panel-empty">Mevcut anket bulunamamıştır.</p>
        <?php else: ?>
            <div class="item">
                <p class="item-title"><?= e((string)$latestSurvey['title']) ?></p>
                <p class="item-sub">Yayın tarihi: <?= e(date('d.m.Y H:i', strtotime((string)$latestSurvey['created_at']))) ?></p>
            </div>
            <iframe
                class="survey-frame"
                src="<?= e(portalIframeSrcUrl((string)$latestSurvey['survey_url'])) ?>"
                title="<?= e((string)$latestSurvey['title']) ?>"
                loading="lazy"
                referrerpolicy="no-referrer"
            ></iframe>
        <?php endif; ?>
    </article>

    <article class="card span-6 panel-poll" id="panel-egitimler">
        <div class="panel-birthdays-head">
            <h2>Eğitim videoları</h2>
            <?php if (!empty($latestTrainingVideo)): ?>
                <a class="card-head-link" href="<?= e(portalTrainingVideoOpenUrl((string)$latestTrainingVideo['video_url'], (int)$latestTrainingVideo['id'])) ?>" target="_blank" rel="noopener noreferrer">Yeni sekmede aç ›</a>
            <?php endif; ?>
        </div>
        <?php if (empty($latestTrainingVideo)): ?>
            <p class="item-sub panel-empty">Henüz eğitim videosu eklenmedi.</p>
        <?php else: ?>
            <div class="item">
                <p class="item-title"><?= e((string)$latestTrainingVideo['title']) ?></p>
                <?php if (trim((string)$latestTrainingVideo['details']) !== ''): ?>
                    <p class="item-sub"><?= e((string)$latestTrainingVideo['details']) ?></p>
                <?php endif; ?>
                <p class="item-sub">Yayın tarihi: <?= e(date('d.m.Y H:i', strtotime((string)$latestTrainingVideo['created_at']))) ?></p>
            </div>
            <?php portalRenderTrainingVideoPlayer($latestTrainingVideo); ?>
        <?php endif; ?>
    </article>

    <article class="card span-6 panel-birthdays">
        <div class="panel-birthdays-head">
            <h2>Doğum günleri</h2>
            <span class="panel-tag">Bu ay</span>
        </div>
        <?php if (!$birthdays): ?>
            <p class="item-sub panel-empty">Bu ay kayıt bulunmuyor.</p>
        <?php else: ?>
            <div class="birthday-grid">
                <?php foreach ($birthdays as $person): ?>
                    <div class="birthday-card">
                        <div class="birthday-avatar" aria-hidden="true"><?= e(initialsFromName($person['full_name'])) ?></div>
                        <div class="birthday-info">
                            <p class="birthday-name"><?= e($person['full_name']) ?></p>
                            <p class="birthday-dept"><?= e($person['department']) ?></p>
                        </div>
                        <span class="birthday-date"><?= e(formatTurkishDayMonth($person['birth_date'])) ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </article>

    <article class="card span-6">
        <h2>Aramıza yeni katılanlar</h2>
        <?php if (!$joiners): ?>
            <p class="item-sub">Yeni katılım bulunmuyor.</p>
        <?php endif; ?>
        <?php foreach ($joiners as $joiner): ?>
            <div class="item">
                <p class="item-title"><?= e($joiner['full_name']) ?></p>
                <p class="item-sub"><?= e($joiner['role']) ?> - <?= e($joiner['department']) ?></p>
            </div>
        <?php endforeach; ?>
    </article>

    <article class="card span-6 panel-menu">
        <?php
        $menuPanelBase = rtrim((string)($app['base_url'] ?? '/portal'), '/') . '/index.php';
        $buildMenuNavUrl = static function (int $weekOffset, string $day, string $base): string {
            return $base . '?' . http_build_query([
                'menu_week' => $weekOffset,
                'menu_day' => $day,
            ]);
        };
        ?>
        <div class="panel-menu-head">
            <h2>Yemek menüsü</h2>
            <div class="panel-menu-nav">
                <?php if ($menuPrevDay !== null): ?>
                    <a class="menu-day-arrow" href="<?= e($buildMenuNavUrl((int)$menuWeekOffset, $menuPrevDay, $menuPanelBase)) ?>" aria-label="Önceki menü günü">‹</a>
                <?php else: ?>
                    <span class="menu-day-arrow is-disabled" aria-hidden="true">‹</span>
                <?php endif; ?>
                <span class="panel-tag"><?= e(date('d.m.Y', strtotime($menuDay))) ?></span>
                <?php if ($menuNextDay !== null): ?>
                    <a class="menu-day-arrow" href="<?= e($buildMenuNavUrl((int)$menuWeekOffset, $menuNextDay, $menuPanelBase)) ?>" aria-label="Sonraki menü günü">›</a>
                <?php else: ?>
                    <span class="menu-day-arrow is-disabled" aria-hidden="true">›</span>
                <?php endif; ?>
            </div>
        </div>
        <?php if (!$menu): ?>
            <p class="item-sub panel-empty">Seçilen gün için menü girilmedi.</p>
        <?php else: ?>
            <?php
            $menuIcons = ['🥣', '🍽️', '🍚', '🥗', '🍮'];
            $menuRows = [];
            $itemsJson = isset($menu['menu_items']) ? trim((string)$menu['menu_items']) : '';
            if ($itemsJson !== '') {
                $decoded = json_decode($itemsJson, true);
                if (is_array($decoded)) {
                    foreach ($decoded as $i => $line) {
                        $value = trim((string)$line);
                        if ($value === '' || $value === '-') {
                            continue;
                        }
                        $icon = $menuIcons[$i % count($menuIcons)];
                        $menuRows[] = ['', $value, $icon];
                    }
                }
            }
            if ($menuRows === []) {
                $menuRowsRaw = [
                    ['Çorba', $menu['soup'], '🥣'],
                    ['Ana yemek', $menu['main_course'], '🍽️'],
                    ['Yardımcı', $menu['side_dish'], '🍚'],
                    ['', $menu['salad'], '🥗'],
                    ['', $menu['dessert'], '🍮'],
                ];
                $menuRows = array_values(array_filter($menuRowsRaw, static function (array $row): bool {
                    $value = trim((string)$row[1]);
                    return $value !== '' && $value !== '-';
                }));
            }
            ?>
            <ul class="menu-list">
                <?php foreach ($menuRows as $row): ?>
                    <li class="menu-row">
                        <span class="menu-icon" aria-hidden="true"><?= $row[2] ?></span>
                        <div class="menu-text">
                            <?php if ($row[0] !== ''): ?>
                                <span class="menu-label"><?= e($row[0]) ?></span>
                            <?php endif; ?>
                            <span class="menu-value"><?= e($row[1]) ?></span>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </article>

    <article class="card span-6 loss">
        <h2>Acı kaybımız</h2>
        <?php if (!$losses): ?>
            <p class="item-sub">Yayınlanan acı kaybı bildirimi bulunmuyor.</p>
        <?php endif; ?>
        <?php foreach ($losses as $loss): ?>
            <div class="item">
                <span class="badge">Başsağlığı</span>
                <p class="item-title"><?= e($loss['full_name']) ?></p>
                <p class="item-sub"><?= e($loss['relation_info']) ?></p>
                <p class="item-sub"><?= e($loss['message']) ?></p>
            </div>
        <?php endforeach; ?>
    </article>

    <article class="card span-12 panel-stats">
        <h2>Genel özet</h2>
        <div class="stats-grid">
            <div class="stat-box">
                <div class="stat-label">Toplam haber</div>
                <div class="stat-value"><?= e((string)$stats['news']) ?></div>
            </div>
            <div class="stat-box">
                <div class="stat-label">Bu ay doğum günü</div>
                <div class="stat-value"><?= e((string)$stats['birthdays']) ?></div>
            </div>
            <div class="stat-box">
                <div class="stat-label">Bu ay yeni katılan</div>
                <div class="stat-value"><?= e((string)$stats['joiners']) ?></div>
            </div>
        </div>
    </article>
</section>

