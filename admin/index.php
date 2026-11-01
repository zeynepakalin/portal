<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../includes/repository.php';
require_once __DIR__ . '/../includes/news_upload.php';
require_once __DIR__ . '/../includes/menu_pptx.php';

authRequireAdmin();

$message = '';
if (!empty($_SESSION['admin_flash'])) {
    $message = (string)$_SESSION['admin_flash'];
    unset($_SESSION['admin_flash']);
}
$adminActivePane = 'haberler';
$adminAllowedPanes = ['haberler', 'dogum-gunleri', 'yeni-katilanlar', 'yemek-menusu', 'aci-kaybimiz', 'etkinlik-takvimi', 'duyuru-banneri', 'anketler', 'egitimler'];
ensureEngagementTables($pdo);

$adminRedirectFlash = static function (string $tab, string $flashMessage): void {
    $_SESSION['admin_flash'] = $flashMessage;
    header('Location: index.php?tab=' . rawurlencode($tab));
    exit;
};

/** @return string|null Y-m-d */
function adminParseDateYmd(string $raw): ?string
{
    $s = trim($raw);
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $s)) {
        return null;
    }
    $ts = strtotime($s . ' 12:00:00');
    if ($ts === false) {
        return null;
    }
    return date('Y-m-d', $ts);
}

