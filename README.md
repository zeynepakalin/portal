# Kurumsal intranet portalı (PHP)

Bu proje, şirket içi kullanım için temel bir İntranet portalı iskeleti sunar.

## İçerik modülleri

- Haberler ve duyurular
- Doğum günleri
- Aramıza yeni katılanlar
- Yemek menüsü
- Acı kaybımız
- Hava durumu

## Kurulum

1. `database/schema.sql` dosyasını phpMyAdmin veya MySQL CLI ile çalıştırın.
2. Eski veride Türkçe harf eksikse `database/fix_turkish_data.sql` dosyasını phpMyAdmin’de çalıştırın.
3. `config/database.php` dosyasındaki bağlantı bilgilerini kontrol edin.
4. XAMPP Apache ve MySQL servislerini başlatın.
5. Tarayıcıdan `http://localhost/portal` adresine gidin.

## Dosya yapısı

- `index.php`: Ana sayfa (pano)
- `news.php`: Tüm haberler
- `admin/index.php`: Haber ekleme ekranı
- `includes/`: Ortak bootstrap, layout ve repository fonksiyonları
- `assets/`: CSS ve JS
- `database/schema.sql`: Tablo ve örnek veri kurulumu (utf8mb4)
