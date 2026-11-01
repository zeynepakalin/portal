-- Mevcut veritabanını Türkçe karakterlere geçirir (eski ASCII örnek veriyi düzeltir).
-- phpMyAdmin: Dosyayı UTF-8 olarak içe aktarın veya SQL sekmesinden yapıştırın.
USE company_portal;

ALTER DATABASE company_portal CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

ALTER TABLE news CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE news ADD COLUMN IF NOT EXISTS image_url VARCHAR(255) NULL AFTER category;
ALTER TABLE news MODIFY image_url TEXT NULL;
ALTER TABLE birthdays CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE new_joiners CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE food_menus CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE condolences CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Haberler (ilk kurulumdaki ASCII metinler)
UPDATE news SET
    title = 'Q2 Hedef Toplantısı 28 Mart''ta',
    summary = 'Tüm birim yöneticileri ile çeyrek hedefleri değerlendirilecektir.'
WHERE title = 'Q2 Hedef Toplantisi 28 Mart''ta'
   OR title LIKE 'Q2 Hedef Toplantisi%';

UPDATE news SET
    title = 'Bilgi Güvenliği Eğitimi Açıldı',
    summary = 'Tüm çalışanların zorunlu eğitimi 15 Nisan''a kadar tamamlaması beklenmektedir.'
WHERE title = 'Bilgi Guvenligi Egitimi Acildi'
   OR title LIKE 'Bilgi Guvenligi%';

-- Doğum günleri
UPDATE birthdays SET department = 'İnsan Kaynakları'
WHERE department IN ('Insan Kaynaklari', 'Insan Kaynakları');

-- Yeni katılanlar
UPDATE new_joiners SET
    full_name = 'Cansu Yıldız',
    role = 'Ön yüz geliştirici',
    department = 'Yazılım Geliştirme'
WHERE full_name = 'Cansu Yildiz'
   OR department = 'Yazilim Gelistirme';

UPDATE new_joiners SET
    full_name = 'Mert Aydın',
    role = 'Satın Alma Uzmanı',
    department = 'Satın Alma'
WHERE full_name = 'Mert Aydin'
   OR department = 'Satin Alma'
   OR role LIKE 'Satin Alma%';

-- Yemek menüsü (aynı bozuk çorba adına göre)
UPDATE food_menus SET
    soup = 'Ezogelin Çorbası',
    main_course = 'Fırında Tavuk But',
    side_dish = 'Bulgur Pilavı',
    dessert = 'Sütlaç'
WHERE soup = 'Ezogelin Corbasi'
   OR main_course = 'Firinda Tavuk But'
   OR side_dish = 'Bulgur Pilavi';

-- Başsağlığı metinleri
UPDATE condolences SET
    full_name = 'Mehmet Özkan',
    relation_info = 'Muhasebe biriminden Ayşe Özkan''ın babası',
    message = 'Merhuma Allah''tan rahmet, ailesine ve yakınlarına başsağlığı dileriz.'
WHERE full_name = 'Mehmet Ozkan'
   OR relation_info LIKE '%Ayse Ozkan%';
