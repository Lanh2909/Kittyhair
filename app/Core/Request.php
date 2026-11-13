<?php

declare(strict_types=1);

namespace App\Core;

use App\Helpers\IpAddress;

/**
 * Đóng gói dữ liệu yêu cầu HTTP ($_GET, $_POST, $_FILES, $_SERVER) – thay cho is_post(), post(), get(), client_ip().
 * Controller chỉ đọc dữ liệu qua đối tượng này, không đụng trực tiếp siêu biến toàn cục.
 */
final class Request
{
    public function isPost(): bool
    {
        return ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST';
    }

    /** Giá trị POST dạng chuỗi đã trim (không phải chuỗi -> $default). */
    public function post(string $key, string $default = ''): string
    {
        return isset($_POST[$key]) && is_string($_POST[$key]) ? trim($_POST[$key]) : $default;
    }

    /** Giá trị GET dạng chuỗi đã trim. */
    public function get(string $key, string $default = ''): string
    {
        return isset($_GET[$key]) && is_string($_GET[$key]) ? trim($_GET[$key]) : $default;
    }

    /** Giá trị POST giữ nguyên, không trim (dùng cho mật khẩu). */
    public function raw(string $key): string
    {
        return (string) ($_POST[$key] ?? '');
    }

    /** Giá trị POST dạng mảng (vd: services[], days[1][from]). */
    public function postArray(string $key): array
    {
        return (array) ($_POST[$key] ?? []);
    }

    /** Giá trị GET dạng mảng; không phải mảng -> []. */
    public function getArray(string $key): array
    {
        return isset($_GET[$key]) && is_array($_GET[$key]) ? $_GET[$key] : [];
    }

    /** Ghi đè 1 tham số GET (dùng khi hiển thị lại form sau lỗi POST). */
    public function setQuery(string $key, string $value): void
    {
        $_GET[$key] = $value;
    }

    public function queryString(): string
    {
        return (string) ($_SERVER['QUERY_STRING'] ?? '');
    }

    public function uri(): string
    {
        return (string) ($_SERVER['REQUEST_URI'] ?? '');
    }

    /** Tên file PHP đang chạy, vd "profile.php". */
    public function scriptBasename(): string
    {
        return basename((string) ($_SERVER['SCRIPT_NAME'] ?? ''));
    }

    /** IP người dùng đã chuẩn hóa (không tin header proxy như X-Forwarded-For). */
    public function ip(): string
    {
        return IpAddress::normalize((string) ($_SERVER['REMOTE_ADDR'] ?? ''));
    }

    /** Có tệp được chọn trong ô input file hay không. */
    public function hasFile(string $field): bool
    {
        return isset($_FILES[$field]) && ($_FILES[$field]['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
    }

    public function file(string $field): ?array
    {
        return $_FILES[$field] ?? null;
    }
}
