<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Chống giả mạo yêu cầu (CSRF): mỗi session có 1 token ngẫu nhiên, mọi form POST phải gửi kèm token này.

 */
final class Csrf
{
    public static function token(): string
    {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    /** Ô input ẩn chứa token, chèn vào mọi form POST. */
    public static function field(): string
    {
        return '<input type="hidden" name="csrf_token" value="' . e(self::token()) . '">';
    }

    /** Dừng xử lý (HTTP 419) nếu token POST không hợp lệ. hash_equals so sánh thời gian hằng, chống dò từng ký tự. */
    public static function verify(): void
    {
        $token = App::request()->post('csrf_token');
        if ($token === '' || !hash_equals(self::token(), $token)) {
            http_response_code(419);
            exit('Phiên làm việc đã hết hạn. Vui lòng tải lại trang và thử lại.');
        }
    }
}
