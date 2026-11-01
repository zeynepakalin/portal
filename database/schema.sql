CREATE DATABASE IF NOT EXISTS company_portal CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE company_portal;

CREATE TABLE IF NOT EXISTS news (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(180) NOT NULL,
    summary TEXT NOT NULL,
    category VARCHAR(50) NOT NULL DEFAULT 'Duyuru',
    image_url TEXT NULL,
    published_at DATETIME NOT NULL
);

CREATE TABLE IF NOT EXISTS birthdays (
    id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(120) NOT NULL,
    department VARCHAR(120) NOT NULL,
    birth_date DATE NOT NULL
);

CREATE TABLE IF NOT EXISTS new_joiners (
    id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(120) NOT NULL,
    role VARCHAR(120) NOT NULL,
    department VARCHAR(120) NOT NULL,
    join_date DATE NOT NULL
);

CREATE TABLE IF NOT EXISTS food_menus (
    id INT AUTO_INCREMENT PRIMARY KEY,
    menu_date DATE NOT NULL UNIQUE,
    soup VARCHAR(100) NOT NULL,
    main_course VARCHAR(100) NOT NULL,
    side_dish VARCHAR(100) NOT NULL,
    salad VARCHAR(100) NOT NULL,
    dessert VARCHAR(100) NOT NULL,
    menu_items TEXT NULL
);

CREATE TABLE IF NOT EXISTS condolences (
    id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(120) NOT NULL,
    relation_info VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    published_at DATETIME NOT NULL
);

