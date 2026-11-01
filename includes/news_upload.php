<?php
declare(strict_types=1);

/**
 * Yapıştırılan görsel adresini normalize eder (Google vb. uzun URL'ler için).
 */
function normalizeNewsImageUrl(string $raw): ?string
{
    $u = trim($raw);
    $u = trim($u, " \t\n\r\0\x0B'\"");
    if ($u === '') {
        return null;
    }

    if (strncmp($u, '//', 2) === 0) {
        $u = 'https:' . $u;
    }
    if (!preg_match('#^https?://#i', $u)) {
        $u = 'https://' . $u;
    }

    if (strlen($u) > 8000) {
        return null;
    }

    if (filter_var($u, FILTER_VALIDATE_URL) !== false) {
        return $u;
    }

    if (preg_match('#^https?://\S+$#u', $u) === 1) {
        return $u;
    }

    return null;
}

/**
 * @return string|null Web yolu (/portal/uploads/news/...) veya başarısızsa null
 */
function saveNewsImageUpload(array $file): ?string
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        return null;
    }

    $maxBytes = 3 * 1024 * 1024;
    if (($file['size'] ?? 0) > $maxBytes) {
        return null;
    }

    $tmp = (string)($file['tmp_name'] ?? '');
    if ($tmp === '' || !is_uploaded_file($tmp)) {
        return null;
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($tmp);
    $allowed = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/gif' => 'gif',
    ];
    if (!isset($allowed[$mime])) {
        return null;
    }

    $ext = $allowed[$mime];
    $name = bin2hex(random_bytes(16)) . '.' . $ext;
    $dir = __DIR__ . '/../uploads/news';
    if (!is_dir($dir) && !mkdir($dir, 0755, true)) {
        return null;
    }

    $dest = $dir . DIRECTORY_SEPARATOR . $name;
    if (!move_uploaded_file($tmp, $dest)) {
        return null;
    }

    $app = require __DIR__ . '/../config/app.php';
    $base = rtrim((string)($app['base_url'] ?? '/portal'), '/');

    return $base . '/uploads/news/' . $name;
}

/**
 * Haber silinirken yalnızca bu sunucuya yüklenen dosyayı kaldırır (harici URL’lere dokunmaz).
 */
function deleteLocalNewsImageIfAny(?string $imageUrl): void
{
    if ($imageUrl === null || $imageUrl === '') {
        return;
    }

    $app = require __DIR__ . '/../config/app.php';
    $base = rtrim((string)($app['base_url'] ?? '/portal'), '/');
    $needle = $base . '/uploads/news/';
    if (strncmp($imageUrl, $needle, strlen($needle)) !== 0) {
        return;
    }

    $pathPart = parse_url($imageUrl, PHP_URL_PATH);
    if (!is_string($pathPart) || $pathPart === '') {
        return;
    }

    $name = basename($pathPart);
    if ($name === '' || strpos($name, '..') !== false) {
        return;
    }

    $dir = realpath(__DIR__ . '/../uploads/news');
    if ($dir === false) {
        return;
    }

    $full = realpath($dir . DIRECTORY_SEPARATOR . $name);
    $dirPrefix = $dir . DIRECTORY_SEPARATOR;
    if ($full === false || strncmp($full, $dirPrefix, strlen($dirPrefix)) !== 0) {
        return;
    }

    if (is_file($full)) {
        @unlink($full);
    }
}
