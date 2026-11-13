<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\App;
use App\Models\SettingModel;

/**
 * Đọc / ghi cài đặt salon. Đọc CSDL 1 lần mỗi request rồi giữ trong bộ nhớ đệm ($cache).
 */
final class SettingService
{
    /** Giá trị mặc định nếu bảng settings chưa có khóa tương ứng. */
    public const DEFAULTS = [
        'salon_name'         => 'Kitty Hair',
        'salon_slogan'       => 'Salon tóc nữ – Đẹp từ mái tóc',
        'salon_address'      => '',
        'salon_phone'        => '',
        'salon_email'        => '',
        'open_time'          => '08:30',
        'close_time'         => '21:00',
        'slot_step'          => '30',
        'booking_days_ahead' => '14',
        'min_notice_minutes' => '60',
        'facebook_url'       => '',
        'instagram_url'      => '',
        'tiktok_url'         => '',
    ];

    private SettingModel $model;
    private ?array $cache = null;

    public function __construct(SettingModel $model)
    {
        $this->model = $model;
    }

    /** Toàn bộ cài đặt (mặc định + giá trị đã lưu). */
    public function all(bool $refresh = false): array
    {
        if ($this->cache === null || $refresh) {
            $this->cache = array_merge(self::DEFAULTS, $this->model->pairs());
        }
        return $this->cache;
    }

    public function get(string $key): string
    {
        return (string) ($this->all()[$key] ?? '');
    }

    /** Lưu nhiều khóa trong 1 transaction (hoặc lưu hết, hoặc không lưu gì). */
    public function saveMany(array $values): void
    {
        App::db()->transaction(function () use ($values): void {
            foreach ($values as $key => $value) {
                $this->model->save((string) $key, (string) $value);
            }
        });
        $this->cache = null;
    }
}
