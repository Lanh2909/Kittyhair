<?php

declare(strict_types=1);

namespace App\Models;

/**
 * Bảng rate_limits: mỗi lần "vi phạm" (đăng nhập sai, đặt lịch) ghi 1 dòng theo khóa + IP.
 */
final class RateLimitModel extends Model
{
    /** Số lần trong $minutes phút gần nhất. */
    public function countRecent(string $identifier, string $ip, int $minutes): int
    {
        return (int) $this->db->value(
            'SELECT COUNT(*) FROM rate_limits
             WHERE identifier = ? AND ip_address = ? AND attempted_at > (NOW() - INTERVAL ' . $minutes . ' MINUTE)',
            [$identifier, $ip]
        );
    }

    public function add(string $identifier, string $ip): void
    {
        $this->db->query('INSERT INTO rate_limits (identifier, ip_address) VALUES (?, ?)', [$identifier, $ip]);
    }

    public function clear(string $identifier, string $ip): void
    {
        $this->db->query('DELETE FROM rate_limits WHERE identifier = ? AND ip_address = ?', [$identifier, $ip]);
    }

    /** Dọn bản ghi cũ hơn 1 ngày cho bảng khỏi phình to. */
    public function purgeOld(): void
    {
        $this->db->query('DELETE FROM rate_limits WHERE attempted_at < (NOW() - INTERVAL 1 DAY)');
    }
}
