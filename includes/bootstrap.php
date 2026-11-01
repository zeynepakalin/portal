<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';
authStartSession();

if (PHP_SAPI !== 'cli' && !defined('PORTAL_API_REQUEST')) {
    header('Content-Type: text/html; charset=UTF-8');
}

$appConfig = require __DIR__ . '/../config/app.php';
$dbConfig = require __DIR__ . '/../config/database.php';

date_default_timezone_set($appConfig['timezone']);

$dsn = sprintf(
    'mysql:host=%s;port=%d;dbname=%s;charset=%s',
    $dbConfig['host'],
    $dbConfig['port'],
    $dbConfig['dbname'],
    $dbConfig['charset']
);

try {
    $pdo = new PDO($dsn, $dbConfig['username'], $dbConfig['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    $pdo->exec('SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci');
    try {
        require_once __DIR__ . '/repository.php';
        require_once __DIR__ . '/training_video.php';
        deleteExpiredFoodMenus($pdo);
    } catch (Throwable $e) {
        // tablo yoksa veya geçici hata: isteği düşürme
    }
} catch (PDOException $exception) {
    http_response_code(500);
    echo 'Veritabanı bağlantısı kurulamadı. Lütfen config/database.php ayarlarını kontrol edin.';
    exit;
}

function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

/** Doğum günü gibi tarihlerde ay adını Türkçe kısaltmayla gösterir (örn. 24 Mar). */
function formatTurkishDayMonth(string $mysqlDate): string
{
    $ts = strtotime($mysqlDate);
    if ($ts === false) {
        return '';
    }

    $months = [
        1 => 'Oca', 2 => 'Şub', 3 => 'Mar', 4 => 'Nis', 5 => 'May', 6 => 'Haz',
        7 => 'Tem', 8 => 'Ağu', 9 => 'Eyl', 10 => 'Eki', 11 => 'Kas', 12 => 'Ara',
    ];
    $m = (int) date('n', $ts);

    return date('d', $ts) . ' ' . ($months[$m] ?? '');
}

/** İsimden en fazla iki harflik baş harf (avatar yerine). */
function initialsFromName(string $name): string
{
    $parts = preg_split('/\s+/u', trim($name), -1, PREG_SPLIT_NO_EMPTY);
    $out = '';
    foreach (array_slice($parts, 0, 2) as $part) {
        $out .= mb_strtoupper(mb_substr($part, 0, 1, 'UTF-8'), 'UTF-8');
    }

    return $out !== '' ? $out : '?';
}
