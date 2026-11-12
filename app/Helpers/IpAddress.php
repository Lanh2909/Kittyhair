<?php

declare(strict_types=1);

namespace App\Helpers;

/**
 * Chuẩn hóa và hiển thị địa chỉ IP (dùng cho nhật ký hoạt động và giới hạn tần suất).
 */
final class IpAddress
{
    /**
     * ::1 -> 127.0.0.1, ::ffff:1.2.3.4 -> 1.2.3.4, IPv6 rút gọn chuẩn.
     * Trả về chuỗi rỗng nếu không phải IP hợp lệ.
     */
    public static function normalize(string $ip): string
    {
        $ip = trim($ip);
        if (filter_var($ip, FILTER_VALIDATE_IP) === false) {
            return '';
        }
        $packed = inet_pton($ip);
        if ($packed === false) {
            return '';
        }
        if ($packed === inet_pton('::1')) {
            return '127.0.0.1';
        }
        // IPv4 nhúng trong IPv6 (::ffff:a.b.c.d)
        if (strlen($packed) === 16 && substr($packed, 0, 12) === str_repeat("\0", 10) . "\xff\xff") {
            return (string) inet_ntop(substr($packed, 12));
        }
        return (string) inet_ntop($packed);
    }

    /** Nhãn thân thiện cho IP nội bộ: "Máy cục bộ (localhost)" / "Mạng nội bộ" / null nếu là IP công cộng. */
    public static function label(string $ip): ?string
    {
        $ip = self::normalize($ip);
        if ($ip === '') {
            return null;
        }
        if (strpos($ip, '127.') === 0) {
            return 'Máy cục bộ (localhost)';
        }
        $isPublic = filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false;
        return $isPublic ? null : 'Mạng nội bộ';
    }

    /** HTML hiển thị IP: "127.0.0.1 (Máy cục bộ (localhost))"; rỗng -> "—". */
    public static function html(string $ip): string
    {
        $normalized = self::normalize($ip);
        if ($normalized === '') {
            return $ip === '' ? '<span class="text-muted">—</span>' : e($ip);
        }
        $label = self::label($normalized);
        return '<span class="text-nowrap">' . e($normalized) . '</span>' . ($label ? '<br><small class="text-muted">' . e($label) . '</small>' : '');
    }
}
