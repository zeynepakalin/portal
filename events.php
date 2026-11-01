<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/layout.php';
require_once __DIR__ . '/includes/repository.php';

ensureEngagementTables($pdo);

$year = (int)($_GET['y'] ?? date('Y'));
$month = (int)($_GET['m'] ?? date('n'));
if ($year < 2020 || $year > 2100) {
    $year = (int)date('Y');
}
if ($month < 1 || $month > 12) {
    $month = (int)date('n');
}

$events = getEventsForMonth($pdo, $year, $month);
$eventsByDate = [];
foreach ($events as $event) {
    $eventsByDate[$event['event_date']][] = $event;
}
$nextHoliday = getNextOfficialHoliday($pdo);

$monthStart = strtotime(sprintf('%04d-%02d-01', $year, $month));
$daysInMonth = (int)date('t', $monthStart);
$firstWeekday = (int)date('N', $monthStart);

$prevTs = strtotime('-1 month', $monthStart);
$nextTs = strtotime('+1 month', $monthStart);

renderLayout('Etkinlik takvimi', 'events', function () use (
    $year,
    $month,
    $daysInMonth,
    $firstWeekday,
    $eventsByDate,
    $nextHoliday,
    $prevTs,
    $nextTs
): void {
    $monthNames = [1 => 'Ocak', 2 => 'Şubat', 3 => 'Mart', 4 => 'Nisan', 5 => 'Mayıs', 6 => 'Haziran', 7 => 'Temmuz', 8 => 'Ağustos', 9 => 'Eylül', 10 => 'Ekim', 11 => 'Kasım', 12 => 'Aralık'];
    ?>
    <section class="card calendar-shell">
        <div class="calendar-head">
            <a class="card-head-link" href="/portal/events.php?y=<?= e(date('Y', $prevTs)) ?>&m=<?= e(date('n', $prevTs)) ?>">‹ Önceki ay</a>
            <h2><?= e($monthNames[$month] . ' ' . $year) ?></h2>
            <a class="card-head-link" href="/portal/events.php?y=<?= e(date('Y', $nextTs)) ?>&m=<?= e(date('n', $nextTs)) ?>">Sonraki ay ›</a>
        </div>

        <div class="calendar-grid">
            <?php foreach (['Pzt', 'Sal', 'Çar', 'Per', 'Cum', 'Cmt', 'Paz'] as $dayName): ?>
                <div class="calendar-weekday"><?= e($dayName) ?></div>
            <?php endforeach; ?>

            <?php for ($i = 1; $i < $firstWeekday; $i++): ?>
                <div class="calendar-cell calendar-cell--empty"></div>
            <?php endfor; ?>

            <?php for ($d = 1; $d <= $daysInMonth; $d++): ?>
                <?php
                $dateKey = sprintf('%04d-%02d-%02d', $year, $month, $d);
                $isToday = $dateKey === date('Y-m-d');
                ?>
                <div class="calendar-cell<?= $isToday ? ' calendar-cell--today' : '' ?>">
                    <div class="calendar-day"><?= e((string)$d) ?></div>
                    <?php if (!empty($eventsByDate[$dateKey])): ?>
                        <?php foreach ($eventsByDate[$dateKey] as $event): ?>
                            <?php $holidayClass = $event['category'] === 'Resmi tatil' ? ' calendar-event--holiday' : ''; ?>
                            <div class="calendar-event<?= $holidayClass ?>">
                                <span class="badge"><?= e($event['category']) ?></span>
                                <p><?= e($event['title']) ?></p>
                                <?php if (!empty($event['event_time'])): ?>
                                    <small><?= e(substr((string)$event['event_time'], 0, 5)) ?></small>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            <?php endfor; ?>
        </div>
    </section>

    <section class="card next-holiday">
        <h2>En yakın tatil</h2>
        <?php if (!empty($nextHoliday)): ?>
            <p class="item-title"><?= e($nextHoliday['title']) ?></p>
            <p class="item-sub">
                <?= e(date('d.m.Y', strtotime($nextHoliday['event_date']))) ?>
                <?php if (!empty($nextHoliday['event_time'])): ?> · <?= e(substr((string)$nextHoliday['event_time'], 0, 5)) ?><?php endif; ?>
            </p>
            <p class="item-sub"><?= e($nextHoliday['details']) ?></p>
        <?php else: ?>
            <p class="item-sub">Yaklaşan resmi tatil bulunamadı.</p>
        <?php endif; ?>
    </section>
    <?php
});
