<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/layout.php';
require_once __DIR__ . '/includes/repository.php';

$app = require __DIR__ . '/config/app.php';

ensureEngagementTables($pdo);

$basePath = trim((string)($app['base_url'] ?? '/'), '/');
$moodCookiePath = ($basePath === '') ? '/' : '/' . $basePath . '/';
$moodCookieExpiry = [
    'expires' => time() + (86400 * 365),
    'path' => $moodCookiePath,
    'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
    'httponly' => true,
    'samesite' => 'Lax',
];

$pollMessage = '';
$moodMessage = '';
$moodToken = (string)($_COOKIE['mood_respondent_token'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (string)($_POST['action'] ?? '') === 'mood_submit') {
    $mood = trim((string)($_POST['mood'] ?? ''));
    if ($moodToken === '') {
        $moodToken = bin2hex(random_bytes(20));
        setcookie('mood_respondent_token', $moodToken, $moodCookieExpiry);
    }
    if ($mood !== '') {
        $ok = submitMoodCheckin($pdo, $mood, $moodToken);
        $moodMessage = $ok ? 'Teşekkürler, yanıtınız kaydedildi.' : 'Bugün için zaten yanıt verdiniz.';
    } else {
        $moodMessage = 'Lütfen 1-5 arası bir puan seçin.';
    }
}

$hasMoodToday = hasMoodCheckinToday($pdo, $moodToken);
$moodCounts = getTodayMoodCounts($pdo);
$moodTotal = 0;
foreach ($moodCounts as $row) {
    $moodTotal += $row['count'];
}
$moodScoreKeys = getMoodScoreKeys();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (string)($_POST['action'] ?? '') === 'poll_vote') {
    $pollId = (int)($_POST['poll_id'] ?? 0);
    $optionId = (int)($_POST['option_id'] ?? 0);
    $token = (string)($_COOKIE['poll_voter_token'] ?? '');
    if ($token === '') {
        $token = bin2hex(random_bytes(20));
        setcookie('poll_voter_token', $token, time() + (86400 * 365), '/');
    }

    if ($pollId > 0 && $optionId > 0) {
        $ok = submitPollVote($pdo, $pollId, $optionId, $token);
        $pollMessage = $ok ? 'Oyunuz kaydedildi.' : 'Bu ankette daha önce oy kullanmış olabilirsiniz.';
    } else {
        $pollMessage = 'Geçersiz anket seçimi.';
    }
}

$stats = getDashboardStats($pdo);
$news = getNews($pdo, 5);
$birthdays = getBirthdays($pdo, 6);
$joiners = getNewJoiners($pdo, 6);
$menuWeekOffset = filter_input(INPUT_GET, 'menu_week', FILTER_VALIDATE_INT);
if ($menuWeekOffset === false || $menuWeekOffset === null) {
    $menuWeekOffset = 0;
}
$menuWeekOffset = max(-52, min(52, $menuWeekOffset));
$menuWeekStartTs = strtotime('monday this week');
if ($menuWeekOffset !== 0) {
    $menuWeekStartTs = strtotime((($menuWeekOffset > 0 ? '+' : '') . $menuWeekOffset . ' week'), $menuWeekStartTs);
}
$menuWeekStart = date('Y-m-d', $menuWeekStartTs);
$menuWeekEnd = date('Y-m-d', strtotime($menuWeekStart . ' +6 days'));
$weekMenus = getMenusForDateRange($pdo, $menuWeekStart, $menuWeekEnd);
$weekMenusByDate = [];
foreach ($weekMenus as $menuRow) {
    $menuDate = (string)($menuRow['menu_date'] ?? '');
    if ($menuDate !== '') {
        $weekMenusByDate[$menuDate] = $menuRow;
    }
}
$requestedMenuDay = trim((string)($_GET['menu_day'] ?? ''));
$isRequestedDayValid = (bool)preg_match('/^\d{4}-\d{2}-\d{2}$/', $requestedMenuDay)
    && ($requestedMenuDay >= $menuWeekStart && $requestedMenuDay <= $menuWeekEnd);
$todayYmd = date('Y-m-d');
$menuDay = $isRequestedDayValid ? $requestedMenuDay : (($todayYmd >= $menuWeekStart && $todayYmd <= $menuWeekEnd) ? $todayYmd : $menuWeekStart);
$menu = $weekMenusByDate[$menuDay] ?? [];
$menuDaysWithData = array_keys($weekMenusByDate);
sort($menuDaysWithData);
$menuPrevDay = null;
$menuNextDay = null;
if (!empty($menuDaysWithData)) {
    $currentIndex = array_search($menuDay, $menuDaysWithData, true);
    if ($currentIndex === false) {
        $menuDay = $menuDaysWithData[0];
        $menu = $weekMenusByDate[$menuDay] ?? [];
        $currentIndex = 0;
    }
    if ($currentIndex > 0) {
        $menuPrevDay = $menuDaysWithData[$currentIndex - 1];
    }
    if ($currentIndex < count($menuDaysWithData) - 1) {
        $menuNextDay = $menuDaysWithData[$currentIndex + 1];
    }
}
$losses = getLosses($pdo, 3);
$banner = getActiveAnnouncementBanner($pdo);
$upcomingEvents = getUpcomingEvents($pdo, 6);
$calendarYear = (int)date('Y');
$calendarMonth = (int)date('n');
$calendarEvents = getEventsForMonth($pdo, $calendarYear, $calendarMonth);
$activePoll = getActivePollWithOptions($pdo);
$token = (string)($_COOKIE['poll_voter_token'] ?? '');
$hasVotedActivePoll = !empty($activePoll) ? hasVotedOnPoll($pdo, (int)$activePoll['id'], $token) : false;
$activePollResults = (!empty($activePoll) && $hasVotedActivePoll) ? getPollResults($pdo, (int)$activePoll['id']) : [];
$latestSurvey = getLatestSurveyLink($pdo);
$latestTrainingVideo = getLatestTrainingVideo($pdo);

renderLayout('Dedem Mekatronik Portal', 'dashboard', function () use ($stats, $news, $birthdays, $joiners, $menu, $menuWeekOffset, $menuDay, $menuPrevDay, $menuNextDay, $losses, $app, $banner, $upcomingEvents, $calendarYear, $calendarMonth, $calendarEvents, $activePoll, $hasVotedActivePoll, $activePollResults, $pollMessage, $moodMessage, $hasMoodToday, $moodCounts, $moodTotal, $moodScoreKeys, $latestSurvey, $latestTrainingVideo): void {
    require __DIR__ . '/pages/dashboard.php';
});
