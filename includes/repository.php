<?php
declare(strict_types=1);

function getDashboardStats(PDO $pdo): array
{
    return [
        'news' => (int)$pdo->query('SELECT COUNT(*) FROM news')->fetchColumn(),
        'birthdays' => (int)$pdo->query('SELECT COUNT(*) FROM birthdays WHERE MONTH(birth_date) = MONTH(CURDATE())')->fetchColumn(),
        'joiners' => (int)$pdo->query('SELECT COUNT(*) FROM new_joiners WHERE MONTH(join_date) = MONTH(CURDATE())')->fetchColumn(),
    ];
}

function getNews(PDO $pdo, int $limit = 5): array
{
    $hasImageColumn = newsHasImageColumn($pdo);
    $hasOrderColumn = newsHasDisplayOrderColumn($pdo);
    $select = $hasImageColumn
        ? 'SELECT id, title, summary, category, published_at, image_url FROM news'
        : 'SELECT id, title, summary, category, published_at FROM news';
    $orderBy = $hasOrderColumn
        ? ' ORDER BY (display_order IS NULL) ASC, display_order ASC, published_at DESC, id DESC'
        : ' ORDER BY published_at DESC, id DESC';
    $stmt = $pdo->prepare($select . $orderBy . ' LIMIT :limit');
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    $rows = $stmt->fetchAll();

    foreach ($rows as &$row) {
        if (!isset($row['image_url']) || trim((string)$row['image_url']) === '') {
            $row['image_url'] = 'https://images.unsplash.com/photo-1497366811353-6870744d04b2?auto=format&fit=crop&w=1200&q=80';
        }
    }
    unset($row);

    return $rows;
}