function adminNormalizeHttpUrl(string $raw): ?string
{
    $url = trim($raw);
    if ($url === '') {
        return null;
    }
    if (!preg_match('#^https?://#i', $url)) {
        return null;
    }
    return filter_var($url, FILTER_VALIDATE_URL) ? $url : null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = trim((string)($_POST['admin_action'] ?? ''));

    switch ($action) {
        case 'news_delete':
            $deleteId = (int)($_POST['delete_news_id'] ?? 0);
            if ($deleteId > 0) {
                $message = deleteNews($pdo, $deleteId) ? 'Haber / duyuru silindi.' : 'Kayıt silinemedi.';
            }
            break;

        case 'news_add':
            $title = trim((string)($_POST['title'] ?? ''));
            $summary = trim((string)($_POST['summary'] ?? ''));
            $category = trim((string)($_POST['category'] ?? 'Duyuru'));
            $imageUrl = null;
            $uploadNote = '';

            $file = $_FILES['news_image'] ?? null;
            if (is_array($file) && ($file['name'] ?? '') !== '') {
                if ((int)($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
                    $saved = saveNewsImageUpload($file);
                    if ($saved !== null) {
                        $imageUrl = $saved;
                    } else {
                        $uploadNote = 'Yüklenen dosya kabul edilmedi (yalnızca JPG, PNG, WEBP, GIF; en fazla 3 MB). ';
                    }
                } elseif ((int)$file['error'] !== UPLOAD_ERR_NO_FILE) {
                    $uploadNote = 'Dosya yüklenirken hata oluştu. ';
                }
            }
            if ($imageUrl === null) {
                $imageUrl = normalizeNewsImageUrl((string)($_POST['image_url'] ?? ''));
            }

            if ($title !== '' && $summary !== '') {
                addNews($pdo, $title, $summary, $category, $imageUrl);
                $adminRedirectFlash('haberler', $uploadNote !== '' ? $uploadNote . 'Haber kaydı eklendi.' : 'Haber kaydı başarıyla eklendi.');
            } else {
                $message = 'Başlık ve içerik zorunludur.';
            }
            break;

        case 'news_update':
            $newsId = (int)($_POST['news_id'] ?? 0);
            $title = trim((string)($_POST['title'] ?? ''));
            $summary = trim((string)($_POST['summary'] ?? ''));
            $category = trim((string)($_POST['category'] ?? 'Duyuru'));
            $existing = $newsId > 0 ? getNewsById($pdo, $newsId) : [];
            if ($existing === []) {
                $message = 'Düzenlenecek haber bulunamadı.';
                break;
            }
            $imageUrl = null;
            $uploadNote = '';
            $file = $_FILES['news_image'] ?? null;
            if (is_array($file) && ($file['name'] ?? '') !== '') {
                if ((int)($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
                    $saved = saveNewsImageUpload($file);
                    if ($saved !== null) {
                        $imageUrl = $saved;
                    } else {
                        $uploadNote = 'Yüklenen dosya kabul edilmedi (yalnızca JPG, PNG, WEBP, GIF; en fazla 3 MB). ';
                    }
                } elseif ((int)$file['error'] !== UPLOAD_ERR_NO_FILE) {
                    $uploadNote = 'Dosya yüklenirken hata oluştu. ';
                }
            }
            if ($imageUrl === null) {
                $urlFromForm = normalizeNewsImageUrl((string)($_POST['image_url'] ?? ''));
                if ($urlFromForm !== null) {
                    $imageUrl = $urlFromForm;
                } elseif (newsHasImageColumn($pdo)) {
                    $imageUrl = (string)($existing['image_url'] ?? '');
                }
            }
            if ($title !== '' && $summary !== '') {
                $finalImg = ($imageUrl !== null && trim((string)$imageUrl) !== '') ? trim((string)$imageUrl) : null;
                updateNews($pdo, $newsId, $title, $summary, $category, $finalImg);
                $adminRedirectFlash('haberler', $uploadNote !== '' ? $uploadNote . 'Haber güncellendi.' : 'Haber güncellendi.');
            } else {
                $message = 'Başlık ve içerik zorunludur.';
            }
            break;

        case 'news_move':
            $newsId = (int)($_POST['news_id'] ?? 0);
            $direction = trim((string)($_POST['direction'] ?? ''));
            if ($newsId > 0 && in_array($direction, ['up', 'down'], true)) {
                $message = moveNewsByDirection($pdo, $newsId, $direction)
                    ? 'Haber sıralaması güncellendi.'
                    : 'Haber sırası değiştirilemedi.';
            } else {
                $message = 'Geçersiz sıralama isteği.';
            }
            break;

        case 'birthday_add':
            $fn = trim((string)($_POST['birthday_full_name'] ?? ''));
            $dept = trim((string)($_POST['birthday_department'] ?? ''));
            $bd = adminParseDateYmd((string)($_POST['birthday_date'] ?? ''));
            if ($fn !== '' && $dept !== '' && $bd !== null) {
                addBirthday($pdo, $fn, $dept, $bd);
                $adminRedirectFlash('dogum-gunleri', 'Doğum günü kaydı eklendi.');
            } else {
                $message = 'Doğum günü: ad, birim ve geçerli tarih (Yıl-Ay-Gün) zorunludur.';
            }
            break;

        case 'birthday_update':
            $bid = (int)($_POST['birthday_id'] ?? 0);
            $fn = trim((string)($_POST['birthday_full_name'] ?? ''));
            $dept = trim((string)($_POST['birthday_department'] ?? ''));
            $bd = adminParseDateYmd((string)($_POST['birthday_date'] ?? ''));
            if ($bid > 0 && $fn !== '' && $dept !== '' && $bd !== null) {
                updateBirthday($pdo, $bid, $fn, $dept, $bd);
                $adminRedirectFlash('dogum-gunleri', 'Doğum günü kaydı güncellendi.');
            } else {
                $message = 'Doğum günü: ad, birim ve geçerli tarih (Yıl-Ay-Gün) zorunludur.';
            }
            break;

        case 'birthday_delete':
            $id = (int)($_POST['delete_birthday_id'] ?? 0);
            $message = $id > 0 && deleteBirthday($pdo, $id) ? 'Doğum günü kaydı silindi.' : 'Silme başarısız.';
            break;

        case 'joiner_add':
            $fn = trim((string)($_POST['joiner_full_name'] ?? ''));
            $role = trim((string)($_POST['joiner_role'] ?? ''));
            $dept = trim((string)($_POST['joiner_department'] ?? ''));
            $jd = adminParseDateYmd((string)($_POST['joiner_date'] ?? ''));
            if ($fn !== '' && $role !== '' && $dept !== '' && $jd !== null) {
                addJoiner($pdo, $fn, $role, $dept, $jd);
                $adminRedirectFlash('yeni-katilanlar', 'Yeni katılan kaydı eklendi.');
            } else {
                $message = 'Yeni katılan: tüm alanlar ve geçerli katılım tarihi zorunludur.';
            }
            break;

        case 'joiner_update':
            $jid = (int)($_POST['joiner_id'] ?? 0);
            $fn = trim((string)($_POST['joiner_full_name'] ?? ''));
            $role = trim((string)($_POST['joiner_role'] ?? ''));
            $dept = trim((string)($_POST['joiner_department'] ?? ''));
            $jd = adminParseDateYmd((string)($_POST['joiner_date'] ?? ''));
            if ($jid > 0 && $fn !== '' && $role !== '' && $dept !== '' && $jd !== null) {
                updateJoiner($pdo, $jid, $fn, $role, $dept, $jd);
                $adminRedirectFlash('yeni-katilanlar', 'Kayıt güncellendi.');
            } else {
                $message = 'Yeni katılan: tüm alanlar ve geçerli katılım tarihi zorunludur.';
            }
            break;

        case 'joiner_delete':
            $id = (int)($_POST['delete_joiner_id'] ?? 0);
            $message = $id > 0 && deleteJoiner($pdo, $id) ? 'Kayıt silindi.' : 'Silme başarısız.';
            break;

        case 'menu_save':
            $menuRowId = (int)($_POST['menu_id'] ?? 0);
            $md = adminParseDateYmd((string)($_POST['menu_date'] ?? ''));
            $soup = trim((string)($_POST['menu_soup'] ?? ''));
            $main = trim((string)($_POST['menu_main'] ?? ''));
            $side = trim((string)($_POST['menu_side'] ?? ''));
            $salad = trim((string)($_POST['menu_salad'] ?? ''));
            $dessert = trim((string)($_POST['menu_dessert'] ?? ''));
            if ($md !== null && $soup !== '' && $main !== '' && $side !== '' && $salad !== '' && $dessert !== '') {
                if ($menuRowId > 0) {
                    $prev = getFoodMenuById($pdo, $menuRowId);
                    $menuJson = isset($prev['menu_items']) ? (string)$prev['menu_items'] : null;
                    updateFoodMenuById($pdo, $menuRowId, $md, $soup, $main, $side, $salad, $dessert, $menuJson !== '' ? $menuJson : null);
                    $adminRedirectFlash('yemek-menusu', 'Yemek menüsü güncellendi.');
                } else {
                    upsertFoodMenu($pdo, $md, $soup, $main, $side, $salad, $dessert);
                    $adminRedirectFlash('yemek-menusu', 'Yemek menüsü kaydedildi.');
                }
            } else {
                $message = 'Menü: tarih ve tüm yemek alanları zorunludur.';
            }
            break;

        case 'menu_import_pptx':
            $menuFile = $_FILES['menu_pptx'] ?? null;
            if (!is_array($menuFile) || (int)($menuFile['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
                $message = 'Lütfen geçerli bir PPTX dosyası seçin.';
                break;
            }
            $name = (string)($menuFile['name'] ?? '');
            $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
            if ($ext !== 'pptx') {
                $message = 'Yalnızca .pptx dosyası yüklenebilir.';
                break;
            }
            $tmp = (string)($menuFile['tmp_name'] ?? '');
            try {
                $parsed = parseWeeklyMenuFromPptx($tmp);
                $count = 0;
                foreach ($parsed['menus'] as $menuItem) {
                    upsertFoodMenu(
                        $pdo,
                        $menuItem['menu_date'],
                        $menuItem['soup'],
                        $menuItem['main_course'],
                        $menuItem['side_dish'],
                        $menuItem['salad'],
                        $menuItem['dessert'],
                        $menuItem['menu_items'] ?? null
                    );
                    $count++;
                }
                $adminRedirectFlash('yemek-menusu', 'PPTX içe aktarıldı. ' . $count . ' günlük menü kaydedildi (' . $parsed['range'] . ').');
            } catch (Throwable $e) {
                $message = 'PPTX okunamadı: ' . $e->getMessage();
            }
            break;

        case 'menu_delete':
            $id = (int)($_POST['delete_menu_id'] ?? 0);
            $message = $id > 0 && deleteFoodMenu($pdo, $id) ? 'Menü kaydı silindi.' : 'Silme başarısız.';
            break;

        case 'condolence_add':
            $fn = trim((string)($_POST['cond_full_name'] ?? ''));
            $rel = trim((string)($_POST['cond_relation'] ?? ''));
            $msg = trim((string)($_POST['cond_message'] ?? ''));
            if ($fn !== '' && $rel !== '' && $msg !== '') {
                addCondolence($pdo, $fn, $rel, $msg);
                $adminRedirectFlash('aci-kaybimiz', 'Acı kaybı bildirimi eklendi.');
            } else {
                $message = 'Acı kaybı: tüm alanlar zorunludur.';
            }
            break;

        case 'condolence_update':
            $cid = (int)($_POST['condolence_id'] ?? 0);
            $fn = trim((string)($_POST['cond_full_name'] ?? ''));
            $rel = trim((string)($_POST['cond_relation'] ?? ''));
            $msg = trim((string)($_POST['cond_message'] ?? ''));
            if ($cid > 0 && $fn !== '' && $rel !== '' && $msg !== '') {
                updateCondolence($pdo, $cid, $fn, $rel, $msg);
                $adminRedirectFlash('aci-kaybimiz', 'Bildirim güncellendi.');
            } else {
                $message = 'Acı kaybı: tüm alanlar zorunludur.';
            }
            break;

        case 'condolence_delete':
            $id = (int)($_POST['delete_condolence_id'] ?? 0);
            $message = $id > 0 && deleteCondolence($pdo, $id) ? 'Kayıt silindi.' : 'Silme başarısız.';
            break;

        case 'event_add':
            $title = trim((string)($_POST['event_title'] ?? ''));
            $details = trim((string)($_POST['event_details'] ?? ''));
            $date = adminParseDateYmd((string)($_POST['event_date'] ?? ''));
            $time = trim((string)($_POST['event_time'] ?? ''));
            $category = trim((string)($_POST['event_category'] ?? 'Toplantı'));
            $allowedCategories = ['Toplantı', 'Eğitim', 'Resmi tatil', 'Şirket etkinliği'];
            if (!in_array($category, $allowedCategories, true)) {
                $category = 'Toplantı';
            }
            $eventTime = preg_match('/^\d{2}:\d{2}$/', $time) ? $time . ':00' : null;
            if ($title !== '' && $details !== '' && $date !== null) {
                addEvent($pdo, $title, $details, $date, $eventTime, $category);
                $adminRedirectFlash('etkinlik-takvimi', 'Etkinlik eklendi.');
            } else {
                $message = 'Etkinlik: başlık, açıklama ve tarih zorunludur.';
            }
            break;

        case 'event_update':
            $eid = (int)($_POST['event_id'] ?? 0);
            $title = trim((string)($_POST['event_title'] ?? ''));
            $details = trim((string)($_POST['event_details'] ?? ''));
            $date = adminParseDateYmd((string)($_POST['event_date'] ?? ''));
            $time = trim((string)($_POST['event_time'] ?? ''));
            $category = trim((string)($_POST['event_category'] ?? 'Toplantı'));
            $allowedCategories = ['Toplantı', 'Eğitim', 'Resmi tatil', 'Şirket etkinliği'];
            if (!in_array($category, $allowedCategories, true)) {
                $category = 'Toplantı';
            }
            $eventTime = preg_match('/^\d{2}:\d{2}$/', $time) ? $time . ':00' : null;
            if ($eid > 0 && $title !== '' && $details !== '' && $date !== null) {
                updateEvent($pdo, $eid, $title, $details, $date, $eventTime, $category);
                $adminRedirectFlash('etkinlik-takvimi', 'Etkinlik güncellendi.');
            } else {
                $message = 'Etkinlik: başlık, açıklama ve tarih zorunludur.';
            }
            break;

        case 'event_delete':
            $id = (int)($_POST['delete_event_id'] ?? 0);
            $message = $id > 0 && deleteEvent($pdo, $id) ? 'Etkinlik silindi.' : 'Silme başarısız.';
            break;

        case 'banner_save':
            $title = trim((string)($_POST['banner_title'] ?? ''));
            $text = trim((string)($_POST['banner_message'] ?? ''));
            $level = trim((string)($_POST['banner_level'] ?? 'info'));
            $allowedLevels = ['info', 'warning', 'danger'];
            if (!in_array($level, $allowedLevels, true)) {
                $level = 'info';
            }
            if ($title !== '' && $text !== '') {
                $bannerId = (int)($_POST['banner_id'] ?? 0);
                if ($bannerId > 0) {
                    updateAnnouncementBannerById($pdo, $bannerId, $title, $text, $level);
                } else {
                    upsertAnnouncementBanner($pdo, $title, $text, $level);
                }
                $adminRedirectFlash('duyuru-banneri', 'Duyuru bannerı kaydedildi.');
            } else {
                $message = 'Banner başlığı ve metni zorunludur.';
            }
            break;

        case 'banner_clear':
            clearAnnouncementBanner($pdo);
            $adminRedirectFlash('duyuru-banneri', 'Duyuru bannerı kapatıldı.');
            break;

        case 'survey_add':
            $title = trim((string)($_POST['survey_title'] ?? ''));
            $url = adminNormalizeHttpUrl((string)($_POST['survey_url'] ?? ''));
            if ($title !== '' && $url !== null) {
                addSurveyLink($pdo, $title, $url);
                $adminRedirectFlash('anketler', 'Anket bağlantısı eklendi.');
            } else {
                $message = 'Anket için başlık ve geçerli http/https bağlantısı zorunludur.';
            }
            break;

        case 'survey_update':
            $sid = (int)($_POST['survey_id'] ?? 0);
            $title = trim((string)($_POST['survey_title'] ?? ''));
            $url = adminNormalizeHttpUrl((string)($_POST['survey_url'] ?? ''));
            if ($sid > 0 && $title !== '' && $url !== null) {
                updateSurveyLink($pdo, $sid, $title, $url);
                $adminRedirectFlash('anketler', 'Anket bağlantısı güncellendi.');
            } else {
                $message = 'Anket için başlık ve geçerli http/https bağlantısı zorunludur.';
            }
            break;

        case 'survey_delete':
            $id = (int)($_POST['delete_survey_id'] ?? 0);
            $message = $id > 0 && deleteSurveyLink($pdo, $id) ? 'Anket bağlantısı silindi.' : 'Silme başarısız.';
            break;

        case 'training_add':
            $title = trim((string)($_POST['training_title'] ?? ''));
            $url = normalizeTrainingVideoStoredUrl((string)($_POST['training_video_url'] ?? ''));
            $details = trim((string)($_POST['training_details'] ?? ''));
            if ($title !== '' && $url !== null) {
                addTrainingVideo($pdo, $title, $url, $details);
                $adminRedirectFlash('egitimler', 'Eğitim videosu eklendi.');
            } else {
                $message = 'Başlık ve geçerli video yolu zorunludur (http/https, /portal/... veya \\\\sunucu\\paylaşım\\...mp4).';
            }
            break;

        case 'training_update':
            $tid = (int)($_POST['training_id'] ?? 0);
            $title = trim((string)($_POST['training_title'] ?? ''));
            $url = normalizeTrainingVideoStoredUrl((string)($_POST['training_video_url'] ?? ''));
            $details = trim((string)($_POST['training_details'] ?? ''));
            if ($tid > 0 && $title !== '' && $url !== null) {
                updateTrainingVideo($pdo, $tid, $title, $url, $details);
                $adminRedirectFlash('egitimler', 'Eğitim videosu güncellendi.');
            } else {
                $message = 'Başlık ve geçerli video yolu zorunludur (http/https veya ortak alan UNC).';
            }
            break;

        case 'training_delete':
            $id = (int)($_POST['delete_training_id'] ?? 0);
            $message = $id > 0 && deleteTrainingVideo($pdo, $id) ? 'Eğitim videosu silindi.' : 'Silme başarısız.';
            break;

    }

    $paneByAction = [
        'news_delete' => 'haberler',
        'news_add' => 'haberler',
        'news_move' => 'haberler',
        'birthday_add' => 'dogum-gunleri',
        'birthday_delete' => 'dogum-gunleri',
        'joiner_add' => 'yeni-katilanlar',
        'joiner_delete' => 'yeni-katilanlar',
        'menu_save' => 'yemek-menusu',
        'menu_import_pptx' => 'yemek-menusu',
        'menu_delete' => 'yemek-menusu',
        'condolence_add' => 'aci-kaybimiz',
        'condolence_delete' => 'aci-kaybimiz',
        'event_add' => 'etkinlik-takvimi',
        'event_delete' => 'etkinlik-takvimi',
        'banner_save' => 'duyuru-banneri',
        'banner_clear' => 'duyuru-banneri',
        'survey_add' => 'anketler',
        'survey_delete' => 'anketler',
        'training_add' => 'egitimler',
        'training_delete' => 'egitimler',
        'news_update' => 'haberler',
        'birthday_update' => 'dogum-gunleri',
        'joiner_update' => 'yeni-katilanlar',
        'condolence_update' => 'aci-kaybimiz',
        'event_update' => 'etkinlik-takvimi',
        'survey_update' => 'anketler',
        'training_update' => 'egitimler',
    ];
    $postedAction = trim((string)($_POST['admin_action'] ?? ''));
    if (isset($paneByAction[$postedAction])) {
        $adminActivePane = $paneByAction[$postedAction];
    }
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' && isset($_GET['tab'])) {
    $t = trim((string)$_GET['tab']);
    if (in_array($t, $adminAllowedPanes, true)) {
        $adminActivePane = $t;
    }
}

$editNewsId = (int)($_GET['edit_news'] ?? 0);
$editNews = $editNewsId > 0 ? getNewsById($pdo, $editNewsId) : [];
$editBirthdayId = (int)($_GET['edit_birthday'] ?? 0);
$editBirthday = $editBirthdayId > 0 ? getBirthdayById($pdo, $editBirthdayId) : [];
$editJoinerId = (int)($_GET['edit_joiner'] ?? 0);
$editJoiner = $editJoinerId > 0 ? getJoinerById($pdo, $editJoinerId) : [];
$editMenuId = (int)($_GET['edit_menu'] ?? 0);
$editMenuRow = $editMenuId > 0 ? getFoodMenuById($pdo, $editMenuId) : [];
$editCondolenceId = (int)($_GET['edit_condolence'] ?? 0);
$editCondolence = $editCondolenceId > 0 ? getCondolenceById($pdo, $editCondolenceId) : [];
$editEventId = (int)($_GET['edit_event'] ?? 0);
$editEvent = $editEventId > 0 ? getEventById($pdo, $editEventId) : [];
$editSurveyId = (int)($_GET['edit_survey'] ?? 0);
$editSurvey = $editSurveyId > 0 ? getSurveyLinkById($pdo, $editSurveyId) : [];
$editTrainingId = (int)($_GET['edit_training'] ?? 0);
$editTraining = $editTrainingId > 0 ? getTrainingVideoById($pdo, $editTrainingId) : [];
$paneByEdit = [
    'edit_news' => 'haberler',
    'edit_birthday' => 'dogum-gunleri',
    'edit_joiner' => 'yeni-katilanlar',
    'edit_menu' => 'yemek-menusu',
    'edit_condolence' => 'aci-kaybimiz',
    'edit_event' => 'etkinlik-takvimi',
    'edit_survey' => 'anketler',
    'edit_training' => 'egitimler',
];
foreach ($paneByEdit as $getKey => $pane) {
    if (isset($_GET[$getKey]) && (int)$_GET[$getKey] > 0) {
        $adminActivePane = $pane;
        break;
    }
}

$news = getNews($pdo, 10);
$birthdaysAdmin = getBirthdaysAdmin($pdo);
$joinersAdmin = getJoinersAdmin($pdo);
$menusAdmin = getFoodMenusAdmin($pdo, 40);
$condolencesAdmin = getCondolencesAdmin($pdo);
$eventsAdmin = getEventsAdmin($pdo, 80);
$bannerActive = getActiveAnnouncementBanner($pdo);
$surveyLinksAdmin = getSurveyLinksAdmin($pdo, 30);
$trainingVideosAdmin = getTrainingVideosAdmin($pdo, 30);
$todayMenuDate = date('Y-m-d');

renderLayout('Yönetim paneli', 'admin', function () use (
    $message,
    $news,
    $birthdaysAdmin,
    $joinersAdmin,
    $menusAdmin,
    $condolencesAdmin,
    $eventsAdmin,
    $bannerActive,
    $surveyLinksAdmin,
    $trainingVideosAdmin,
    $todayMenuDate,
    $adminActivePane,
    $editNews,
    $editBirthday,
    $editJoiner,
    $editMenuRow,
    $editCondolence,
    $editEvent,
    $editSurvey,
    $editTraining
): void {
    $paneHidden = static function (string $pane, string $active): string {
        return $pane === $active ? '' : ' hidden';
    };
    ?>
    <?php if ($message !== ''): ?>
        <p class="admin-flash item-sub"><?= e($message) ?></p>
    <?php endif; ?>

    <div
        class="admin-shell"
        data-admin-tabs
        data-default-pane="<?= e($adminActivePane) ?>"
    >
        <div class="admin-tab-bar" role="tablist" aria-label="Yönetim bölümleri">
            <button type="button" class="admin-tab<?= $adminActivePane === 'haberler' ? ' is-active' : '' ?>" role="tab" data-tab-target="haberler" aria-selected="<?= $adminActivePane === 'haberler' ? 'true' : 'false' ?>" aria-controls="pane-haberler" id="tab-haberler">Haberler</button>
            <button type="button" class="admin-tab<?= $adminActivePane === 'dogum-gunleri' ? ' is-active' : '' ?>" role="tab" data-tab-target="dogum-gunleri" aria-selected="<?= $adminActivePane === 'dogum-gunleri' ? 'true' : 'false' ?>" aria-controls="pane-dogum-gunleri" id="tab-dogum-gunleri">Doğum günleri</button>
            <button type="button" class="admin-tab<?= $adminActivePane === 'yeni-katilanlar' ? ' is-active' : '' ?>" role="tab" data-tab-target="yeni-katilanlar" aria-selected="<?= $adminActivePane === 'yeni-katilanlar' ? 'true' : 'false' ?>" aria-controls="pane-yeni-katilanlar" id="tab-yeni-katilanlar">Yeni katılanlar</button>
            <button type="button" class="admin-tab<?= $adminActivePane === 'yemek-menusu' ? ' is-active' : '' ?>" role="tab" data-tab-target="yemek-menusu" aria-selected="<?= $adminActivePane === 'yemek-menusu' ? 'true' : 'false' ?>" aria-controls="pane-yemek-menusu" id="tab-yemek-menusu">Yemek menüsü</button>
            <button type="button" class="admin-tab<?= $adminActivePane === 'aci-kaybimiz' ? ' is-active' : '' ?>" role="tab" data-tab-target="aci-kaybimiz" aria-selected="<?= $adminActivePane === 'aci-kaybimiz' ? 'true' : 'false' ?>" aria-controls="pane-aci-kaybimiz" id="tab-aci-kaybimiz">Acı kaybımız</button>
            <button type="button" class="admin-tab<?= $adminActivePane === 'etkinlik-takvimi' ? ' is-active' : '' ?>" role="tab" data-tab-target="etkinlik-takvimi" aria-selected="<?= $adminActivePane === 'etkinlik-takvimi' ? 'true' : 'false' ?>" aria-controls="pane-etkinlik-takvimi" id="tab-etkinlik-takvimi">Etkinlik takvimi</button>
            <button type="button" class="admin-tab<?= $adminActivePane === 'duyuru-banneri' ? ' is-active' : '' ?>" role="tab" data-tab-target="duyuru-banneri" aria-selected="<?= $adminActivePane === 'duyuru-banneri' ? 'true' : 'false' ?>" aria-controls="pane-duyuru-banneri" id="tab-duyuru-banneri">Duyuru banner'ı</button>
            <button type="button" class="admin-tab<?= $adminActivePane === 'anketler' ? ' is-active' : '' ?>" role="tab" data-tab-target="anketler" aria-selected="<?= $adminActivePane === 'anketler' ? 'true' : 'false' ?>" aria-controls="pane-anketler" id="tab-anketler">Anketler</button>
            <button type="button" class="admin-tab<?= $adminActivePane === 'egitimler' ? ' is-active' : '' ?>" role="tab" data-tab-target="egitimler" aria-selected="<?= $adminActivePane === 'egitimler' ? 'true' : 'false' ?>" aria-controls="pane-egitimler" id="tab-egitimler">Eğitimler</button>
        </div>

    <div class="admin-pane" data-admin-pane="haberler" id="pane-haberler" role="tabpanel" aria-labelledby="tab-haberler"<?= $paneHidden('haberler', $adminActivePane) ?>>
    <section class="grid">
        <article class="card span-6">
            <?php if (!empty($editNews)): ?>
                <h2>Haber düzenle</h2>
                <p class="item-sub" style="margin-top:0"><a href="index.php?tab=haberler">← Yeni haber ekle</a></p>
                <form method="post" enctype="multipart/form-data" class="form">
                    <input type="hidden" name="admin_action" value="news_update">
                    <input type="hidden" name="news_id" value="<?= (int)($editNews['id'] ?? 0) ?>">
                    <label>Başlık</label>
                    <input type="text" name="title" required value="<?= e((string)($editNews['title'] ?? '')) ?>">

                    <label>Kategori</label>
                    <?php $ec = (string)($editNews['category'] ?? 'Duyuru'); ?>
                    <select name="category">
                        <option value="Haber"<?= $ec === 'Haber' ? ' selected' : '' ?>>Haber</option>
                        <option value="Duyuru"<?= $ec === 'Duyuru' ? ' selected' : '' ?>>Duyuru</option>
                        <option value="İK"<?= $ec === 'İK' ? ' selected' : '' ?>>İK</option>
                    </select>

                    <label>Görsel bağlantısı</label>
                    <p class="item-sub" style="margin:0 0 6px">Google Görseller’de görsele sağ tıklayıp <strong>Görsel adresini kopyala</strong> deyin.</p>
                    <input type="text" name="image_url" inputmode="url" autocomplete="off" placeholder="https://..." value="<?= e((string)(isset($editNews['image_url']) ? $editNews['image_url'] : '')) ?>">

                    <label>Cihazdan görsel yükle</label>
                    <input type="file" name="news_image" accept="image/jpeg,image/png,image/webp,image/gif">

                    <label>Özet / içerik</label>
                    <textarea name="summary" rows="5" required><?= e((string)($editNews['summary'] ?? '')) ?></textarea>

                    <button type="submit">Güncelle</button>
                </form>
            <?php else: ?>
                <h2>Yeni haber / duyuru ekle</h2>
                <form method="post" enctype="multipart/form-data" class="form">
                    <input type="hidden" name="admin_action" value="news_add">
                    <label>Başlık</label>
                    <input type="text" name="title" required>

                    <label>Kategori</label>
                    <select name="category">
                        <option value="Haber">Haber</option>
                        <option value="Duyuru">Duyuru</option>
                        <option value="İK">İK</option>
                    </select>

                    <label>Görsel bağlantısı</label>
                    <p class="item-sub" style="margin:0 0 6px">Google Görseller’de görsele sağ tıklayıp <strong>Görsel adresini kopyala</strong> deyin.</p>
                    <input type="text" name="image_url" inputmode="url" autocomplete="off" placeholder="https://...">

                    <label>Cihazdan görsel yükle</label>
                    <input type="file" name="news_image" accept="image/jpeg,image/png,image/webp,image/gif">

                    <label>Özet / içerik</label>
                    <textarea name="summary" rows="5" required></textarea>

                    <button type="submit">Kaydet</button>
                </form>
            <?php endif; ?>
        </article>

        <article class="card span-6">
            <h2>Son haberler / duyurular</h2>
            <?php if (!$news): ?>
                <p class="item-sub">Henüz kayıt yok.</p>
            <?php endif; ?>
            <?php foreach ($news as $item): ?>
                <div class="item admin-news-row">
                    <div class="admin-news-main">
                        <span class="badge"><?= e($item['category']) ?></span>
                        <p class="item-title"><?= e($item['title']) ?></p>
                        <p class="item-sub"><?= e(date('d.m.Y H:i', strtotime($item['published_at']))) ?></p>
                    </div>
                    <div class="admin-news-actions">
                        <form method="post" class="admin-news-delete">
                            <input type="hidden" name="admin_action" value="news_move">
                            <input type="hidden" name="news_id" value="<?= (int)($item['id'] ?? 0) ?>">
                            <input type="hidden" name="direction" value="up">
                            <button type="submit" class="btn-sort" title="Yukarı taşı">↑</button>
                        </form>
                        <form method="post" class="admin-news-delete">
                            <input type="hidden" name="admin_action" value="news_move">
                            <input type="hidden" name="news_id" value="<?= (int)($item['id'] ?? 0) ?>">
                            <input type="hidden" name="direction" value="down">
                            <button type="submit" class="btn-sort" title="Aşağı taşı">↓</button>
                        </form>
                        <a class="card-head-link" href="index.php?tab=haberler&amp;edit_news=<?= (int)($item['id'] ?? 0) ?>" style="margin-right:8px">Düzenle</a>
                        <form method="post" class="admin-news-delete" onsubmit="return confirm('Bu kaydı silmek istediğinize emin misiniz?');">
                            <input type="hidden" name="admin_action" value="news_delete">
                            <input type="hidden" name="delete_news_id" value="<?= (int)($item['id'] ?? 0) ?>">
                            <button type="submit" class="btn-danger">Sil</button>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
        </article>
    </section>
    </div>

    <div class="admin-pane" data-admin-pane="dogum-gunleri" id="pane-dogum-gunleri" role="tabpanel" aria-labelledby="tab-dogum-gunleri"<?= $paneHidden('dogum-gunleri', $adminActivePane) ?>>
    <section class="grid admin-section">
        <article class="card span-6">
            <?php if (!empty($editBirthday)): ?>
                <h2>Doğum günü düzenle</h2>
                <p class="item-sub" style="margin-top:0"><a href="index.php?tab=dogum-gunleri">← Yeni kayıt</a></p>
                <form method="post" class="form">
                    <input type="hidden" name="admin_action" value="birthday_update">
                    <input type="hidden" name="birthday_id" value="<?= (int)($editBirthday['id'] ?? 0) ?>">
                    <label>Ad soyad</label>
                    <input type="text" name="birthday_full_name" required maxlength="120" value="<?= e((string)($editBirthday['full_name'] ?? '')) ?>">
                    <label>Birim</label>
                    <input type="text" name="birthday_department" required maxlength="120" value="<?= e((string)($editBirthday['department'] ?? '')) ?>">
                    <label>Doğum tarihi</label>
                    <input type="date" name="birthday_date" required value="<?= e((string)($editBirthday['birth_date'] ?? '')) ?>">
                    <button type="submit">Güncelle</button>
                </form>
            <?php else: ?>
                <h2>Doğum günü ekle</h2>
                <form method="post" class="form">
                    <input type="hidden" name="admin_action" value="birthday_add">
                    <label>Ad soyad</label>
                    <input type="text" name="birthday_full_name" required maxlength="120">
                    <label>Birim</label>
                    <input type="text" name="birthday_department" required maxlength="120">
                    <label>Doğum tarihi</label>
                    <input type="date" name="birthday_date" required>
                    <button type="submit">Ekle</button>
                </form>
            <?php endif; ?>
        </article>
        <article class="card span-6">
            <h2>Doğum günleri listesi</h2>
            <?php if (!$birthdaysAdmin): ?>
                <p class="item-sub">Kayıt yok.</p>
            <?php endif; ?>
            <?php foreach ($birthdaysAdmin as $b): ?>
                <div class="item admin-news-row">
                    <div class="admin-news-main">
                        <p class="item-title"><?= e($b['full_name']) ?></p>
                        <p class="item-sub"><?= e($b['department']) ?> · <?= e(date('d.m.Y', strtotime($b['birth_date']))) ?></p>
                    </div>
                    <a class="card-head-link" href="index.php?tab=dogum-gunleri&amp;edit_birthday=<?= (int)$b['id'] ?>" style="margin-right:8px">Düzenle</a>
                    <form method="post" class="admin-news-delete" onsubmit="return confirm('Silmek istediğinize emin misiniz?');">
                        <input type="hidden" name="admin_action" value="birthday_delete">
                        <input type="hidden" name="delete_birthday_id" value="<?= (int)$b['id'] ?>">
                        <button type="submit" class="btn-danger">Sil</button>
                    </form>
                </div>
            <?php endforeach; ?>
        </article>
    </section>
    </div>

    <div class="admin-pane" data-admin-pane="yeni-katilanlar" id="pane-yeni-katilanlar" role="tabpanel" aria-labelledby="tab-yeni-katilanlar"<?= $paneHidden('yeni-katilanlar', $adminActivePane) ?>>
    <section class="grid admin-section">
        <article class="card span-6">
            <?php if (!empty($editJoiner)): ?>
                <h2>Kayıt düzenle</h2>
                <p class="item-sub" style="margin-top:0"><a href="index.php?tab=yeni-katilanlar">← Yeni kayıt</a></p>
                <form method="post" class="form">
                    <input type="hidden" name="admin_action" value="joiner_update">
                    <input type="hidden" name="joiner_id" value="<?= (int)($editJoiner['id'] ?? 0) ?>">
                    <label>Ad soyad</label>
                    <input type="text" name="joiner_full_name" required maxlength="120" value="<?= e((string)($editJoiner['full_name'] ?? '')) ?>">
                    <label>Ünvan / rol</label>
                    <input type="text" name="joiner_role" required maxlength="120" value="<?= e((string)($editJoiner['role'] ?? '')) ?>">
                    <label>Birim</label>
                    <input type="text" name="joiner_department" required maxlength="120" value="<?= e((string)($editJoiner['department'] ?? '')) ?>">
                    <label>Katılım tarihi</label>
                    <input type="date" name="joiner_date" required value="<?= e((string)($editJoiner['join_date'] ?? '')) ?>">
                    <button type="submit">Güncelle</button>
                </form>
            <?php else: ?>
                <h2>Yeni katılan ekle</h2>
                <form method="post" class="form">
                    <input type="hidden" name="admin_action" value="joiner_add">
                    <label>Ad soyad</label>
                    <input type="text" name="joiner_full_name" required maxlength="120">
                    <label>Ünvan / rol</label>
                    <input type="text" name="joiner_role" required maxlength="120">
                    <label>Birim</label>
                    <input type="text" name="joiner_department" required maxlength="120">
                    <label>Katılım tarihi</label>
                    <input type="date" name="joiner_date" required value="<?= e($todayMenuDate) ?>">
                    <button type="submit">Ekle</button>
                </form>
            <?php endif; ?>
        </article>
        <article class="card span-6">
            <h2>Yeni katılanlar listesi</h2>
            <?php if (!$joinersAdmin): ?>
                <p class="item-sub">Kayıt yok.</p>
            <?php endif; ?>
            <?php foreach ($joinersAdmin as $j): ?>
                <div class="item admin-news-row">
                    <div class="admin-news-main">
                        <p class="item-title"><?= e($j['full_name']) ?></p>
                        <p class="item-sub"><?= e($j['role']) ?> — <?= e($j['department']) ?> · <?= e(date('d.m.Y', strtotime($j['join_date']))) ?></p>
                    </div>
                    <a class="card-head-link" href="index.php?tab=yeni-katilanlar&amp;edit_joiner=<?= (int)$j['id'] ?>" style="margin-right:8px">Düzenle</a>
                    <form method="post" class="admin-news-delete" onsubmit="return confirm('Silmek istediğinize emin misiniz?');">
                        <input type="hidden" name="admin_action" value="joiner_delete">
                        <input type="hidden" name="delete_joiner_id" value="<?= (int)$j['id'] ?>">
                        <button type="submit" class="btn-danger">Sil</button>
                    </form>
                </div>
            <?php endforeach; ?>
        </article>
    </section>
    </div>

    <div class="admin-pane" data-admin-pane="yemek-menusu" id="pane-yemek-menusu" role="tabpanel" aria-labelledby="tab-yemek-menusu"<?= $paneHidden('yemek-menusu', $adminActivePane) ?>>
    <section class="grid admin-section">
        <article class="card span-6">
            <h2>Yemek menüsü (tarih seçin)</h2>
            <?php if (!empty($editMenuRow)): ?>
                <p class="item-sub" style="margin-top:0">Kayıt düzenleniyor. <a href="index.php?tab=yemek-menusu">İptal</a></p>
            <?php else: ?>
                <p class="item-sub" style="margin-top:0">Aynı tarih için tekrar kaydederseniz mevcut menü güncellenir.</p>
            <?php endif; ?>
            <form method="post" class="form">
                <input type="hidden" name="admin_action" value="menu_save">
                <?php if (!empty($editMenuRow)): ?>
                    <input type="hidden" name="menu_id" value="<?= (int)($editMenuRow['id'] ?? 0) ?>">
                <?php endif; ?>
                <label>Menü tarihi</label>
                <input type="date" name="menu_date" required value="<?= e(!empty($editMenuRow['menu_date']) ? (string)$editMenuRow['menu_date'] : $todayMenuDate) ?>">
                <label>Çorba</label>
                <input type="text" name="menu_soup" required maxlength="100" value="<?= e((string)($editMenuRow['soup'] ?? '')) ?>">
                <label>Ana yemek</label>
                <input type="text" name="menu_main" required maxlength="100" value="<?= e((string)($editMenuRow['main_course'] ?? '')) ?>">
                <label>Yardımcı</label>
                <input type="text" name="menu_side" required maxlength="100" value="<?= e((string)($editMenuRow['side_dish'] ?? '')) ?>">
                <label>Salata</label>
                <input type="text" name="menu_salad" required maxlength="100" value="<?= e((string)($editMenuRow['salad'] ?? '')) ?>">
                <label>Tatlı</label>
                <input type="text" name="menu_dessert" required maxlength="100" value="<?= e((string)($editMenuRow['dessert'] ?? '')) ?>">
                <button type="submit"><?= !empty($editMenuRow) ? 'Güncelle' : 'Kaydet / güncelle' ?></button>
            </form>
            <hr style="border:0;border-top:1px solid var(--border);margin:14px 0;">
            <h3 style="margin:0 0 8px;font-size:14px;">PowerPoint'ten haftalık içe aktar</h3>
            <p class="item-sub" style="margin:0 0 8px;">Pazartesi-Cuma tablosu olan <strong>.pptx</strong> dosyasını seçin.</p>
            <form method="post" class="form" enctype="multipart/form-data">
                <input type="hidden" name="admin_action" value="menu_import_pptx">
                <input type="file" name="menu_pptx" accept=".pptx,application/vnd.openxmlformats-officedocument.presentationml.presentation" required>
                <button type="submit">PPTX içe aktar</button>
            </form>
        </article>
        <article class="card span-6">
            <h2>Kayıtlı menüler</h2>
            <?php if (!$menusAdmin): ?>
                <p class="item-sub">Kayıt yok.</p>
            <?php endif; ?>
            <?php foreach ($menusAdmin as $m): ?>
                <div class="item admin-news-row">
                    <div class="admin-news-main">
                        <p class="item-title"><?= e(date('d.m.Y', strtotime($m['menu_date']))) ?></p>
                        <p class="item-sub"><?= e($m['soup']) ?>, <?= e($m['main_course']) ?>…</p>
                    </div>
                    <a class="card-head-link" href="index.php?tab=yemek-menusu&amp;edit_menu=<?= (int)$m['id'] ?>" style="margin-right:8px">Düzenle</a>
                    <form method="post" class="admin-news-delete" onsubmit="return confirm('Bu menüyü silmek istediğinize emin misiniz?');">
                        <input type="hidden" name="admin_action" value="menu_delete">
                        <input type="hidden" name="delete_menu_id" value="<?= (int)$m['id'] ?>">
                        <button type="submit" class="btn-danger">Sil</button>
                    </form>
                </div>
            <?php endforeach; ?>
        </article>
    </section>
    </div>

    <div class="admin-pane" data-admin-pane="aci-kaybimiz" id="pane-aci-kaybimiz" role="tabpanel" aria-labelledby="tab-aci-kaybimiz"<?= $paneHidden('aci-kaybimiz', $adminActivePane) ?>>
    <section class="grid admin-section">
        <article class="card span-6">
            <?php if (!empty($editCondolence)): ?>
                <h2>Bildirim düzenle</h2>
                <p class="item-sub" style="margin-top:0"><a href="index.php?tab=aci-kaybimiz">← Yeni bildirim</a></p>
                <form method="post" class="form">
                    <input type="hidden" name="admin_action" value="condolence_update">
                    <input type="hidden" name="condolence_id" value="<?= (int)($editCondolence['id'] ?? 0) ?>">
                    <label>Merhum / merhume adı</label>
                    <input type="text" name="cond_full_name" required maxlength="120" value="<?= e((string)($editCondolence['full_name'] ?? '')) ?>">
                    <label>Yakınlık / birim bilgisi</label>
                    <input type="text" name="cond_relation" required maxlength="255" value="<?= e((string)($editCondolence['relation_info'] ?? '')) ?>">
                    <label>Mesaj</label>
                    <textarea name="cond_message" rows="4" required><?= e((string)($editCondolence['message'] ?? '')) ?></textarea>
                    <button type="submit">Güncelle</button>
                </form>
            <?php else: ?>
                <h2>Acı kaybı bildirimi ekle</h2>
                <form method="post" class="form">
                    <input type="hidden" name="admin_action" value="condolence_add">
                    <label>Merhum / merhume adı</label>
                    <input type="text" name="cond_full_name" required maxlength="120">
                    <label>Yakınlık / birim bilgisi</label>
                    <input type="text" name="cond_relation" required maxlength="255">
                    <label>Mesaj</label>
                    <textarea name="cond_message" rows="4" required></textarea>
                    <button type="submit">Yayınla</button>
                </form>
            <?php endif; ?>
        </article>
        <article class="card span-6">
            <h2>Yayınlanan bildirimler</h2>
            <?php if (!$condolencesAdmin): ?>
                <p class="item-sub">Kayıt yok.</p>
            <?php endif; ?>
            <?php foreach ($condolencesAdmin as $c): ?>
                <div class="item admin-news-row">
                    <div class="admin-news-main">
                        <p class="item-title"><?= e($c['full_name']) ?></p>
                        <p class="item-sub"><?= e($c['relation_info']) ?> · <?= e(date('d.m.Y H:i', strtotime($c['published_at']))) ?></p>
                    </div>
                    <a class="card-head-link" href="index.php?tab=aci-kaybimiz&amp;edit_condolence=<?= (int)$c['id'] ?>" style="margin-right:8px">Düzenle</a>
                    <form method="post" class="admin-news-delete" onsubmit="return confirm('Silmek istediğinize emin misiniz?');">
                        <input type="hidden" name="admin_action" value="condolence_delete">
                        <input type="hidden" name="delete_condolence_id" value="<?= (int)$c['id'] ?>">
                        <button type="submit" class="btn-danger">Sil</button>
                    </form>
                </div>
            <?php endforeach; ?>
        </article>
    </section>
    </div>

    <div class="admin-pane" data-admin-pane="etkinlik-takvimi" id="pane-etkinlik-takvimi" role="tabpanel" aria-labelledby="tab-etkinlik-takvimi"<?= $paneHidden('etkinlik-takvimi', $adminActivePane) ?>>
    <section class="grid admin-section">
        <article class="card span-6">
            <?php
            $evTimeVal = '';
            if (!empty($editEvent['event_time'])) {
                $evTimeVal = substr((string)$editEvent['event_time'], 0, 5);
            }
            $evCat = (string)($editEvent['category'] ?? 'Toplantı');
            ?>
            <?php if (!empty($editEvent)): ?>
                <h2>Etkinlik düzenle</h2>
                <p class="item-sub" style="margin-top:0"><a href="index.php?tab=etkinlik-takvimi">← Yeni etkinlik</a></p>
                <form method="post" class="form">
                    <input type="hidden" name="admin_action" value="event_update">
                    <input type="hidden" name="event_id" value="<?= (int)($editEvent['id'] ?? 0) ?>">
                    <label>Başlık</label>
                    <input type="text" name="event_title" required maxlength="180" value="<?= e((string)($editEvent['title'] ?? '')) ?>">
                    <label>Kategori</label>
                    <select name="event_category">
                        <option<?= $evCat === 'Toplantı' ? ' selected' : '' ?>>Toplantı</option>
                        <option<?= $evCat === 'Eğitim' ? ' selected' : '' ?>>Eğitim</option>
                        <option<?= $evCat === 'Resmi tatil' ? ' selected' : '' ?>>Resmi tatil</option>
                        <option<?= $evCat === 'Şirket etkinliği' ? ' selected' : '' ?>>Şirket etkinliği</option>
                    </select>
                    <label>Tarih</label>
                    <input type="date" name="event_date" required value="<?= e((string)($editEvent['event_date'] ?? '')) ?>">
                    <label>Saat (opsiyonel)</label>
                    <input type="time" name="event_time" value="<?= e($evTimeVal) ?>">
                    <label>Açıklama</label>
                    <textarea name="event_details" rows="4" required><?= e((string)($editEvent['details'] ?? '')) ?></textarea>
                    <button type="submit">Güncelle</button>
                </form>
            <?php else: ?>
                <h2>Etkinlik ekle</h2>
                <form method="post" class="form">
                    <input type="hidden" name="admin_action" value="event_add">
                    <label>Başlık</label>
                    <input type="text" name="event_title" required maxlength="180">
                    <label>Kategori</label>
                    <select name="event_category">
                        <option>Toplantı</option>
                        <option>Eğitim</option>
                        <option>Resmi tatil</option>
                        <option>Şirket etkinliği</option>
                    </select>
                    <label>Tarih</label>
                    <input type="date" name="event_date" required value="<?= e($todayMenuDate) ?>">
                    <label>Saat (opsiyonel)</label>
                    <input type="time" name="event_time">
                    <label>Açıklama</label>
                    <textarea name="event_details" rows="4" required></textarea>
                    <button type="submit">Etkinlik ekle</button>
                </form>
            <?php endif; ?>
        </article>
        <article class="card span-6">
            <h2>Etkinlik listesi</h2>
            <?php if (!$eventsAdmin): ?><p class="item-sub">Kayıt yok.</p><?php endif; ?>
            <?php foreach ($eventsAdmin as $ev): ?>
                <div class="item admin-news-row">
                    <div class="admin-news-main">
                        <span class="badge"><?= e($ev['category']) ?></span>
                        <p class="item-title"><?= e($ev['title']) ?></p>
                        <p class="item-sub"><?= e(date('d.m.Y', strtotime($ev['event_date']))) ?><?php if (!empty($ev['event_time'])): ?> · <?= e(substr((string)$ev['event_time'], 0, 5)) ?><?php endif; ?></p>
                    </div>
                    <a class="card-head-link" href="index.php?tab=etkinlik-takvimi&amp;edit_event=<?= (int)$ev['id'] ?>" style="margin-right:8px">Düzenle</a>
                    <form method="post" class="admin-news-delete" onsubmit="return confirm('Etkinlik silinsin mi?');">
                        <input type="hidden" name="admin_action" value="event_delete">
                        <input type="hidden" name="delete_event_id" value="<?= (int)$ev['id'] ?>">
                        <button type="submit" class="btn-danger">Sil</button>
                    </form>
                </div>
            <?php endforeach; ?>
        </article>
    </section>
    </div>

    <div class="admin-pane" data-admin-pane="duyuru-banneri" id="pane-duyuru-banneri" role="tabpanel" aria-labelledby="tab-duyuru-banneri"<?= $paneHidden('duyuru-banneri', $adminActivePane) ?>>
    <section class="grid admin-section">
        <article class="card span-6">
            <h2>Duyuru banner'ı düzenle</h2>
            <form method="post" class="form">
                <input type="hidden" name="admin_action" value="banner_save">
                <?php if (!empty($bannerActive['id'])): ?>
                    <input type="hidden" name="banner_id" value="<?= (int)$bannerActive['id'] ?>">
                <?php endif; ?>
                <label>Başlık</label>
                <input type="text" name="banner_title" required maxlength="160" value="<?= e((string)($bannerActive['title'] ?? '')) ?>">
                <label>Mesaj</label>
                <textarea name="banner_message" rows="4" required><?= e((string)($bannerActive['message'] ?? '')) ?></textarea>
                <label>Renk seviyesi</label>
                <select name="banner_level">
                    <?php $currentLevel = (string)($bannerActive['level'] ?? 'info'); ?>
                    <option value="info"<?= $currentLevel === 'info' ? ' selected' : '' ?>>Bilgi</option>
                    <option value="warning"<?= $currentLevel === 'warning' ? ' selected' : '' ?>>Uyarı</option>
                    <option value="danger"<?= $currentLevel === 'danger' ? ' selected' : '' ?>>Kritik</option>
                </select>
                <button type="submit">Banner'ı yayınla</button>
            </form>
            <form method="post" style="margin-top:8px">
                <input type="hidden" name="admin_action" value="banner_clear">
                <button type="submit" class="btn-danger">Banner'ı kapat</button>
            </form>
        </article>
    </section>
    </div>

    <div class="admin-pane" data-admin-pane="anketler" id="pane-anketler" role="tabpanel" aria-labelledby="tab-anketler"<?= $paneHidden('anketler', $adminActivePane) ?>>
    <section class="grid admin-section">
        <article class="card span-6">
            <?php if (!empty($editSurvey)): ?>
                <h2>Anket bağlantısı düzenle</h2>
                <p class="item-sub" style="margin-top:0">Ana sayfada <strong>en son eklenen</strong> anket gösterilir.</p>
                <p class="item-sub" style="margin-top:0"><a href="index.php?tab=anketler">← Yeni ekle</a></p>
                <form method="post" class="form">
                    <input type="hidden" name="admin_action" value="survey_update">
                    <input type="hidden" name="survey_id" value="<?= (int)($editSurvey['id'] ?? 0) ?>">
                    <label>Başlık</label>
                    <input type="text" name="survey_title" required maxlength="180" value="<?= e((string)($editSurvey['title'] ?? '')) ?>">
                    <label>Anket bağlantısı (http/https)</label>
                    <input type="url" name="survey_url" required maxlength="1000" value="<?= e((string)($editSurvey['survey_url'] ?? '')) ?>">
                    <button type="submit">Güncelle</button>
                </form>
            <?php else: ?>
                <h2>Memnuniyet anketi bağlantısı ekle</h2>
                <p class="item-sub" style="margin-top:0">Memnuniyet-anket projesindeki anket sayfasının tam adresini yapıştırın. Ana sayfada <strong>en son eklenen</strong> bağlantı gösterilir.</p>
                <form method="post" class="form">
                    <input type="hidden" name="admin_action" value="survey_add">
                    <label>Başlık</label>
                    <input type="text" name="survey_title" required maxlength="180" placeholder="Örn: Nisan 2026 Memnuniyet Anketi">
                    <label>Anket bağlantısı (http/https)</label>
                    <input type="url" name="survey_url" required maxlength="1000" placeholder="https://...">
                    <button type="submit">Anket bağlantısı ekle</button>
                </form>
            <?php endif; ?>
        </article>
        <article class="card span-6">
            <h2>Kayıtlı anket bağlantıları</h2>
            <?php if (!$surveyLinksAdmin): ?><p class="item-sub">Kayıt yok.</p><?php endif; ?>
            <?php foreach ($surveyLinksAdmin as $survey): ?>
                <div class="item admin-news-row">
                    <div class="admin-news-main">
                        <p class="item-title"><?= e($survey['title']) ?></p>
                        <p class="item-sub"><?= e((string)$survey['survey_url']) ?></p>
                        <p class="item-sub"><?= e(date('d.m.Y H:i', strtotime($survey['created_at']))) ?></p>
                    </div>
                    <a class="card-head-link" href="index.php?tab=anketler&amp;edit_survey=<?= (int)$survey['id'] ?>" style="margin-right:8px">Düzenle</a>
                    <form method="post" class="admin-news-delete" onsubmit="return confirm('Bu anket bağlantısı silinsin mi?');">
                        <input type="hidden" name="admin_action" value="survey_delete">
                        <input type="hidden" name="delete_survey_id" value="<?= (int)$survey['id'] ?>">
                        <button type="submit" class="btn-danger">Sil</button>
                    </form>
                </div>
            <?php endforeach; ?>
        </article>
    </section>
    </div>

    <div class="admin-pane" data-admin-pane="egitimler" id="pane-egitimler" role="tabpanel" aria-labelledby="tab-egitimler"<?= $paneHidden('egitimler', $adminActivePane) ?>>
    <section class="grid admin-section">
        <article class="card span-6">
            <?php if (!empty($editTraining)): ?>
                <h2>Eğitim videosu düzenle</h2>
                <p class="item-sub" style="margin-top:0">Ana sayfada <strong>en son eklenen</strong> video gösterilir. YouTube, https veya ortak alan UNC yolu kullanılabilir.</p>
                <p class="item-sub" style="margin-top:0"><a href="index.php?tab=egitimler">← Yeni ekle</a></p>
                <form method="post" class="form">
                    <input type="hidden" name="admin_action" value="training_update">
                    <input type="hidden" name="training_id" value="<?= (int)($editTraining['id'] ?? 0) ?>">
                    <label>Başlık</label>
                    <input type="text" name="training_title" required maxlength="180" value="<?= e((string)($editTraining['title'] ?? '')) ?>">
                    <label>Video yolu (http/https veya \\sunucu\paylaşım\...)</label>
                    <input type="text" name="training_video_url" required maxlength="1000" value="<?= e((string)($editTraining['video_url'] ?? '')) ?>">
                    <label>Açıklama (opsiyonel)</label>
                    <textarea name="training_details" rows="3"><?= e((string)($editTraining['details'] ?? '')) ?></textarea>
                    <button type="submit">Güncelle</button>
                </form>
            <?php else: ?>
                <h2>Eğitim videosu ekle</h2>
                <p class="item-sub" style="margin-top:0">YouTube/https veya ortak alan: <code>\\sobek\ortak\...\video.mp4</code>. Apache’nin paylaşıma okuma yetkisi gerekir.</p>
                <form method="post" class="form">
                    <input type="hidden" name="admin_action" value="training_add">
                    <label>Başlık</label>
                    <input type="text" name="training_title" required maxlength="180" placeholder="Örn: İş sağlığı eğitimi">
                    <label>Video yolu</label>
                    <input type="text" name="training_video_url" required maxlength="1000" placeholder="\\sobek\ortak\...\dosya.mp4 veya https://...">
                    <label>Açıklama (opsiyonel)</label>
                    <textarea name="training_details" rows="3" placeholder="Kısa açıklama"></textarea>
                    <button type="submit">Eğitim videosu ekle</button>
                </form>
            <?php endif; ?>
        </article>
        <article class="card span-6">
            <h2>Kayıtlı eğitim videoları</h2>
            <?php if (!$trainingVideosAdmin): ?><p class="item-sub">Kayıt yok.</p><?php endif; ?>
            <?php foreach ($trainingVideosAdmin as $video): ?>
                <div class="item admin-news-row">
                    <div class="admin-news-main">
                        <p class="item-title"><?= e($video['title']) ?></p>
                        <p class="item-sub"><?= e((string)$video['video_url']) ?></p>
                        <?php if (trim((string)$video['details']) !== ''): ?>
                            <p class="item-sub"><?= e((string)$video['details']) ?></p>
                        <?php endif; ?>
                        <p class="item-sub"><?= e(date('d.m.Y H:i', strtotime($video['created_at']))) ?></p>
                    </div>
                    <a class="card-head-link" href="index.php?tab=egitimler&amp;edit_training=<?= (int)$video['id'] ?>" style="margin-right:8px">Düzenle</a>
                    <form method="post" class="admin-news-delete" onsubmit="return confirm('Bu eğitim videosu silinsin mi?');">
                        <input type="hidden" name="admin_action" value="training_delete">
                        <input type="hidden" name="delete_training_id" value="<?= (int)$video['id'] ?>">
                        <button type="submit" class="btn-danger">Sil</button>
                    </form>
                </div>
            <?php endforeach; ?>
        </article>
    </section>
    </div>

    </div>
    <?php
});
