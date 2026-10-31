<?php

declare(strict_types=1);

/** Giá trị mặc định nếu bảng settings chưa có khóa tương ứng. */
function setting_defaults(): array
{
    return [
        'salon_name'            => 'Kitty Hair',
        'salon_slogan'          => 'Salon tóc nữ – Đẹp từ mái tóc',
        'salon_address'         => '',
        'salon_phone'           => '',
        'salon_email'           => '',
        'open_time'             => '08:30',
        'close_time'            => '21:00',
        'slot_step'             => '30',
        'booking_days_ahead'    => '14',
        'min_notice_minutes'    => '60',
        'facebook_url'          => '',
        'instagram_url'         => '',
        'tiktok_url'            => '',
    ];
}

/** Đọc toàn bộ cài đặt (cache trong 1 request). */
function settings_all(bool $refresh = false): array
{
    static $cache = null;
    if ($cache === null || $refresh) {
        $cache = setting_defaults();
        foreach (db_all('SELECT setting_key, setting_value FROM settings') as $row) {
            $cache[$row['setting_key']] = $row['setting_value'];
        }
    }
    return $cache;
}

function setting(string $key): string
{
    return settings_all()[$key] ?? '';
}

function setting_save(string $key, string $value): void
{
    db_query(
        'INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)
         ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)',
        [$key, $value]
    );
}
