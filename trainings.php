<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/layout.php';
require_once __DIR__ . '/includes/repository.php';

ensureEngagementTables($pdo);

/**
 * @return string|null /portal/uploads/trainings/... veya başarısızsa null
 */
function saveTrainingVideoUpload(array $file): ?string
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        return null;
    }

    $maxBytes = 120 * 1024 * 1024; // 120 MB
    $size = (int)($file['size'] ?? 0);
    if ($size <= 0 || $size > $maxBytes) {
        return null;
    }

    $tmp = (string)($file['tmp_name'] ?? '');
    if ($tmp === '' || !is_uploaded_file($tmp)) {
        return null;
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = (string)$finfo->file($tmp);
    $allowed = [
        'video/mp4' => 'mp4',
        'video/webm' => 'webm',
        'video/ogg' => 'ogv',
    ];
    if (!isset($allowed[$mime])) {
        return null;
    }

    $name = bin2hex(random_bytes(16)) . '.' . $allowed[$mime];
    $dir = __DIR__ . '/uploads/trainings';
    if (!is_dir($dir) && !mkdir($dir, 0755, true)) {
        return null;
    }

    $dest = $dir . DIRECTORY_SEPARATOR . $name;
    if (!move_uploaded_file($tmp, $dest)) {
        return null;
    }

    $app = require __DIR__ . '/config/app.php';
    $base = rtrim((string)($app['base_url'] ?? '/portal'), '/');
    return $base . '/uploads/trainings/' . $name;
}

$message = '';
$isAdmin = function_exists('authIsAdmin') && authIsAdmin();