function getBirthdays(PDO $pdo, int $limit = 6): array
{
    $stmt = $pdo->prepare('
        SELECT full_name, department, birth_date
        FROM birthdays
        WHERE MONTH(birth_date) = MONTH(CURDATE())
        ORDER BY DAY(birth_date) ASC
        LIMIT :limit
    ');
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

function getNewJoiners(PDO $pdo, int $limit = 6): array
{
    $stmt = $pdo->prepare('
        SELECT full_name, role, department, join_date
        FROM new_joiners
        ORDER BY join_date DESC
        LIMIT :limit
    ');
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

function foodMenusHasMenuItemsColumn(PDO $pdo): bool
{
    static $has = null;
    if ($has !== null) {
        return $has;
    }
    try {
        $stmt = $pdo->query("SHOW COLUMNS FROM food_menus LIKE 'menu_items'");
        $exists = (bool)($stmt && $stmt->fetch());
        if (!$exists) {
            $pdo->exec('ALTER TABLE food_menus ADD COLUMN menu_items TEXT NULL');
            $stmt = $pdo->query("SHOW COLUMNS FROM food_menus LIKE 'menu_items'");
            $exists = (bool)($stmt && $stmt->fetch());
        }
        $has = $exists;
    } catch (Throwable $e) {
        $has = false;
    }

    return $has;
}

function getTodayMenu(PDO $pdo): array
{
    $cols = foodMenusHasMenuItemsColumn($pdo)
        ? 'soup, main_course, side_dish, salad, dessert, menu_items'
        : 'soup, main_course, side_dish, salad, dessert';
    $stmt = $pdo->prepare('SELECT ' . $cols . ' FROM food_menus WHERE menu_date = CURDATE() LIMIT 1');
    $stmt->execute();
    $result = $stmt->fetch();

    if (!$result) {
        return [];
    }

    return $result;
}

/** @return array<int, array<string, string>> */
function getMenusForDateRange(PDO $pdo, string $startDate, string $endDate): array
{
    $hasItems = foodMenusHasMenuItemsColumn($pdo);
    $cols = $hasItems
        ? 'menu_date, soup, main_course, side_dish, salad, dessert, menu_items'
        : 'menu_date, soup, main_course, side_dish, salad, dessert';
    $stmt = $pdo->prepare('
        SELECT ' . $cols . '
        FROM food_menus
        WHERE menu_date BETWEEN :start_date AND :end_date
        ORDER BY menu_date ASC
    ');
    $stmt->execute([
        ':start_date' => $startDate,
        ':end_date' => $endDate,
    ]);

    return $stmt->fetchAll();
}

function getLosses(PDO $pdo, int $limit = 3): array
{
    $stmt = $pdo->prepare('SELECT full_name, relation_info, message, published_at FROM condolences ORDER BY published_at DESC LIMIT :limit');
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

function getNewsById(PDO $pdo, int $id): array
{
    if ($id <= 0) {
        return [];
    }
    $hasImageColumn = newsHasImageColumn($pdo);
    $cols = $hasImageColumn
        ? 'id, title, summary, category, published_at, image_url'
        : 'id, title, summary, category, published_at';
    $stmt = $pdo->prepare('SELECT ' . $cols . ' FROM news WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $id]);
    $row = $stmt->fetch();
    return $row ?: [];
}

function updateNews(
    PDO $pdo,
    int $id,
    string $title,
    string $summary,
    string $category,
    ?string $imageUrl = null
): bool {
    if ($id <= 0 || $title === '' || $summary === '') {
        return false;
    }
    $hasImageColumn = newsHasImageColumn($pdo);
    if ($hasImageColumn) {
        $stmt = $pdo->prepare('
            UPDATE news
            SET title = :title, summary = :summary, category = :category, image_url = :image_url
            WHERE id = :id
            LIMIT 1
        ');
        $stmt->execute([
            ':title' => $title,
            ':summary' => $summary,
            ':category' => $category,
            ':image_url' => $imageUrl,
            ':id' => $id,
        ]);
    } else {
        $stmt = $pdo->prepare('
            UPDATE news
            SET title = :title, summary = :summary, category = :category
            WHERE id = :id
            LIMIT 1
        ');
        $stmt->execute([
            ':title' => $title,
            ':summary' => $summary,
            ':category' => $category,
            ':id' => $id,
        ]);
    }

    return $stmt->rowCount() > 0;
}

function deleteNews(PDO $pdo, int $id): bool
{
    if ($id <= 0) {
        return false;
    }

    $hasImageColumn = newsHasImageColumn($pdo);
    $selectSql = $hasImageColumn
        ? 'SELECT image_url FROM news WHERE id = :id'
        : 'SELECT NULL AS image_url FROM news WHERE id = :id';

    $stmt = $pdo->prepare($selectSql);
    $stmt->execute([':id' => $id]);
    $row = $stmt->fetch();
    if (!$row) {
        return false;
    }

    // Silme işlemi asla fatal hataya düşmemeli.
    // Yerel yüklenen görsel varsa (uploads/news), hata vermeden temizlemeye çalış.
    try {
        $imageUrl = (string)($row['image_url'] ?? '');
        if ($hasImageColumn && $imageUrl !== '') {
            $app = require __DIR__ . '/../config/app.php';
            $base = rtrim((string)($app['base_url'] ?? '/portal'), '/');
            $needle = $base . '/uploads/news/';

            if (strncmp($imageUrl, $needle, strlen($needle)) === 0) {
                $pathPart = parse_url($imageUrl, PHP_URL_PATH);
                if (is_string($pathPart) && $pathPart !== '') {
                    $name = basename($pathPart);
                    if ($name !== '' && strpos($name, '..') === false) {
                        $dir = realpath(__DIR__ . '/../uploads/news');
                        if ($dir !== false) {
                            $full = realpath($dir . DIRECTORY_SEPARATOR . $name);
                            $dirPrefix = $dir . DIRECTORY_SEPARATOR;
                            if ($full !== false && strncmp($full, $dirPrefix, strlen($dirPrefix)) === 0 && is_file($full)) {
                                @unlink($full);
                            }
                        }
                    }
                }
            }
        }
    } catch (Throwable $e) {
        // Yoksay: kayıt silme devam etmeli.
    }

    $del = $pdo->prepare('DELETE FROM news WHERE id = :id LIMIT 1');
    $del->execute([':id' => $id]);

    return $del->rowCount() > 0;
}

function addNews(PDO $pdo, string $title, string $summary, string $category, ?string $imageUrl = null): void
{
    $hasImageColumn = newsHasImageColumn($pdo);
    $hasOrderColumn = newsHasDisplayOrderColumn($pdo);
    $newDisplayOrder = null;
    if ($hasOrderColumn) {
        $hasManual = (int)$pdo->query('SELECT COUNT(*) FROM news WHERE display_order IS NOT NULL')->fetchColumn();
        if ($hasManual > 0) {
            $maxOrder = (int)$pdo->query('SELECT COALESCE(MAX(display_order), 0) FROM news')->fetchColumn();
            $newDisplayOrder = $maxOrder + 1;
        }
    }

    if ($hasImageColumn) {
        if ($hasOrderColumn) {
            $stmt = $pdo->prepare('
                INSERT INTO news (title, summary, category, image_url, published_at, display_order)
                VALUES (:title, :summary, :category, :image_url, NOW(), :display_order)
            ');
            $stmt->execute([
                ':title' => $title,
                ':summary' => $summary,
                ':category' => $category,
                ':image_url' => $imageUrl,
                ':display_order' => $newDisplayOrder,
            ]);
        } else {
            $stmt = $pdo->prepare('
                INSERT INTO news (title, summary, category, image_url, published_at)
                VALUES (:title, :summary, :category, :image_url, NOW())
            ');
            $stmt->execute([
                ':title' => $title,
                ':summary' => $summary,
                ':category' => $category,
                ':image_url' => $imageUrl,
            ]);
        }
        return;
    }

    if ($hasOrderColumn) {
        $stmt = $pdo->prepare('
            INSERT INTO news (title, summary, category, published_at, display_order)
            VALUES (:title, :summary, :category, NOW(), :display_order)
        ');
        $stmt->execute([
            ':title' => $title,
            ':summary' => $summary,
            ':category' => $category,
            ':display_order' => $newDisplayOrder,
        ]);
    } else {
        $stmt = $pdo->prepare('
            INSERT INTO news (title, summary, category, published_at)
            VALUES (:title, :summary, :category, NOW())
        ');
        $stmt->execute([
            ':title' => $title,
            ':summary' => $summary,
            ':category' => $category,
        ]);
    }
}

function newsHasImageColumn(PDO $pdo): bool
{
    static $hasColumn = null;

    if ($hasColumn !== null) {
        return $hasColumn;
    }

    $stmt = $pdo->query("SHOW COLUMNS FROM news LIKE 'image_url'");
    $hasColumn = (bool)$stmt->fetch();
    return $hasColumn;
}

function newsHasDisplayOrderColumn(PDO $pdo): bool
{
    static $hasColumn = null;
    if ($hasColumn !== null) {
        return $hasColumn;
    }
    $stmt = $pdo->query("SHOW COLUMNS FROM news LIKE 'display_order'");
    $exists = (bool)$stmt->fetch();
    if (!$exists) {
        $pdo->exec('ALTER TABLE news ADD COLUMN display_order INT NULL');
        $stmt = $pdo->query("SHOW COLUMNS FROM news LIKE 'display_order'");
        $exists = (bool)$stmt->fetch();
    }
    $hasColumn = $exists;
    return $hasColumn;
}

function moveNewsByDirection(PDO $pdo, int $id, string $direction): bool
{
    if ($id <= 0 || !in_array($direction, ['up', 'down'], true)) {
        return false;
    }
    if (!newsHasDisplayOrderColumn($pdo)) {
        return false;
    }

    $rows = $pdo->query('
        SELECT id
        FROM news
        ORDER BY (display_order IS NULL) ASC, display_order ASC, published_at DESC, id DESC
    ')->fetchAll();
    if (!$rows) {
        return false;
    }
    $ids = array_map(static fn(array $r): int => (int)$r['id'], $rows);
    $index = array_search($id, $ids, true);
    if ($index === false) {
        return false;
    }
    $swapWith = $direction === 'up' ? $index - 1 : $index + 1;
    if ($swapWith < 0 || $swapWith >= count($ids)) {
        return false;
    }
    $tmp = $ids[$index];
    $ids[$index] = $ids[$swapWith];
    $ids[$swapWith] = $tmp;

    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare('UPDATE news SET display_order = :display_order WHERE id = :id');
        foreach ($ids as $pos => $newsId) {
            $stmt->execute([
                ':display_order' => $pos + 1,
                ':id' => $newsId,
            ]);
        }
        $pdo->commit();
        return true;
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

function getBirthdaysAdmin(PDO $pdo): array
{
    return $pdo->query('
        SELECT id, full_name, department, birth_date
        FROM birthdays
        ORDER BY MONTH(birth_date), DAY(birth_date), full_name
    ')->fetchAll();
}

function getBirthdayById(PDO $pdo, int $id): array
{
    if ($id <= 0) {
        return [];
    }
    $stmt = $pdo->prepare('
        SELECT id, full_name, department, birth_date
        FROM birthdays
        WHERE id = :id
        LIMIT 1
    ');
    $stmt->execute([':id' => $id]);
    $row = $stmt->fetch();
    return $row ?: [];
}

function updateBirthday(PDO $pdo, int $id, string $fullName, string $department, string $birthDateYmd): bool
{
    if ($id <= 0) {
        return false;
    }
    $stmt = $pdo->prepare('
        UPDATE birthdays
        SET full_name = :full_name, department = :department, birth_date = :birth_date
        WHERE id = :id
        LIMIT 1
    ');
    $stmt->execute([
        ':full_name' => $fullName,
        ':department' => $department,
        ':birth_date' => $birthDateYmd,
        ':id' => $id,
    ]);

    return $stmt->rowCount() > 0;
}

function addBirthday(PDO $pdo, string $fullName, string $department, string $birthDateYmd): void
{
    $stmt = $pdo->prepare('
        INSERT INTO birthdays (full_name, department, birth_date)
        VALUES (:full_name, :department, :birth_date)
    ');
    $stmt->execute([
        ':full_name' => $fullName,
        ':department' => $department,
        ':birth_date' => $birthDateYmd,
    ]);
}

function deleteBirthday(PDO $pdo, int $id): bool
{
    if ($id <= 0) {
        return false;
    }
    $stmt = $pdo->prepare('DELETE FROM birthdays WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $id]);

    return $stmt->rowCount() > 0;
}

function getJoinersAdmin(PDO $pdo): array
{
    return $pdo->query('
        SELECT id, full_name, role, department, join_date
        FROM new_joiners
        ORDER BY join_date DESC, id DESC
    ')->fetchAll();
}

function getJoinerById(PDO $pdo, int $id): array
{
    if ($id <= 0) {
        return [];
    }
    $stmt = $pdo->prepare('
        SELECT id, full_name, role, department, join_date
        FROM new_joiners
        WHERE id = :id
        LIMIT 1
    ');
    $stmt->execute([':id' => $id]);
    $row = $stmt->fetch();
    return $row ?: [];
}

function updateJoiner(PDO $pdo, int $id, string $fullName, string $role, string $department, string $joinDateYmd): bool
{
    if ($id <= 0) {
        return false;
    }
    $stmt = $pdo->prepare('
        UPDATE new_joiners
        SET full_name = :full_name, role = :role, department = :department, join_date = :join_date
        WHERE id = :id
        LIMIT 1
    ');
    $stmt->execute([
        ':full_name' => $fullName,
        ':role' => $role,
        ':department' => $department,
        ':join_date' => $joinDateYmd,
        ':id' => $id,
    ]);

    return $stmt->rowCount() > 0;
}

function addJoiner(PDO $pdo, string $fullName, string $role, string $department, string $joinDateYmd): void
{
    $stmt = $pdo->prepare('
        INSERT INTO new_joiners (full_name, role, department, join_date)
        VALUES (:full_name, :role, :department, :join_date)
    ');
    $stmt->execute([
        ':full_name' => $fullName,
        ':role' => $role,
        ':department' => $department,
        ':join_date' => $joinDateYmd,
    ]);
}

function deleteJoiner(PDO $pdo, int $id): bool
{
    if ($id <= 0) {
        return false;
    }
    $stmt = $pdo->prepare('DELETE FROM new_joiners WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $id]);

    return $stmt->rowCount() > 0;
}

function getFoodMenusAdmin(PDO $pdo, int $limit = 30): array
{
    $hasItems = foodMenusHasMenuItemsColumn($pdo);
    $cols = $hasItems
        ? 'id, menu_date, soup, main_course, side_dish, salad, dessert, menu_items'
        : 'id, menu_date, soup, main_course, side_dish, salad, dessert';
    $stmt = $pdo->prepare('
        SELECT ' . $cols . '
        FROM food_menus
        ORDER BY menu_date DESC
        LIMIT :limit
    ');
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->fetchAll();
}

function upsertFoodMenu(
    PDO $pdo,
    string $menuDateYmd,
    string $soup,
    string $mainCourse,
    string $sideDish,
    string $salad,
    string $dessert,
    ?string $menuItemsJson = null
): void {
    if ($menuItemsJson === null) {
        $menuItemsJson = json_encode(
            [$soup, $mainCourse, $sideDish, $salad, $dessert],
            JSON_UNESCAPED_UNICODE
        );
    }

    if (foodMenusHasMenuItemsColumn($pdo)) {
        $stmt = $pdo->prepare('
            INSERT INTO food_menus (menu_date, soup, main_course, side_dish, salad, dessert, menu_items)
            VALUES (:menu_date, :soup, :main_course, :side_dish, :salad, :dessert, :menu_items)
            ON DUPLICATE KEY UPDATE
                soup = VALUES(soup),
                main_course = VALUES(main_course),
                side_dish = VALUES(side_dish),
                salad = VALUES(salad),
                dessert = VALUES(dessert),
                menu_items = VALUES(menu_items)
        ');
        $stmt->execute([
            ':menu_date' => $menuDateYmd,
            ':soup' => $soup,
            ':main_course' => $mainCourse,
            ':side_dish' => $sideDish,
            ':salad' => $salad,
            ':dessert' => $dessert,
            ':menu_items' => $menuItemsJson,
        ]);

        return;
    }

    $stmt = $pdo->prepare('
        INSERT INTO food_menus (menu_date, soup, main_course, side_dish, salad, dessert)
        VALUES (:menu_date, :soup, :main_course, :side_dish, :salad, :dessert)
        ON DUPLICATE KEY UPDATE
            soup = VALUES(soup),
            main_course = VALUES(main_course),
            side_dish = VALUES(side_dish),
            salad = VALUES(salad),
            dessert = VALUES(dessert)
    ');
    $stmt->execute([
        ':menu_date' => $menuDateYmd,
        ':soup' => $soup,
        ':main_course' => $mainCourse,
        ':side_dish' => $sideDish,
        ':salad' => $salad,
        ':dessert' => $dessert,
    ]);
}

function updateFoodMenuById(
    PDO $pdo,
    int $id,
    string $menuDateYmd,
    string $soup,
    string $mainCourse,
    string $sideDish,
    string $salad,
    string $dessert,
    ?string $menuItemsJson = null
): bool {
    if ($id <= 0) {
        return false;
    }
    if ($menuItemsJson === null) {
        $menuItemsJson = json_encode(
            [$soup, $mainCourse, $sideDish, $salad, $dessert],
            JSON_UNESCAPED_UNICODE
        );
    }

    if (foodMenusHasMenuItemsColumn($pdo)) {
        $stmt = $pdo->prepare('
            UPDATE food_menus
            SET menu_date = :menu_date,
                soup = :soup,
                main_course = :main_course,
                side_dish = :side_dish,
                salad = :salad,
                dessert = :dessert,
                menu_items = :menu_items
            WHERE id = :id
            LIMIT 1
        ');
        $stmt->execute([
            ':menu_date' => $menuDateYmd,
            ':soup' => $soup,
            ':main_course' => $mainCourse,
            ':side_dish' => $sideDish,
            ':salad' => $salad,
            ':dessert' => $dessert,
            ':menu_items' => $menuItemsJson,
            ':id' => $id,
        ]);

        return $stmt->rowCount() > 0;
    }

    $stmt = $pdo->prepare('
        UPDATE food_menus
        SET menu_date = :menu_date,
            soup = :soup,
            main_course = :main_course,
            side_dish = :side_dish,
            salad = :salad,
            dessert = :dessert
        WHERE id = :id
        LIMIT 1
    ');
    $stmt->execute([
        ':menu_date' => $menuDateYmd,
        ':soup' => $soup,
        ':main_course' => $mainCourse,
        ':side_dish' => $sideDish,
        ':salad' => $salad,
        ':dessert' => $dessert,
        ':id' => $id,
    ]);

    return $stmt->rowCount() > 0;
}

function getFoodMenuById(PDO $pdo, int $id): array
{
    if ($id <= 0) {
        return [];
    }
    $hasItems = foodMenusHasMenuItemsColumn($pdo);
    $cols = $hasItems
        ? 'id, menu_date, soup, main_course, side_dish, salad, dessert, menu_items'
        : 'id, menu_date, soup, main_course, side_dish, salad, dessert';
    $stmt = $pdo->prepare('SELECT ' . $cols . ' FROM food_menus WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $id]);
    $row = $stmt->fetch();
    return $row ?: [];
}

function deleteFoodMenu(PDO $pdo, int $id): bool
{
    if ($id <= 0) {
        return false;
    }
    $stmt = $pdo->prepare('DELETE FROM food_menus WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $id]);

    return $stmt->rowCount() > 0;
}

/** Bugünden önceki menü tarihlerini siler (bugün ve gelecek kalır). Tarih uygulama saat dilimindedir. */
function deleteExpiredFoodMenus(PDO $pdo): int
{
    $today = date('Y-m-d');
    $del = $pdo->prepare('DELETE FROM food_menus WHERE menu_date < :today');
    $del->execute([':today' => $today]);

    return $del->rowCount();
}

function getCondolencesAdmin(PDO $pdo): array
{
    return $pdo->query('
        SELECT id, full_name, relation_info, message, published_at
        FROM condolences
        ORDER BY published_at DESC, id DESC
    ')->fetchAll();
}

function getCondolenceById(PDO $pdo, int $id): array
{
    if ($id <= 0) {
        return [];
    }
    $stmt = $pdo->prepare('
        SELECT id, full_name, relation_info, message, published_at
        FROM condolences
        WHERE id = :id
        LIMIT 1
    ');
    $stmt->execute([':id' => $id]);
    $row = $stmt->fetch();
    return $row ?: [];
}

function updateCondolence(PDO $pdo, int $id, string $fullName, string $relationInfo, string $message): bool
{
    if ($id <= 0) {
        return false;
    }
    $stmt = $pdo->prepare('
        UPDATE condolences
        SET full_name = :full_name, relation_info = :relation_info, message = :message
        WHERE id = :id
        LIMIT 1
    ');
    $stmt->execute([
        ':full_name' => $fullName,
        ':relation_info' => $relationInfo,
        ':message' => $message,
        ':id' => $id,
    ]);

    return $stmt->rowCount() > 0;
}

function addCondolence(PDO $pdo, string $fullName, string $relationInfo, string $message): void
{
    $stmt = $pdo->prepare('
        INSERT INTO condolences (full_name, relation_info, message, published_at)
        VALUES (:full_name, :relation_info, :message, NOW())
    ');
    $stmt->execute([
        ':full_name' => $fullName,
        ':relation_info' => $relationInfo,
        ':message' => $message,
    ]);
}

function deleteCondolence(PDO $pdo, int $id): bool
{
    if ($id <= 0) {
        return false;
    }
    $stmt = $pdo->prepare('DELETE FROM condolences WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $id]);

    return $stmt->rowCount() > 0;
}

/** 1 = en düşük ruh hali, 5 = en iyi. Eski metin anahtarları özetlerde sayıya çevrilir. */
function moodRowToScore(string $mood): ?int
{
    $m = trim($mood);
    if (preg_match('/^[1-5]$/', $m)) {
        return (int) $m;
    }
    static $legacy = ['hard' => 1, 'tired' => 2, 'ok' => 3, 'good' => 4, 'great' => 5];

    return $legacy[$m] ?? null;
}

/** @return list<string> */
function getMoodScoreKeys(): array
{
    return ['1', '2', '3', '4', '5'];
}

function submitMoodCheckin(PDO $pdo, string $mood, string $token): bool
{
    ensureEngagementTables($pdo);
    $allowed = getMoodScoreKeys();
    if (!in_array($mood, $allowed, true) || $token === '') {
        return false;
    }
    $today = date('Y-m-d');
    $stmt = $pdo->prepare('
        INSERT INTO mood_checkins (mood, respondent_token, respond_date)
        VALUES (:mood, :token, :respond_date)
    ');
    try {
        $stmt->execute([
            ':mood' => $mood,
            ':token' => $token,
            ':respond_date' => $today,
        ]);
    } catch (PDOException $e) {
        if ((int)$e->getCode() === 23000) {
            return false;
        }
        throw $e;
    }

    return $stmt->rowCount() > 0;
}

function hasMoodCheckinToday(PDO $pdo, string $token): bool
{
    ensureEngagementTables($pdo);
    if ($token === '') {
        return false;
    }
    $today = date('Y-m-d');
    $stmt = $pdo->prepare('
        SELECT 1 FROM mood_checkins
        WHERE respondent_token = :token AND respond_date = :today
        LIMIT 1
    ');
    $stmt->execute([':token' => $token, ':today' => $today]);

    return (bool)$stmt->fetchColumn();
}

/** @return array<int, array{mood: string, count: int}> */
function getTodayMoodCounts(PDO $pdo): array
{
    ensureEngagementTables($pdo);
    $today = date('Y-m-d');
    $stmt = $pdo->prepare('
        SELECT mood, COUNT(*) AS cnt
        FROM mood_checkins
        WHERE respond_date = :today
        GROUP BY mood
    ');
    $stmt->execute([':today' => $today]);
    $rows = $stmt->fetchAll();
    $buckets = [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0];
    foreach ($rows as $row) {
        $s = moodRowToScore((string) $row['mood']);
        if ($s !== null) {
            $buckets[$s] += (int) $row['cnt'];
        }
    }
    $out = [];
    foreach ([1, 2, 3, 4, 5] as $n) {
        $out[] = ['mood' => (string) $n, 'count' => $buckets[$n]];
    }

    return $out;
}

function ensureEngagementTables(PDO $pdo): void
{
    static $isReady = false;
    if ($isReady) {
        return;
    }

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS events (
            id INT AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(180) NOT NULL,
            details TEXT NOT NULL,
            event_date DATE NOT NULL,
            event_time TIME NULL,
            category VARCHAR(40) NOT NULL DEFAULT 'Toplantı',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_event_date (event_date),
            INDEX idx_event_category (category)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS announcement_banners (
            id INT AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(160) NOT NULL,
            message TEXT NOT NULL,
            level VARCHAR(20) NOT NULL DEFAULT 'info',
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS polls (
            id INT AUTO_INCREMENT PRIMARY KEY,
            question VARCHAR(255) NOT NULL,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS poll_options (
            id INT AUTO_INCREMENT PRIMARY KEY,
            poll_id INT NOT NULL,
            option_text VARCHAR(180) NOT NULL,
            sort_order INT NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT fk_poll_options_poll FOREIGN KEY (poll_id) REFERENCES polls(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS poll_votes (
            id INT AUTO_INCREMENT PRIMARY KEY,
            poll_id INT NOT NULL,
            poll_option_id INT NOT NULL,
            voter_token VARCHAR(80) NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT fk_poll_votes_poll FOREIGN KEY (poll_id) REFERENCES polls(id) ON DELETE CASCADE,
            CONSTRAINT fk_poll_votes_option FOREIGN KEY (poll_option_id) REFERENCES poll_options(id) ON DELETE CASCADE,
            UNIQUE KEY uniq_poll_voter (poll_id, voter_token),
            INDEX idx_poll_option (poll_option_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS mood_checkins (
            id INT AUTO_INCREMENT PRIMARY KEY,
            mood VARCHAR(20) NOT NULL,
            respondent_token VARCHAR(80) NOT NULL,
            respond_date DATE NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_mood_day (respondent_token, respond_date),
            INDEX idx_mood_respond_date (respond_date)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS survey_links (
            id INT AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(180) NOT NULL,
            survey_url TEXT NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS training_videos (
            id INT AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(180) NOT NULL,
            video_url TEXT NOT NULL,
            details TEXT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    seedTurkeyOfficialHolidays2026($pdo);

    foodMenusHasMenuItemsColumn($pdo);

    $isReady = true;
}

function seedTurkeyOfficialHolidays2026(PDO $pdo): void
{
    $holidays = [
        ['2026-01-01', 'Yılbaşı', '2026 yılı resmi tatili.', null],
        ['2026-03-19', 'Ramazan Bayramı Arifesi (Yarım gün)', 'Öğleden sonra resmi tatil.', '13:00:00'],
        ['2026-03-20', 'Ramazan Bayramı 1. Gün', 'Resmi tatil.', null],
        ['2026-03-21', 'Ramazan Bayramı 2. Gün', 'Resmi tatil.', null],
        ['2026-03-22', 'Ramazan Bayramı 3. Gün', 'Resmi tatil.', null],
        ['2026-04-23', '23 Nisan Ulusal Egemenlik ve Çocuk Bayramı', 'Resmi tatil.', null],
        ['2026-05-01', 'Emek ve Dayanışma Günü', 'Resmi tatil.', null],
        ['2026-05-19', '19 Mayıs Atatürk\'ü Anma, Gençlik ve Spor Bayramı', 'Resmi tatil.', null],
        ['2026-05-26', 'Kurban Bayramı Arifesi (Yarım gün)', 'Öğleden sonra resmi tatil.', '13:00:00'],
        ['2026-05-27', 'Kurban Bayramı 1. Gün', 'Resmi tatil.', null],
        ['2026-05-28', 'Kurban Bayramı 2. Gün', 'Resmi tatil.', null],
        ['2026-05-29', 'Kurban Bayramı 3. Gün', 'Resmi tatil.', null],
        ['2026-05-30', 'Kurban Bayramı 4. Gün', 'Resmi tatil.', null],
        ['2026-07-15', '15 Temmuz Demokrasi ve Milli Birlik Günü', 'Resmi tatil.', null],
        ['2026-08-30', '30 Ağustos Zafer Bayramı', 'Resmi tatil.', null],
        ['2026-10-28', '29 Ekim Cumhuriyet Bayramı Arifesi (Yarım gün)', 'Öğleden sonra resmi tatil.', '13:00:00'],
        ['2026-10-29', '29 Ekim Cumhuriyet Bayramı', 'Resmi tatil.', null],
    ];

    $existsStmt = $pdo->prepare('
        SELECT id
        FROM events
        WHERE event_date = :event_date AND title = :title AND category = :category
        LIMIT 1
    ');
    $insertStmt = $pdo->prepare('
        INSERT INTO events (title, details, event_date, event_time, category)
        VALUES (:title, :details, :event_date, :event_time, :category)
    ');

    foreach ($holidays as $holiday) {
        $existsStmt->execute([
            ':event_date' => $holiday[0],
            ':title' => $holiday[1],
            ':category' => 'Resmi tatil',
        ]);
        if ($existsStmt->fetch()) {
            continue;
        }
        $insertStmt->execute([
            ':title' => $holiday[1],
            ':details' => $holiday[2],
            ':event_date' => $holiday[0],
            ':event_time' => $holiday[3],
            ':category' => 'Resmi tatil',
        ]);
    }
}

function getUpcomingEvents(PDO $pdo, int $limit = 8): array
{
    ensureEngagementTables($pdo);
    $stmt = $pdo->prepare('
        SELECT id, title, details, event_date, event_time, category
        FROM events
        WHERE event_date >= CURDATE()
        ORDER BY event_date ASC, event_time ASC, id ASC
        LIMIT :limit
    ');
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

function getNextOfficialHoliday(PDO $pdo): array
{
    ensureEngagementTables($pdo);
    $stmt = $pdo->prepare('
        SELECT id, title, details, event_date, event_time, category
        FROM events
        WHERE category = :category AND event_date >= CURDATE()
        ORDER BY event_date ASC, event_time ASC, id ASC
        LIMIT 1
    ');
    $stmt->execute([':category' => 'Resmi tatil']);
    $row = $stmt->fetch();
    return $row ?: [];
}

function getEventsForMonth(PDO $pdo, int $year, int $month): array
{
    ensureEngagementTables($pdo);
    $start = sprintf('%04d-%02d-01', $year, $month);
    $end = date('Y-m-t', strtotime($start));
    $stmt = $pdo->prepare('
        SELECT id, title, details, event_date, event_time, category
        FROM events
        WHERE event_date BETWEEN :start AND :end
        ORDER BY event_date ASC, event_time ASC, id ASC
    ');
    $stmt->execute([
        ':start' => $start,
        ':end' => $end,
    ]);
    return $stmt->fetchAll();
}

function getEventsAdmin(PDO $pdo, int $limit = 100): array
{
    ensureEngagementTables($pdo);
    $stmt = $pdo->prepare('
        SELECT id, title, details, event_date, event_time, category
        FROM events
        ORDER BY event_date DESC, event_time DESC, id DESC
        LIMIT :limit
    ');
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

function getEventById(PDO $pdo, int $id): array
{
    ensureEngagementTables($pdo);
    if ($id <= 0) {
        return [];
    }
    $stmt = $pdo->prepare('
        SELECT id, title, details, event_date, event_time, category
        FROM events
        WHERE id = :id
        LIMIT 1
    ');
    $stmt->execute([':id' => $id]);
    $row = $stmt->fetch();
    return $row ?: [];
}

function updateEvent(
    PDO $pdo,
    int $id,
    string $title,
    string $details,
    string $eventDateYmd,
    ?string $eventTimeHm,
    string $category
): bool {
    ensureEngagementTables($pdo);
    if ($id <= 0) {
        return false;
    }
    $stmt = $pdo->prepare('
        UPDATE events
        SET title = :title,
            details = :details,
            event_date = :event_date,
            event_time = :event_time,
            category = :category
        WHERE id = :id
        LIMIT 1
    ');
    $stmt->execute([
        ':title' => $title,
        ':details' => $details,
        ':event_date' => $eventDateYmd,
        ':event_time' => $eventTimeHm,
        ':category' => $category,
        ':id' => $id,
    ]);

    return $stmt->rowCount() > 0;
}

function addEvent(PDO $pdo, string $title, string $details, string $eventDateYmd, ?string $eventTimeHm, string $category): void
{
    ensureEngagementTables($pdo);
    $stmt = $pdo->prepare('
        INSERT INTO events (title, details, event_date, event_time, category)
        VALUES (:title, :details, :event_date, :event_time, :category)
    ');
    $stmt->execute([
        ':title' => $title,
        ':details' => $details,
        ':event_date' => $eventDateYmd,
        ':event_time' => $eventTimeHm,
        ':category' => $category,
    ]);
}

function deleteEvent(PDO $pdo, int $id): bool
{
    ensureEngagementTables($pdo);
    if ($id <= 0) {
        return false;
    }
    $stmt = $pdo->prepare('DELETE FROM events WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $id]);
    return $stmt->rowCount() > 0;
}

/**
 * Iframe’de oynatmayı aç: autoplay=1 sorgu parametrelerini kaldır, autoplay=0 ekle.
 */
function portalVideoIframeSrcDisableAutoplay(string $fullUrl): string
{
    $u = trim($fullUrl);
    if ($u === '' || strncmp($u, 'javascript:', 11) === 0 || strncmp($u, 'data:', 5) === 0) {
        return $u;
    }
    $parts = parse_url($u);
    if ($parts === false) {
        return $fullUrl;
    }
    $q = [];
    if (!empty($parts['query'])) {
        parse_str((string) $parts['query'], $q);
    }
    if (array_key_exists('autoplay', $q)) {
        unset($q['autoplay'], $q['Autoplay'], $q['autoPlay']);
    }
    $q['autoplay'] = '0';
    $scheme = ($parts['scheme'] ?? 'https') === 'http' ? 'http' : 'https';
    $host = (string) ($parts['host'] ?? '');
    $path = (string) ($parts['path'] ?? '');
    if ($host === '') {
        return $fullUrl;
    }
    $out = $scheme . '://' . $host . $path;
    if ($q !== []) {
        $out .= '?' . http_build_query($q);
    }
    if (!empty($parts['fragment'])) {
        $out .= '#' . (string) $parts['fragment'];
    }

    return $out;
}

/**
 * Ana sayfa iframe’i için: YouTube izleme linklerini embed adresine çevirir (diğer URL’ler olduğu gibi kalır).
 * Tüm YouTube / Vimeo oynatıcılarda otomatik başlamayı kapatır (sorgudaki autoplay=1’i ezer).
 */
function portalIframeSrcUrl(string $url): string
{
    $u = trim($url);
    if ($u === '') {
        return '';
    }
    if (preg_match('#(?:youtube|youtube-nocookie)\.com/embed/#i', $u)) {
        return portalVideoIframeSrcDisableAutoplay($u);
    }
    if (preg_match('#(^|\.)vimeo\.com$#i', (string) (parse_url($u)['host'] ?? '')) || preg_match('#player\.vimeo\.com#i', $u)) {
        return portalVideoIframeSrcDisableAutoplay($u);
    }
    $parts = parse_url($u);
    if (!empty($parts['host']) && preg_match('/(^|\.)(youtube|youtube-nocookie)\.com$/i', (string)$parts['host']) && !empty($parts['query'])) {
        parse_str((string)$parts['query'], $q);
        if (!empty($q['v']) && is_string($q['v'])) {
            $vid = preg_replace('/[^a-zA-Z0-9_-]/', '', $q['v']);
            if ($vid !== '') {
                return portalVideoIframeSrcDisableAutoplay('https://www.youtube.com/embed/' . $vid);
            }
        }
    }
    if (preg_match('#^https?://(?:www\.)?youtu\.be/([a-zA-Z0-9_-]+)#i', $u, $m)) {
        return portalVideoIframeSrcDisableAutoplay('https://www.youtube.com/embed/' . $m[1]);
    }

    return $u;
}

function getLatestSurveyLink(PDO $pdo): array
{
    ensureEngagementTables($pdo);
    $stmt = $pdo->query('
        SELECT id, title, survey_url, created_at
        FROM survey_links
        ORDER BY id DESC
        LIMIT 1
    ');
    $row = $stmt->fetch();
    return $row ?: [];
}

function getSurveyLinkById(PDO $pdo, int $id): array
{
    ensureEngagementTables($pdo);
    if ($id <= 0) {
        return [];
    }
    $stmt = $pdo->prepare('
        SELECT id, title, survey_url, created_at
        FROM survey_links
        WHERE id = :id
        LIMIT 1
    ');
    $stmt->execute([':id' => $id]);
    $row = $stmt->fetch();
    return $row ?: [];
}

function getSurveyLinksAdmin(PDO $pdo, int $limit = 20): array
{
    ensureEngagementTables($pdo);
    $stmt = $pdo->prepare('
        SELECT id, title, survey_url, created_at
        FROM survey_links
        ORDER BY created_at DESC, id DESC
        LIMIT :limit
    ');
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

function updateSurveyLink(PDO $pdo, int $id, string $title, string $surveyUrl): bool
{
    ensureEngagementTables($pdo);
    if ($id <= 0) {
        return false;
    }
    $stmt = $pdo->prepare('
        UPDATE survey_links
        SET title = :title, survey_url = :survey_url
        WHERE id = :id
        LIMIT 1
    ');
    $stmt->execute([
        ':title' => $title,
        ':survey_url' => $surveyUrl,
        ':id' => $id,
    ]);

    return $stmt->rowCount() > 0;
}

function addSurveyLink(PDO $pdo, string $title, string $surveyUrl): void
{
    ensureEngagementTables($pdo);
    $stmt = $pdo->prepare('
        INSERT INTO survey_links (title, survey_url)
        VALUES (:title, :survey_url)
    ');
    $stmt->execute([
        ':title' => $title,
        ':survey_url' => $surveyUrl,
    ]);
}

function deleteSurveyLink(PDO $pdo, int $id): bool
{
    ensureEngagementTables($pdo);
    if ($id <= 0) {
        return false;
    }
    $stmt = $pdo->prepare('DELETE FROM survey_links WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $id]);
    return $stmt->rowCount() > 0;
}

function getLatestTrainingVideo(PDO $pdo): array
{
    ensureEngagementTables($pdo);
    $stmt = $pdo->query('
        SELECT id, title, video_url, details, created_at
        FROM training_videos
        ORDER BY id DESC
        LIMIT 1
    ');
    $row = $stmt->fetch();
    return $row ?: [];
}

function getTrainingVideoById(PDO $pdo, int $id): array
{
    ensureEngagementTables($pdo);
    if ($id <= 0) {
        return [];
    }
    $stmt = $pdo->prepare('
        SELECT id, title, video_url, details, created_at
        FROM training_videos
        WHERE id = :id
        LIMIT 1
    ');
    $stmt->execute([':id' => $id]);
    $row = $stmt->fetch();
    return $row ?: [];
}

function getTrainingVideosAdmin(PDO $pdo, int $limit = 20): array
{
    ensureEngagementTables($pdo);
    $stmt = $pdo->prepare('
        SELECT id, title, video_url, details, created_at
        FROM training_videos
        ORDER BY created_at DESC, id DESC
        LIMIT :limit
    ');
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

function updateTrainingVideo(PDO $pdo, int $id, string $title, string $videoUrl, string $details): bool
{
    ensureEngagementTables($pdo);
    if ($id <= 0) {
        return false;
    }
    $stmt = $pdo->prepare('
        UPDATE training_videos
        SET title = :title, video_url = :video_url, details = :details
        WHERE id = :id
        LIMIT 1
    ');
    $stmt->execute([
        ':title' => $title,
        ':video_url' => $videoUrl,
        ':details' => $details,
        ':id' => $id,
    ]);

    return $stmt->rowCount() > 0;
}

function addTrainingVideo(PDO $pdo, string $title, string $videoUrl, string $details): void
{
    ensureEngagementTables($pdo);
    $stmt = $pdo->prepare('
        INSERT INTO training_videos (title, video_url, details)
        VALUES (:title, :video_url, :details)
    ');
    $stmt->execute([
        ':title' => $title,
        ':video_url' => $videoUrl,
        ':details' => $details,
    ]);
}

function deleteTrainingVideo(PDO $pdo, int $id): bool
{
    ensureEngagementTables($pdo);
    if ($id <= 0) {
        return false;
    }
    $stmt = $pdo->prepare('DELETE FROM training_videos WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $id]);
    return $stmt->rowCount() > 0;
}

function getActiveAnnouncementBanner(PDO $pdo): array
{
    ensureEngagementTables($pdo);
    $stmt = $pdo->query("
        SELECT id, title, message, level, updated_at
        FROM announcement_banners
        WHERE is_active = 1
        ORDER BY updated_at DESC, id DESC
        LIMIT 1
    ");
    $row = $stmt->fetch();
    return $row ?: [];
}

function upsertAnnouncementBanner(PDO $pdo, string $title, string $message, string $level): void
{
    ensureEngagementTables($pdo);
    $pdo->exec('UPDATE announcement_banners SET is_active = 0');
    $stmt = $pdo->prepare('
        INSERT INTO announcement_banners (title, message, level, is_active)
        VALUES (:title, :message, :level, 1)
    ');
    $stmt->execute([
        ':title' => $title,
        ':message' => $message,
        ':level' => $level,
    ]);
}

function updateAnnouncementBannerById(PDO $pdo, int $id, string $title, string $message, string $level): bool
{
    ensureEngagementTables($pdo);
    if ($id <= 0) {
        return false;
    }
    $stmt = $pdo->prepare('
        UPDATE announcement_banners
        SET title = :title, message = :message, level = :level, is_active = 1, updated_at = CURRENT_TIMESTAMP
        WHERE id = :id
        LIMIT 1
    ');
    $stmt->execute([
        ':title' => $title,
        ':message' => $message,
        ':level' => $level,
        ':id' => $id,
    ]);

    return $stmt->rowCount() > 0;
}

function clearAnnouncementBanner(PDO $pdo): void
{
    ensureEngagementTables($pdo);
    $pdo->exec('UPDATE announcement_banners SET is_active = 0');
}

function createPoll(PDO $pdo, string $question, array $options): void
{
    ensureEngagementTables($pdo);
    $opts = [];
    foreach ($options as $option) {
        $trimmed = trim((string)$option);
        if ($trimmed !== '') {
            $opts[] = $trimmed;
        }
    }
    if (count($opts) < 2) {
        throw new InvalidArgumentException('En az 2 seçenek gerekli.');
    }

    $pdo->beginTransaction();
    try {
        $pdo->exec('UPDATE polls SET is_active = 0');
        $stmt = $pdo->prepare('INSERT INTO polls (question, is_active) VALUES (:question, 1)');
        $stmt->execute([':question' => $question]);
        $pollId = (int)$pdo->lastInsertId();

        $optStmt = $pdo->prepare('
            INSERT INTO poll_options (poll_id, option_text, sort_order)
            VALUES (:poll_id, :option_text, :sort_order)
        ');
        foreach ($opts as $index => $text) {
            $optStmt->execute([
                ':poll_id' => $pollId,
                ':option_text' => $text,
                ':sort_order' => $index,
            ]);
        }
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

function closeActivePoll(PDO $pdo): void
{
    ensureEngagementTables($pdo);
    $pdo->exec('UPDATE polls SET is_active = 0 WHERE is_active = 1');
}

function getPollWithOptionsById(PDO $pdo, int $pollId): array
{
    ensureEngagementTables($pdo);
    if ($pollId <= 0) {
        return [];
    }
    $pollStmt = $pdo->prepare('
        SELECT id, question, is_active, created_at
        FROM polls
        WHERE id = :id
        LIMIT 1
    ');
    $pollStmt->execute([':id' => $pollId]);
    $poll = $pollStmt->fetch();
    if (!$poll) {
        return [];
    }

    $optStmt = $pdo->prepare('
        SELECT id, option_text, sort_order
        FROM poll_options
        WHERE poll_id = :poll_id
        ORDER BY sort_order ASC, id ASC
    ');
    $optStmt->execute([':poll_id' => $pollId]);
    $poll['options'] = $optStmt->fetchAll();
    return $poll;
}

function countPollVotes(PDO $pdo, int $pollId): int
{
    ensureEngagementTables($pdo);
    if ($pollId <= 0) {
        return 0;
    }
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM poll_votes WHERE poll_id = :poll_id');
    $stmt->execute([':poll_id' => $pollId]);
    return (int)$stmt->fetchColumn();
}

function updatePoll(PDO $pdo, int $pollId, string $question, array $optionTexts): void
{
    ensureEngagementTables($pdo);
    if ($pollId <= 0 || trim($question) === '') {
        throw new InvalidArgumentException('Geçersiz anket.');
    }

    $poll = getPollWithOptionsById($pdo, $pollId);
    if ($poll === []) {
        throw new InvalidArgumentException('Anket bulunamadı.');
    }

    $existing = $poll['options'] ?? [];
    $lines = [];
    foreach ($optionTexts as $option) {
        $lines[] = trim((string)$option);
    }
    if (count($lines) !== count($existing)) {
        throw new InvalidArgumentException('Seçenek sayısı korunmalı: ' . count($existing) . ' satır girin (her satır bir seçenek).');
    }
    foreach ($lines as $t) {
        if ($t === '') {
            throw new InvalidArgumentException('Seçenek metinleri boş olamaz.');
        }
    }
    if (count($lines) < 2) {
        throw new InvalidArgumentException('En az 2 seçenek gerekli.');
    }

    $pdo->beginTransaction();
    try {
        $pdo->prepare('UPDATE polls SET question = :question WHERE id = :id')->execute([
            ':question' => $question,
            ':id' => $pollId,
        ]);

        $upd = $pdo->prepare('UPDATE poll_options SET option_text = :option_text WHERE id = :id AND poll_id = :poll_id LIMIT 1');
        foreach ($existing as $i => $row) {
            $upd->execute([
                ':option_text' => $lines[$i],
                ':id' => (int)$row['id'],
                ':poll_id' => $pollId,
            ]);
        }
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

function getActivePollWithOptions(PDO $pdo): array
{
    ensureEngagementTables($pdo);
    $pollStmt = $pdo->query('
        SELECT id, question, created_at
        FROM polls
        WHERE is_active = 1
        ORDER BY created_at DESC, id DESC
        LIMIT 1
    ');
    $poll = $pollStmt->fetch();
    if (!$poll) {
        return [];
    }

    $optStmt = $pdo->prepare('
        SELECT id, option_text
        FROM poll_options
        WHERE poll_id = :poll_id
        ORDER BY sort_order ASC, id ASC
    ');
    $optStmt->execute([':poll_id' => (int)$poll['id']]);
    $poll['options'] = $optStmt->fetchAll();
    return $poll;
}

function hasVotedOnPoll(PDO $pdo, int $pollId, string $voterToken): bool
{
    ensureEngagementTables($pdo);
    if ($pollId <= 0 || $voterToken === '') {
        return false;
    }
    $stmt = $pdo->prepare('
        SELECT 1 FROM poll_votes
        WHERE poll_id = :poll_id AND voter_token = :voter_token
        LIMIT 1
    ');
    $stmt->execute([
        ':poll_id' => $pollId,
        ':voter_token' => $voterToken,
    ]);
    return (bool)$stmt->fetchColumn();
}

function submitPollVote(PDO $pdo, int $pollId, int $optionId, string $voterToken): bool
{
    ensureEngagementTables($pdo);
    if ($pollId <= 0 || $optionId <= 0 || $voterToken === '') {
        return false;
    }
    $check = $pdo->prepare('SELECT 1 FROM poll_options WHERE id = :option_id AND poll_id = :poll_id LIMIT 1');
    $check->execute([
        ':option_id' => $optionId,
        ':poll_id' => $pollId,
    ]);
    if (!(bool)$check->fetchColumn()) {
        return false;
    }

    $stmt = $pdo->prepare('
        INSERT IGNORE INTO poll_votes (poll_id, poll_option_id, voter_token)
        VALUES (:poll_id, :option_id, :voter_token)
    ');
    $stmt->execute([
        ':poll_id' => $pollId,
        ':option_id' => $optionId,
        ':voter_token' => $voterToken,
    ]);
    return $stmt->rowCount() > 0;
}

function getPollResults(PDO $pdo, int $pollId): array
{
    ensureEngagementTables($pdo);
    $stmt = $pdo->prepare('
        SELECT po.id, po.option_text, COUNT(pv.id) AS vote_count
        FROM poll_options po
        LEFT JOIN poll_votes pv ON pv.poll_option_id = po.id
        WHERE po.poll_id = :poll_id
        GROUP BY po.id, po.option_text
        ORDER BY po.sort_order ASC, po.id ASC
    ');
    $stmt->execute([':poll_id' => $pollId]);
    return $stmt->fetchAll();
}

function getPollsAdmin(PDO $pdo, int $limit = 20): array
{
    ensureEngagementTables($pdo);
    $stmt = $pdo->prepare('
        SELECT p.id, p.question, p.is_active, p.created_at, COUNT(v.id) AS total_votes
        FROM polls p
        LEFT JOIN poll_votes v ON v.poll_id = p.id
        GROUP BY p.id, p.question, p.is_active, p.created_at
        ORDER BY p.created_at DESC, p.id DESC
        LIMIT :limit
    ');
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}
