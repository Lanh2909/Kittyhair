<?php

declare(strict_types=1);

/** Escape HTML khi xuất ra trình duyệt. */
function e($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** URL gốc của site (tự nhận diện thư mục cài đặt, có thể ghi đè bằng KH_BASE_URL). */
function base_url(): string
{
    static $base = null;
    if ($base !== null) {
        return $base;
    }
    $env = getenv('KH_BASE_URL');
    if ($env !== false) {
        return $base = rtrim($env, '/');
    }
    $root = str_replace('\\', '/', (string) realpath(APP_ROOT));
    $doc  = str_replace('\\', '/', (string) realpath($_SERVER['DOCUMENT_ROOT'] ?? ''));
    if ($doc !== '' && strpos($root, $doc) === 0) {
        return $base = rtrim(substr($root, strlen($doc)), '/');
    }
    return $base = '';
}

/** Tạo URL tuyệt đối theo gốc site: url('booking.php'). */
function url(string $path = ''): string
{
    return base_url() . '/' . ltrim($path, '/');
}

function redirect(string $path): void
{
    // Đường dẫn bắt đầu bằng "/" hoặc http(s) được giữ nguyên; còn lại tính theo gốc site.
    $isAbsolute = $path !== '' && ($path[0] === '/' || preg_match('#^https?://#', $path) === 1);
    header('Location: ' . ($isAbsolute ? $path : url($path)));
    exit;
}

function is_post(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST';
}

/** Lấy giá trị POST đã trim. */
function post(string $key, string $default = ''): string
{
    return isset($_POST[$key]) && is_string($_POST[$key]) ? trim($_POST[$key]) : $default;
}

/** Lấy giá trị GET đã trim. */
function get(string $key, string $default = ''): string
{
    return isset($_GET[$key]) && is_string($_GET[$key]) ? trim($_GET[$key]) : $default;
}

/**
 * Chuẩn hóa địa chỉ IP: ::1 -> 127.0.0.1, ::ffff:1.2.3.4 -> 1.2.3.4, IPv6 rút gọn chuẩn.
 * Trả về chuỗi rỗng nếu không phải IP hợp lệ.
 */
function normalize_ip(string $ip): string
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

/** IP của người dùng đã chuẩn hóa (không tin các header proxy như X-Forwarded-For). */
function client_ip(): string
{
    return normalize_ip((string) ($_SERVER['REMOTE_ADDR'] ?? ''));
}

/** Nhãn thân thiện cho IP nội bộ: "Máy cục bộ (localhost)" / "Mạng nội bộ" / null nếu là IP công cộng. */
function ip_label(string $ip): ?string
{
    $ip = normalize_ip($ip);
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
function format_ip_html(string $ip): string
{
    $normalized = normalize_ip($ip);
    if ($normalized === '') {
        return $ip === '' ? '<span class="text-muted">—</span>' : e($ip);
    }
    $label = ip_label($normalized);
    return '<span class="text-nowrap">' . e($normalized) . '</span>' . ($label ? '<br><small class="text-muted">' . e($label) . '</small>' : '');
}

/* ------------------------------ Flash message ------------------------------ */

function flash(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function render_flash(): string
{
    $html = '';
    foreach ($_SESSION['flash'] ?? [] as $item) {
        $html .= '<div class="alert alert-' . e($item['type']) . ' alert-dismissible fade show" role="alert">'
            . e($item['message'])
            . '<button type="button" class="close" data-dismiss="alert" aria-label="Đóng"><span aria-hidden="true">&times;</span></button></div>';
    }
    unset($_SESSION['flash']);
    return $html;
}

/* ---------------------------------- CSRF ---------------------------------- */

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

/** Dừng xử lý nếu token POST không hợp lệ. */
function verify_csrf(): void
{
    $token = post('csrf_token');
    if ($token === '' || !hash_equals(csrf_token(), $token)) {
        http_response_code(419);
        exit('Phiên làm việc đã hết hạn. Vui lòng tải lại trang và thử lại.');
    }
}

/* ------------------------------- Định dạng -------------------------------- */

/** 1500000 -> "1.500.000đ" */
function format_money($amount): string
{
    return number_format((float) $amount, 0, ',', '.') . 'đ';
}

/** 0912345678 -> "0912 345 678" */
function format_phone(string $phone): string
{
    $digits = preg_replace('/\D/', '', $phone);
    return strlen($digits) === 10
        ? substr($digits, 0, 4) . ' ' . substr($digits, 4, 3) . ' ' . substr($digits, 7)
        : $phone;
}

function format_datetime(string $datetime): string
{
    return date('H:i d/m/Y', strtotime($datetime));
}

function format_date(string $datetime): string
{
    return date('d/m/Y', strtotime($datetime));
}

function format_duration(int $minutes): string
{
    if ($minutes < 60) {
        return $minutes . ' phút';
    }
    $h = intdiv($minutes, 60);
    $m = $minutes % 60;
    return $h . ' giờ' . ($m ? ' ' . $m . ' phút' : '');
}

/** Tên thứ trong tuần theo ISO (1 = Thứ Hai ... 7 = Chủ Nhật). */
function weekday_name(int $isoDay): string
{
    $names = [1 => 'Thứ Hai', 2 => 'Thứ Ba', 3 => 'Thứ Tư', 4 => 'Thứ Năm', 5 => 'Thứ Sáu', 6 => 'Thứ Bảy', 7 => 'Chủ Nhật'];
    return $names[$isoDay] ?? '';
}

function short_weekday_name(int $isoDay): string
{
    $names = [1 => 'T2', 2 => 'T3', 3 => 'T4', 4 => 'T5', 5 => 'T6', 6 => 'T7', 7 => 'CN'];
    return $names[$isoDay] ?? '';
}

/** Chữ cái đầu của tên để làm avatar mặc định. */
function initials(string $name): string
{
    $parts = preg_split('/\s+/u', trim($name));
    return mb_strtoupper(mb_substr((string) end($parts), 0, 1));
}

function json_response(array $data, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}
