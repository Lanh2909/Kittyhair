<?php

declare(strict_types=1);

namespace App\Helpers;

/**
 * Escape HTML: mọi dữ liệu xuất ra trang đều phải qua đây (hoặc hàm tắt e()) để chống XSS.
 * Vd: tên khách "<script>alert(1)</script>" sẽ hiển thị đúng chữ, không bị trình duyệt chạy như mã.
 */
final class Html
{
    /** @param mixed $value */
    public static function e($value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
