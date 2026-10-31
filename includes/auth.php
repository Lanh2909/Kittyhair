<?php

declare(strict_types=1);

/* =========================== Giới hạn tần suất ===========================
 * Dùng chung cho: đăng nhập admin sai, tra cứu lịch hẹn sai, đặt lịch dồn dập.
 * Mỗi lần "hit" ghi 1 dòng vào bảng rate_limits (theo khóa + IP).
 * ======================================================================== */

function rate_limit_exceeded(string $key, int $max, int $minutes): bool
{
    $count = (int) db_value(
        'SELECT COUNT(*) FROM rate_limits
         WHERE identifier = ? AND ip_address = ? AND attempted_at > (NOW() - INTERVAL ' . $minutes . ' MINUTE)',
        [mb_strtolower($key), client_ip()]
    );
    return $count >= $max;
}

function rate_limit_hit(string $key): void
{
    db_query('INSERT INTO rate_limits (identifier, ip_address) VALUES (?, ?)', [mb_strtolower($key), client_ip()]);
}

function rate_limit_clear(string $key): void
{
    db_query('DELETE FROM rate_limits WHERE identifier = ? AND ip_address = ?', [mb_strtolower($key), client_ip()]);
    db_query('DELETE FROM rate_limits WHERE attempted_at < (NOW() - INTERVAL 1 DAY)');
}

/* =============================== Quản trị =============================== */

/** Chuỗi ngẫu nhiên 32 ký tự hex dùng làm security stamp. */
function new_security_stamp(): string
{
    return bin2hex(random_bytes(16));
}

/**
 * Quản trị viên đang đăng nhập; null nếu chưa đăng nhập, bị xóa, bị khóa
 * hoặc phiên đã bị vô hiệu (mật khẩu/quyền đã đổi ở nơi khác -> security_stamp khác).
 */
function admin_current(): ?array
{
    static $admin = false;
    if (empty($_SESSION['admin_id'])) {
        return null;
    }
    if ($admin === false || $admin['admin_id'] !== (int) $_SESSION['admin_id']) {
        $row = db_row('SELECT * FROM admins WHERE admin_id = ? AND is_active = 1', [(int) $_SESSION['admin_id']]);
        if ($row === null || !hash_equals((string) $row['security_stamp'], (string) ($_SESSION['admin_stamp'] ?? ''))) {
            unset($_SESSION['admin_id'], $_SESSION['admin_username'], $_SESSION['admin_stamp']);
            return null;
        }
        $row['admin_id'] = (int) $row['admin_id'];
        $admin = $row;
    }
    return $admin;
}

/**
 * Bắt buộc đăng nhập admin. Tài khoản bị yêu cầu đổi mật khẩu chỉ được vào trang Hồ sơ cá nhân
 * cho tới khi đổi xong.
 */
function require_admin(): array
{
    $admin = admin_current();
    if ($admin === null) {
        redirect('admin/login.php');
    }
    if ((int) $admin['must_change_password'] === 1 && basename((string) $_SERVER['SCRIPT_NAME']) !== 'profile.php') {
        flash('info', 'Vì lý do bảo mật, bạn cần đổi mật khẩu trước khi tiếp tục sử dụng hệ thống.');
        redirect('admin/profile.php');
    }
    return $admin;
}

/** Đăng nhập admin. Trả về thông báo lỗi hoặc null nếu thành công. */
function admin_login(string $username, string $password): ?string
{
    $key = 'admin:' . $username;
    if (rate_limit_exceeded($key, LOGIN_MAX_ATTEMPTS, LOGIN_LOCK_MINUTES)) {
        return 'Bạn đã nhập sai quá nhiều lần. Vui lòng thử lại sau ' . LOGIN_LOCK_MINUTES . ' phút.';
    }
    $admin = db_row('SELECT * FROM admins WHERE username = ?', [mb_strtolower($username)]);
    if ($admin === null || !password_verify($password, $admin['password_hash'])) {
        rate_limit_hit($key);
        return 'Tên đăng nhập hoặc mật khẩu không đúng.';
    }
    if ((int) $admin['is_active'] !== 1) {
        return 'Tài khoản này đã bị khóa. Vui lòng liên hệ quản trị viên.';
    }
    rate_limit_clear($key);
    session_regenerate_id(true);
    $_SESSION['admin_id']       = (int) $admin['admin_id'];
    $_SESSION['admin_username'] = $admin['username'];
    $_SESSION['admin_stamp']    = $admin['security_stamp'];
    db_query('UPDATE admins SET last_login_at = NOW() WHERE admin_id = ?', [$admin['admin_id']]);
    log_activity('login', 'Quản trị viên đăng nhập.');
    return null;
}

function admin_logout(): void
{
    log_activity('logout', 'Quản trị viên đăng xuất.');
    unset($_SESSION['admin_id'], $_SESSION['admin_username'], $_SESSION['admin_stamp']);
    session_regenerate_id(true);
}