CREATE TABLE IF NOT EXISTS events (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(180) NOT NULL,
    details TEXT NOT NULL,
    event_date DATE NOT NULL,
    event_time TIME NULL,
    category VARCHAR(40) NOT NULL DEFAULT 'Toplantı',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS announcement_banners (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(160) NOT NULL,
    message TEXT NOT NULL,
    level VARCHAR(20) NOT NULL DEFAULT 'info',
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS polls (
    id INT AUTO_INCREMENT PRIMARY KEY,
    question VARCHAR(255) NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS poll_options (
    id INT AUTO_INCREMENT PRIMARY KEY,
    poll_id INT NOT NULL,
    option_text VARCHAR(180) NOT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_poll_options_poll FOREIGN KEY (poll_id) REFERENCES polls(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS poll_votes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    poll_id INT NOT NULL,
    poll_option_id INT NOT NULL,
    voter_token VARCHAR(80) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_poll_votes_poll FOREIGN KEY (poll_id) REFERENCES polls(id) ON DELETE CASCADE,
    CONSTRAINT fk_poll_votes_option FOREIGN KEY (poll_option_id) REFERENCES poll_options(id) ON DELETE CASCADE,
    UNIQUE KEY uniq_poll_voter (poll_id, voter_token)
);

CREATE TABLE IF NOT EXISTS mood_checkins (
    id INT AUTO_INCREMENT PRIMARY KEY,
    mood VARCHAR(20) NOT NULL,
    respondent_token VARCHAR(80) NOT NULL,
    respond_date DATE NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_mood_day (respondent_token, respond_date),
    INDEX idx_mood_respond_date (respond_date)
);

CREATE TABLE IF NOT EXISTS survey_links (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(180) NOT NULL,
    survey_url TEXT NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS training_videos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(180) NOT NULL,
    video_url TEXT NOT NULL,
    details TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

INSERT INTO news (title, summary, category, image_url, published_at) VALUES
('Q2 Hedef Toplantısı 28 Mart''ta', 'Tüm birim yöneticileri ile çeyrek hedefleri değerlendirilecektir.', 'Duyuru', 'https://images.unsplash.com/photo-1519389950473-47ba0277781c?auto=format&fit=crop&w=1200&q=80', NOW()),
('Bilgi Güvenliği Eğitimi Açıldı', 'Tüm çalışanların zorunlu eğitimi 15 Nisan''a kadar tamamlaması beklenmektedir.', 'Haber', 'https://images.unsplash.com/photo-1454165804606-c3d57bc86b40?auto=format&fit=crop&w=1200&q=80', NOW());

INSERT INTO birthdays (full_name, department, birth_date) VALUES
('Selin Demir', 'İnsan Kaynakları', '1992-03-24'),
('Ahmet Korkmaz', 'Finans', '1990-03-25');

INSERT INTO new_joiners (full_name, role, department, join_date) VALUES
('Cansu Yıldız', 'Ön yüz geliştirici', 'Yazılım Geliştirme', CURDATE()),
('Mert Aydın', 'Satın Alma Uzmanı', 'Satın Alma', CURDATE());

INSERT INTO food_menus (menu_date, soup, main_course, side_dish, salad, dessert, menu_items) VALUES
(CURDATE(), 'Ezogelin Çorbası', 'Fırında Tavuk But', 'Bulgur Pilavı', 'Mevsim Salata', 'Sütlaç',
 '["Ezogelin Çorbası","Fırında Tavuk But","Bulgur Pilavı","Mevsim Salata","Sütlaç"]')
ON DUPLICATE KEY UPDATE
    soup = VALUES(soup),
    main_course = VALUES(main_course),
    side_dish = VALUES(side_dish),
    salad = VALUES(salad),
    dessert = VALUES(dessert),
    menu_items = VALUES(menu_items);

INSERT INTO condolences (full_name, relation_info, message, published_at) VALUES
('Mehmet Özkan', 'Muhasebe biriminden Ayşe Özkan''ın babası', 'Merhuma Allah''tan rahmet, ailesine ve yakınlarına başsağlığı dileriz.', NOW());

INSERT INTO events (title, details, event_date, event_time, category, created_at) VALUES
('Q2 Strateji Toplantısı', 'Çeyrek hedefleri ve KPI değerlendirmesi yapılacaktır.', DATE_ADD(CURDATE(), INTERVAL 2 DAY), '10:30:00', 'Toplantı', NOW()),
('İş Sağlığı Eğitimi', 'Zorunlu iş güvenliği eğitimi konferans salonunda yapılacaktır.', DATE_ADD(CURDATE(), INTERVAL 5 DAY), '14:00:00', 'Eğitim', NOW()),
('Yılbaşı', '2026 yılı resmi tatili.', '2026-01-01', NULL, 'Resmi tatil', NOW()),
('Ramazan Bayramı Arifesi (Yarım gün)', 'Öğleden sonra resmi tatil.', '2026-03-19', '13:00:00', 'Resmi tatil', NOW()),
('Ramazan Bayramı 1. Gün', 'Resmi tatil.', '2026-03-20', NULL, 'Resmi tatil', NOW()),
('Ramazan Bayramı 2. Gün', 'Resmi tatil.', '2026-03-21', NULL, 'Resmi tatil', NOW()),
('Ramazan Bayramı 3. Gün', 'Resmi tatil.', '2026-03-22', NULL, 'Resmi tatil', NOW()),
('23 Nisan Ulusal Egemenlik ve Çocuk Bayramı', 'Resmi tatil.', '2026-04-23', NULL, 'Resmi tatil', NOW()),
('Emek ve Dayanışma Günü', 'Resmi tatil.', '2026-05-01', NULL, 'Resmi tatil', NOW()),
('19 Mayıs Atatürk''ü Anma, Gençlik ve Spor Bayramı', 'Resmi tatil.', '2026-05-19', NULL, 'Resmi tatil', NOW()),
('Kurban Bayramı Arifesi (Yarım gün)', 'Öğleden sonra resmi tatil.', '2026-05-26', '13:00:00', 'Resmi tatil', NOW()),
('Kurban Bayramı 1. Gün', 'Resmi tatil.', '2026-05-27', NULL, 'Resmi tatil', NOW()),
('Kurban Bayramı 2. Gün', 'Resmi tatil.', '2026-05-28', NULL, 'Resmi tatil', NOW()),
('Kurban Bayramı 3. Gün', 'Resmi tatil.', '2026-05-29', NULL, 'Resmi tatil', NOW()),
('Kurban Bayramı 4. Gün', 'Resmi tatil.', '2026-05-30', NULL, 'Resmi tatil', NOW()),
('15 Temmuz Demokrasi ve Milli Birlik Günü', 'Resmi tatil.', '2026-07-15', NULL, 'Resmi tatil', NOW()),
('30 Ağustos Zafer Bayramı', 'Resmi tatil.', '2026-08-30', NULL, 'Resmi tatil', NOW()),
('29 Ekim Cumhuriyet Bayramı Arifesi (Yarım gün)', 'Öğleden sonra resmi tatil.', '2026-10-28', '13:00:00', 'Resmi tatil', NOW()),
('29 Ekim Cumhuriyet Bayramı', 'Resmi tatil.', '2026-10-29', NULL, 'Resmi tatil', NOW());

INSERT INTO survey_links (title, survey_url) VALUES
('Nisan 2026 Memnuniyet Anketi', 'https://example.com/memnuniyet-anketi');

INSERT INTO training_videos (title, video_url, details) VALUES
('İş Sağlığı ve Güvenliği Tanıtımı', 'https://www.youtube.com/embed/dQw4w9WgXcQ', 'Yeni başlayanlar için kısa eğitim videosu.');
