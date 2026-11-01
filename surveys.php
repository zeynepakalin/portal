<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/layout.php';
require_once __DIR__ . '/includes/repository.php';

ensureEngagementTables($pdo);

$message = '';
$isAdmin = function_exists('authIsAdmin') && authIsAdmin();

if ($isAdmin && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = trim((string)($_POST['survey_action'] ?? ''));
    $title = trim((string)($_POST['survey_title'] ?? ''));
    $url = trim((string)($_POST['survey_url'] ?? ''));
    $isValidUrl = $url !== '' && preg_match('#^https?://#i', $url) && filter_var($url, FILTER_VALIDATE_URL);

    if ($action === 'add') {
        if ($title !== '' && $isValidUrl) {
            addSurveyLink($pdo, $title, $url);
            $message = 'Anket bağlantısı eklendi.';
        } else {
            $message = 'Başlık ve geçerli http/https bağlantısı zorunludur.';
        }
    } elseif ($action === 'update') {
        $id = (int)($_POST['survey_id'] ?? 0);
        if ($id > 0 && $title !== '' && $isValidUrl) {
            updateSurveyLink($pdo, $id, $title, $url);
            $message = 'Anket bağlantısı güncellendi.';
        } else {
            $message = 'Güncelleme için başlık ve geçerli http/https bağlantısı zorunludur.';
        }
    } elseif ($action === 'delete') {
        $id = (int)($_POST['survey_id'] ?? 0);
        $message = ($id > 0 && deleteSurveyLink($pdo, $id)) ? 'Anket bağlantısı silindi.' : 'Silme başarısız.';
    }
}

$editId = $isAdmin ? (int)($_GET['edit'] ?? 0) : 0;
$editSurvey = $editId > 0 ? getSurveyLinkById($pdo, $editId) : [];
$latestSurvey = getLatestSurveyLink($pdo);
$surveys = getSurveyLinksAdmin($pdo, 50);

renderLayout('Anketler', 'surveys', function () use ($latestSurvey, $surveys, $isAdmin, $message, $editSurvey): void {
    ?>
    <section class="grid">
        <?php if ($isAdmin): ?>
            <article class="card span-12">
                <h2><?= !empty($editSurvey) ? 'Anket bağlantısı düzenle' : 'Anket bağlantısı ekle' ?></h2>
                <?php if ($message !== ''): ?>
                    <p class="item-sub"><?= e($message) ?></p>
                <?php endif; ?>
                <form method="post" class="form">
                    <?php if (!empty($editSurvey)): ?>
                        <input type="hidden" name="survey_action" value="update">
                        <input type="hidden" name="survey_id" value="<?= (int)$editSurvey['id'] ?>">
                    <?php else: ?>
                        <input type="hidden" name="survey_action" value="add">
                    <?php endif; ?>
                    <label>Başlık</label>
                    <input type="text" name="survey_title" required maxlength="180" value="<?= e((string)($editSurvey['title'] ?? '')) ?>">
                    <label>Anket bağlantısı (http/https)</label>
                    <input type="url" name="survey_url" required maxlength="1000" value="<?= e((string)($editSurvey['survey_url'] ?? '')) ?>">
                    <button type="submit"><?= !empty($editSurvey) ? 'Güncelle' : 'Ekle' ?></button>
                </form>
                <?php if (!empty($editSurvey)): ?>
                    <p class="item-sub"><a href="/portal/surveys.php">Düzenlemeyi iptal et</a></p>
                <?php endif; ?>
            </article>
        <?php endif; ?>

        <article class="card span-12 panel-poll">
            <div class="panel-birthdays-head">
                <h2>En son eklenen anket</h2>
                <?php if (!empty($latestSurvey)): ?>
                    <a class="card-head-link" href="<?= e((string)$latestSurvey['survey_url']) ?>" target="_blank" rel="noopener noreferrer">Yeni sekmede aç ›</a>
                <?php endif; ?>
            </div>
            <?php if (empty($latestSurvey)): ?>
                <p class="item-sub">Mevcut anket bulunamamıştır.</p>
            <?php else: ?>
                <div class="item">
                    <p class="item-title"><?= e((string)$latestSurvey['title']) ?></p>
                    <p class="item-sub"><?= e(date('d.m.Y H:i', strtotime((string)$latestSurvey['created_at']))) ?></p>
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

        <article class="card span-12">
            <h2>Tüm anket bağlantıları</h2>
            <?php if (!$surveys): ?>
                <p class="item-sub">Kayıt yok.</p>
            <?php endif; ?>
            <?php foreach ($surveys as $survey): ?>
                <div class="item admin-news-row">
                    <div class="admin-news-main">
                        <p class="item-title"><?= e((string)$survey['title']) ?></p>
                        <p class="item-sub">
                            <a href="<?= e((string)$survey['survey_url']) ?>" target="_blank" rel="noopener noreferrer"><?= e((string)$survey['survey_url']) ?></a>
                        </p>
                        <p class="item-sub"><?= e(date('d.m.Y H:i', strtotime((string)$survey['created_at']))) ?></p>
                    </div>
                    <?php if ($isAdmin): ?>
                        <a class="card-head-link" href="/portal/surveys.php?edit=<?= (int)$survey['id'] ?>" style="margin-right:8px">Düzenle</a>
                        <form method="post" class="admin-news-delete" onsubmit="return confirm('Bu anket bağlantısı silinsin mi?');">
                            <input type="hidden" name="survey_action" value="delete">
                            <input type="hidden" name="survey_id" value="<?= (int)$survey['id'] ?>">
                            <button type="submit" class="btn-danger">Sil</button>
                        </form>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </article>
    </section>
    <?php
});