if ($isAdmin && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = trim((string)($_POST['training_action'] ?? ''));
    $title = trim((string)($_POST['training_title'] ?? ''));
    $url = trim((string)($_POST['training_video_url'] ?? ''));
    $details = trim((string)($_POST['training_details'] ?? ''));
    $uploadedVideoUrl = null;
    $uploadNote = '';
    $videoFile = $_FILES['training_video_file'] ?? null;
    if (is_array($videoFile) && ($videoFile['name'] ?? '') !== '') {
        if ((int)($videoFile['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
            $saved = saveTrainingVideoUpload($videoFile);
            if ($saved !== null) {
                $uploadedVideoUrl = $saved;
            } else {
                $uploadNote = 'Yüklenen video kabul edilmedi (mp4/webm/ogg, en fazla 120 MB). ';
            }
        } else {
            $uploadNote = 'Video yükleme sırasında hata oluştu. ';
        }
    }

    if ($action === 'add') {
        $finalUrl = $uploadedVideoUrl ?? $url;
        $storedUrl = normalizeTrainingVideoStoredUrl($finalUrl);
        if ($title !== '' && $storedUrl !== null) {
            addTrainingVideo($pdo, $title, $storedUrl, $details);
            $message = $uploadNote . 'Eğitim videosu eklendi.';
        } else {
            $message = $uploadNote . 'Başlık ve geçerli video yolu (http, /portal/... veya \\\\sunucu\\paylaşım\\...) veya dosya yükleme zorunludur.';
        }
    } elseif ($action === 'update') {
        $id = (int)($_POST['training_id'] ?? 0);
        $existing = $id > 0 ? getTrainingVideoById($pdo, $id) : [];
        $finalUrl = $uploadedVideoUrl ?? $url;
        if ($finalUrl === '' && !empty($existing['video_url'])) {
            $finalUrl = (string)$existing['video_url'];
        }
        $storedUrl = normalizeTrainingVideoStoredUrl($finalUrl);
        if ($id > 0 && $title !== '' && $storedUrl !== null) {
            updateTrainingVideo($pdo, $id, $title, $storedUrl, $details);
            $message = $uploadNote . 'Eğitim videosu güncellendi.';
        } else {
            $message = $uploadNote . 'Güncelleme için başlık ve geçerli video yolu veya dosya yükleme zorunludur.';
        }
    } elseif ($action === 'delete') {
        $id = (int)($_POST['training_id'] ?? 0);
        $message = ($id > 0 && deleteTrainingVideo($pdo, $id)) ? 'Eğitim videosu silindi.' : 'Silme başarısız.';
    }
}

$editId = $isAdmin ? (int)($_GET['edit'] ?? 0) : 0;
$editTraining = $editId > 0 ? getTrainingVideoById($pdo, $editId) : [];
$latestTraining = getLatestTrainingVideo($pdo);
$videos = getTrainingVideosAdmin($pdo, 50);

renderLayout('Eğitimler', 'trainings', function () use ($latestTraining, $videos, $isAdmin, $message, $editTraining): void {
    ?>
    <section class="grid">
        <?php if ($isAdmin): ?>
            <article class="card span-12">
                <h2><?= !empty($editTraining) ? 'Eğitim videosu düzenle' : 'Eğitim videosu ekle' ?></h2>
                <?php if ($message !== ''): ?>
                    <p class="item-sub"><?= e($message) ?></p>
                <?php endif; ?>
                <form method="post" class="form" enctype="multipart/form-data">
                    <?php if (!empty($editTraining)): ?>
                        <input type="hidden" name="training_action" value="update">
                        <input type="hidden" name="training_id" value="<?= (int)$editTraining['id'] ?>">
                    <?php else: ?>
                        <input type="hidden" name="training_action" value="add">
                    <?php endif; ?>
                    <label>Başlık</label>
                    <input type="text" name="training_title" required maxlength="180" value="<?= e((string)($editTraining['title'] ?? '')) ?>">
                    <label>Video yolu (http/https, /portal/... veya ortak alan UNC)</label>
                    <input type="text" name="training_video_url" maxlength="1000" placeholder="\\sobek\ortak\...\video.mp4" value="<?= e((string)($editTraining['video_url'] ?? '')) ?>">
                    <p class="item-sub">Örnek: <code>\\sobek\ortak\ELEKTRIK-YAZILIM ORTAK\Saha Video\dosya.mp4</code> — https zorunlu değil.</p>
                    <label>Cihazdan video yükle (opsiyonel)</label>
                    <input type="file" name="training_video_file" accept="video/mp4,video/webm,video/ogg">
                    <label>Açıklama (opsiyonel)</label>
                    <textarea name="training_details" rows="3"><?= e((string)($editTraining['details'] ?? '')) ?></textarea>
                    <button type="submit"><?= !empty($editTraining) ? 'Güncelle' : 'Ekle' ?></button>
                </form>
                <?php if (!empty($editTraining)): ?>
                    <p class="item-sub"><a href="/portal/trainings.php">Düzenlemeyi iptal et</a></p>
                <?php endif; ?>
            </article>
        <?php endif; ?>

        <article class="card span-12 panel-poll">
            <div class="panel-birthdays-head">
                <h2>En son eklenen eğitim videosu</h2>
                <?php if (!empty($latestTraining)): ?>
                    <a class="card-head-link" href="<?= e(portalTrainingVideoOpenUrl((string)$latestTraining['video_url'], (int)$latestTraining['id'])) ?>" target="_blank" rel="noopener noreferrer">Yeni sekmede aç ›</a>
                <?php endif; ?>
            </div>
            <?php if (empty($latestTraining)): ?>
                <p class="item-sub">Henüz eğitim videosu eklenmedi.</p>
            <?php else: ?>
                <div class="item">
                    <p class="item-title"><?= e((string)$latestTraining['title']) ?></p>
                    <?php if (trim((string)$latestTraining['details']) !== ''): ?>
                        <p class="item-sub"><?= e((string)$latestTraining['details']) ?></p>
                    <?php endif; ?>
                    <p class="item-sub"><?= e(date('d.m.Y H:i', strtotime((string)$latestTraining['created_at']))) ?></p>
                </div>
                <?php portalRenderTrainingVideoPlayer($latestTraining); ?>
            <?php endif; ?>
        </article>

        <article class="card span-12">
            <h2>Tüm eğitim videoları</h2>
            <?php if (!$videos): ?>
                <p class="item-sub">Kayıt yok.</p>
            <?php endif; ?>
            <?php foreach ($videos as $video): ?>
                <div class="item admin-news-row">
                    <div class="admin-news-main">
                        <p class="item-title"><?= e((string)$video['title']) ?></p>
                        <?php if (trim((string)$video['details']) !== ''): ?>
                            <p class="item-sub"><?= e((string)$video['details']) ?></p>
                        <?php endif; ?>
                        <p class="item-sub">
                            <a href="<?= e(portalTrainingVideoOpenUrl((string)$video['video_url'], (int)$video['id'])) ?>" target="_blank" rel="noopener noreferrer"><?= e((string)$video['video_url']) ?></a>
                        </p>
                        <p class="item-sub"><?= e(date('d.m.Y H:i', strtotime((string)$video['created_at']))) ?></p>
                    </div>
                    <?php if ($isAdmin): ?>
                        <a class="card-head-link" href="/portal/trainings.php?edit=<?= (int)$video['id'] ?>" style="margin-right:8px">Düzenle</a>
                        <form method="post" class="admin-news-delete" onsubmit="return confirm('Bu eğitim videosu silinsin mi?');">
                            <input type="hidden" name="training_action" value="delete">
                            <input type="hidden" name="training_id" value="<?= (int)$video['id'] ?>">
                            <button type="submit" class="btn-danger">Sil</button>
                        </form>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </article>
    </section>
    <?php
});
