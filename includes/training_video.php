<?php
declare(strict_types=1);

/**
 * Eğitim videosu: http(s), /portal/uploads/... veya Windows UNC / yerel disk yolu.
 * UNC yolları tarayıcıda açılmaz; oynatma api/training-video.php üzerinden yapılır.
 */

function portalTrainingAppConfig(): array
{
    static $cfg = null;
    if ($cfg === null) {
        $cfg = require __DIR__ . '/../config/app.php';
    }

    return $cfg;
}

function portalTrainingBaseUrl(): string
{
    return rtrim((string)(portalTrainingAppConfig()['base_url'] ?? '/portal'), '/');
}

/** @return list<string> */
function trainingVideoAllowedExtensions(): array
{
    return ['mp4', 'webm', 'ogg', 'ogv'];
}

function normalizeTrainingUncPath(string $raw): string
{
    $u = trim($raw);
    if (preg_match('#^(\\\\|//)#', $u)) {
        $u = str_replace('/', '\\', $u);
        while (strlen($u) > 2 && $u[0] === '\\' && $u[1] === '\\' && $u[2] === '\\') {
            $u = '\\' . ltrim(substr($u, 2), '\\');
        }
        if (strpos($u, '\\\\') !== 0) {
            $u = '\\\\' . ltrim($u, '\\');
        }

        return $u;
    }

    return str_replace('/', '\\', $u);
}

function isTrainingVideoNetworkOrLocalPath(string $path): bool
{
    $p = trim($path);
    if ($p === '' || preg_match('#^https?://#i', $p)) {
        return false;
    }
    if (strpos($p, '/') === 0 && strpos($p, '//') !== 0) {
        return false;
    }

    return (bool)(preg_match('#^(\\\\|//)#', $p) || preg_match('#^[A-Za-z]:[\\\\/]#', $p));
}

/** @return list<string> */
function trainingVideoPathPrefixes(): array
{
    $list = portalTrainingAppConfig()['training_video_path_prefixes'] ?? [];
    if (!is_array($list)) {
        return [];
    }
    $out = [];
    foreach ($list as $prefix) {
        $p = trim((string)$prefix);
        if ($p !== '') {
            $out[] = normalizeTrainingUncPath($p);
        }
    }

    return $out;
}

function trainingVideoPathIsAllowed(string $normalizedPath): bool
{
    $ext = strtolower(pathinfo($normalizedPath, PATHINFO_EXTENSION));
    if (!in_array($ext, trainingVideoAllowedExtensions(), true)) {
        return false;
    }
    $prefixes = trainingVideoPathPrefixes();
    if ($prefixes === []) {
        return isTrainingVideoNetworkOrLocalPath($normalizedPath);
    }
    foreach ($prefixes as $prefix) {
        if (stripos($normalizedPath, $prefix) === 0) {
            return true;
        }
    }

    return false;
}

function normalizeTrainingVideoStoredUrl(string $raw): ?string
{
    $u = trim($raw);
    if ($u === '') {
        return null;
    }
    if (strpos($u, '/') === 0 && strpos($u, '//') !== 0) {
        return $u;
    }
    if (preg_match('#^https?://#i', $u)) {
        return filter_var($u, FILTER_VALIDATE_URL) ? $u : null;
    }
    if (isTrainingVideoNetworkOrLocalPath($u)) {
        $normalized = normalizeTrainingUncPath($u);
        if (preg_match('#^[A-Za-z]:#', $normalized)) {
            $normalized = str_replace('/', '\\', $normalized);
        }

        return trainingVideoPathIsAllowed($normalized) ? $normalized : null;
    }

    return null;
}

function portalTrainingVideoStreamUrl(int $trainingId): string
{
    return portalTrainingBaseUrl() . '/api/training-video.php?id=' . max(0, $trainingId);
}

function portalTrainingVideoOpenUrl(string $storedUrl, int $trainingId): string
{
    if (isTrainingVideoNetworkOrLocalPath($storedUrl) && $trainingId > 0) {
        return portalTrainingVideoStreamUrl($trainingId);
    }
    if (strpos($storedUrl, '/') === 0 && strpos($storedUrl, '//') !== 0) {
        return $storedUrl;
    }

    return $storedUrl;
}

function portalTrainingVideoEmbedSrc(string $storedUrl, int $trainingId): string
{
    if (isTrainingVideoNetworkOrLocalPath($storedUrl)) {
        return $trainingId > 0 ? portalTrainingVideoStreamUrl($trainingId) : '';
    }
    if (strpos($storedUrl, '/') === 0 && strpos($storedUrl, '//') !== 0) {
        return $storedUrl;
    }

    return portalIframeSrcUrl($storedUrl);
}

