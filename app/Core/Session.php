<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Bao bọc $_SESSION: khởi động session an toàn (cookie HttpOnly + SameSite), đọc/ghi/xóa khóa, đổi session ID.
 */
final class Session
{
    public static function start(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax']);
            session_start();
        }
    }

    /** @return mixed */
    public static function get(string $key, $default = null)
    {
        return $_SESSION[$key] ?? $default;
    }

    /** @param mixed $value */
    public static function set(string $key, $value): void
    {
        $_SESSION[$key] = $value;
    }

    public static function remove(string ...$keys): void
    {
        foreach ($keys as $key) {
            unset($_SESSION[$key]);
        }
    }

    /** Đổi session ID (chống session fixation) – gọi khi đăng nhập / đăng xuất / đổi mật khẩu. */
    public static function regenerate(): void
    {
        session_regenerate_id(true);
    }
}
