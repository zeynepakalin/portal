<?php
declare(strict_types=1);

return [
    'app_name' => 'Dedem Mekatronik',
    'timezone' => 'Europe/Istanbul',
    'base_url' => '/portal',
    /** Yönetim paneli: yalnızca bu kullanıcı adı ile giriş (küçük harf). */
    'admin_username' => getenv('PORTAL_ADMIN_USERNAME') ?: 'ikadmin',
    /** Aynı kullanıcının şifresi — canlıda mutlaka değiştirin. */
    'admin_password' => getenv('PORTAL_ADMIN_PASSWORD') ?: 'admin123',
    'weather' => [
        'latitude' => 38.634,
        'longitude' => 27.382,
        'address' => 'OSB 4. Kısım, Keçiliköy OSB, İsmail Sarıgözoğlu Caddesi No:5, 45030 Yunusemre/Manisa',
    ],
    /**
     * Eğitim videoları için izinli ortak alan önekleri (UNC). Boş dizi = tüm geçerli UNC/yerel yollar.
     * Apache/PHP’nin bu paylaşıma okuma yetkisi olmalıdır.
     */
    'training_video_path_prefixes' => [
        '\\\\sobek\\ortak\\',
    ],
];