function portalTrainingVideoUseVideoTag(string $storedUrl): bool
{
    if (isTrainingVideoNetworkOrLocalPath($storedUrl)) {
        return true;
    }
    if (strpos($storedUrl, '/') === 0 && strpos($storedUrl, '//') !== 0) {
        return true;
    }

    return (bool)(preg_match('#^https?://#i', $storedUrl) && preg_match('#\.(mp4|webm|ogg|ogv)(\?|#|$)#i', $storedUrl));
}

function portalRenderTrainingVideoPlayer(array $training): void
{
    $id = (int)($training['id'] ?? 0);
    $url = (string)($training['video_url'] ?? '');
    $title = (string)($training['title'] ?? 'Eğitim videosu');
    $src = portalTrainingVideoEmbedSrc($url, $id);
    if ($src === '') {
        echo '<p class="item-sub">Video yolu geçersiz veya sunucudan okunamıyor.</p>';

        return;
    }
    if (portalTrainingVideoUseVideoTag($url)) {
        echo '<video class="training-video-player" controls preload="metadata" playsinline src="'
            . e($src) . '" title="' . e($title) . '">Tarayıcınız video oynatmayı desteklemiyor.</video>';
    } else {
        echo '<iframe class="survey-frame" src="' . e($src) . '" title="' . e($title)
            . '" loading="lazy" referrerpolicy="no-referrer" allowfullscreen></iframe>';
    }
}

function trainingVideoMimeType(string $filePath): string
{
    $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
    $map = [
        'mp4' => 'video/mp4',
        'webm' => 'video/webm',
        'ogg' => 'video/ogg',
        'ogv' => 'video/ogg',
    ];

    return $map[$ext] ?? 'application/octet-stream';
}

function streamTrainingVideoFile(string $filePath): void
{
    if (!is_file($filePath) || !is_readable($filePath)) {
        http_response_code(404);
        header('Content-Type: text/plain; charset=UTF-8');
        echo 'Video dosyası bulunamadı veya okunamıyor. (Ortak alan erişimini ve yolu kontrol edin.)';

        return;
    }

    $size = filesize($filePath);
    if ($size === false || $size < 1) {
        http_response_code(404);
        header('Content-Type: text/plain; charset=UTF-8');
        echo 'Video dosyası boş.';

        return;
    }

    $start = 0;
    $end = $size - 1;
    $length = $size;

    if (isset($_SERVER['HTTP_RANGE']) && preg_match('/bytes=(\d*)-(\d*)/', (string)$_SERVER['HTTP_RANGE'], $m)) {
        if ($m[1] !== '') {
            $start = (int)$m[1];
        }
        if ($m[2] !== '') {
            $end = (int)$m[2];
        }
        if ($start > $end || $start >= $size) {
            http_response_code(416);
            header("Content-Range: bytes */{$size}");

            return;
        }
        if ($end >= $size) {
            $end = $size - 1;
        }
        $length = $end - $start + 1;
        http_response_code(206);
        header("Content-Range: bytes {$start}-{$end}/{$size}");
    } else {
        http_response_code(200);
    }

    header('Content-Type: ' . trainingVideoMimeType($filePath));
    header('Accept-Ranges: bytes');
    header('Content-Length: ' . (string)$length);
    header('Cache-Control: private, max-age=3600');

    $fp = fopen($filePath, 'rb');
    if ($fp === false) {
        http_response_code(500);

        return;
    }
    fseek($fp, $start);
    $buffer = 8192;
    $sent = 0;
    while ($sent < $length && !feof($fp)) {
        $read = (int)min($buffer, $length - $sent);
        $chunk = fread($fp, $read);
        if ($chunk === false) {
            break;
        }
        echo $chunk;
        $sent += strlen($chunk);
        if (connection_aborted()) {
            break;
        }
    }
    fclose($fp);
}

function serveTrainingVideoById(PDO $pdo, int $id): void
{
    ensureEngagementTables($pdo);
    if ($id <= 0) {
        http_response_code(400);
        header('Content-Type: text/plain; charset=UTF-8');
        echo 'Geçersiz video.';

        return;
    }
    $row = getTrainingVideoById($pdo, $id);
    if ($row === []) {
        http_response_code(404);
        header('Content-Type: text/plain; charset=UTF-8');
        echo 'Kayıt bulunamadı.';

        return;
    }
    $path = (string)($row['video_url'] ?? '');
    if (!isTrainingVideoNetworkOrLocalPath($path)) {
        http_response_code(400);
        header('Content-Type: text/plain; charset=UTF-8');
        echo 'Bu kayıt ortak alan videosu değil.';

        return;
    }
    $normalized = normalizeTrainingUncPath($path);
    if (!trainingVideoPathIsAllowed($normalized)) {
        http_response_code(403);
        header('Content-Type: text/plain; charset=UTF-8');
        echo 'Video yolu izin verilen ortak alan altında değil.';

        return;
    }
    streamTrainingVideoFile($normalized);
}
