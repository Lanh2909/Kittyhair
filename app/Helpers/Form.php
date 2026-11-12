<?php

declare(strict_types=1);

namespace App\Helpers;

/**
 * Hỗ trợ hiển thị lỗi kiểm tra dữ liệu trên form (thay cho templates/form_helpers.php).
 */
final class Form
{
    /** Thông báo lỗi của 1 trường (nếu có). */
    public static function error(array $errors, string $key): string
    {
        return isset($errors[$key]) ? '<span class="form-text-error">' . e($errors[$key]) . '</span>' : '';
    }

    /** Class " is-invalid" (viền đỏ Bootstrap) khi trường có lỗi. */
    public static function invalidClass(array $errors, string $key): string
    {
        return isset($errors[$key]) ? ' is-invalid' : '';
    }
}
