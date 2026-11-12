<?php

declare(strict_types=1);

namespace App\Services;

use DateTime;

/**
 * Quy tắc kiểm tra dữ liệu dùng chung cho mọi form: SĐT Việt Nam, email, họ tên, mật khẩu, giờ, ngày, tên đăng nhập.
 */
final class Validator
{
    /** Gợi ý định dạng SĐT hiển thị dưới các ô nhập. */
    public const PHONE_FORMAT_HINT = 'Ví dụ: 0912 345 678, 0912345678, +84 912 345 678 hoặc (+84) 912345678.';

    /** Thông báo lỗi chuẩn khi SĐT không hợp lệ (dùng chung cho mọi form). */
    public static function phoneErrorMessage(): string
    {
        return 'Số điện thoại không hợp lệ. Chỉ nhận số di động Việt Nam (10 số, đầu số 03, 05, 07, 08, 09). ' . self::PHONE_FORMAT_HINT;
    }

    /**
     * SĐT di động Việt Nam hợp lệ (10 chữ số):
     *   [tiền tố] + [3|5|7|8|9] + 8 chữ số, có thể ngăn cách bằng 1 dấu cách, dấu chấm hoặc gạch ngang.
     *   Tiền tố chấp nhận: 0 | 84 | +84 | (84) | (+84)
     *   Hợp lệ: 0912345678, 0912 345 678, 0912.345.678, +84 912 345 678, (+84) 886786158
     *   Không hợp lệ: chứa chữ, đầu số 01/02/04/06, thiếu/thừa chữ số, ngoặc hoặc dấu + sai vị trí.
     * Trả về dạng chuẩn 0xxxxxxxxx để lưu CSDL, hoặc null nếu không hợp lệ.
     */
    public static function normalizePhone(string $input): ?string
    {
        $pattern = '/^(?:0|84|\+84|\(84\)|\(\+84\))[ .\-]?([35789])((?:[ .\-]?\d){8})$/';
        if (preg_match($pattern, trim($input), $m) !== 1) {
            return null;
        }
        return '0' . $m[1] . preg_replace('/\D/', '', $m[2]);
    }

    public static function isValidEmail(string $email): bool
    {
        return mb_strlen($email) <= 100 && filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }

    /** Họ tên: 2-100 ký tự, chỉ gồm chữ (có dấu), khoảng trắng, dấu ' . - */
    public static function isValidFullName(string $name): bool
    {
        $len = mb_strlen($name);
        return $len >= 2 && $len <= 100 && preg_match("/^[\p{L}][\p{L}\s'.\-]*$/u", $name) === 1;
    }

    /** Tên đăng nhập: 3–30 ký tự chữ thường, số, dấu chấm, gạch dưới. */
    public static function isValidUsername(string $username): bool
    {
        return preg_match('/^[a-z0-9_.]{3,30}$/', $username) === 1;
    }

    /** Thông báo lỗi mật khẩu hoặc null nếu hợp lệ (≥ 8 ký tự, có chữ và số, không chứa tên đăng nhập). */
    public static function passwordError(string $password, string $username = ''): ?string
    {
        if (mb_strlen($password) < PASSWORD_MIN_LENGTH) {
            return 'Mật khẩu phải có ít nhất ' . PASSWORD_MIN_LENGTH . ' ký tự.';
        }
        if (!preg_match('/[A-Za-z]/', $password) || !preg_match('/\d/', $password)) {
            return 'Mật khẩu phải gồm cả chữ và số.';
        }
        if (mb_strlen($username) >= 3 && mb_stripos($password, $username) !== false) {
            return 'Mật khẩu không được trùng hoặc chứa tên đăng nhập.';
        }
        return null;
    }

    /** Giờ dạng HH:MM (00:00 – 23:59). */
    public static function isValidTime(string $time): bool
    {
        return preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $time) === 1;
    }

    /** Ngày dạng Y-m-d có thật (loại 2026-02-30). */
    public static function isValidDate(string $date): bool
    {
        $d = DateTime::createFromFormat('Y-m-d', $date);
        return $d !== false && $d->format('Y-m-d') === $date;
    }
}
