<?php

declare(strict_types=1);

namespace App\Models;

/**
 * Bảng settings (cặp khóa – giá trị).
 */
final class SettingModel extends Model
{
    /** @return array<string,string> [setting_key => setting_value] */
    public function pairs(): array
    {
        $pairs = [];
        foreach ($this->db->all('SELECT setting_key, setting_value FROM settings') as $row) {
            $pairs[$row['setting_key']] = $row['setting_value'];
        }
        return $pairs;
    }

    /** Thêm mới hoặc ghi đè (UPSERT) 1 khóa. */
    public function save(string $key, string $value): void
    {
        $this->db->query(
            'INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)
             ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)',
            [$key, $value]
        );
    }
}
