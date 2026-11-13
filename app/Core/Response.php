<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Các kiểu phản hồi kết thúc request: chuyển hướng, JSON (thay cho redirect(), json_response()).
 */
final class Response
{
    /** Chuyển hướng rồi dừng. Đường dẫn bắt đầu bằng "/" hoặc http(s) ; còn lại tính theo gốc site. */
    public static function redirect(string $path): void
    {
        $isAbsolute = $path !== '' && ($path[0] === '/' || preg_match('#^https?://#', $path) === 1);
        header('Location: ' . ($isAbsolute ? $path : Url::to($path)));
        exit;
    }

    /** Trả JSON (UTF-8, giữ nguyên tiếng Việt) rồi dừng. */
    public static function json(array $data, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }
}
