<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Flash;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Models\AdminModel;

/**
 * Xác thực tài khoản quản trị: đăng nhập (chống dò mật khẩu), đăng xuất, lấy người đang đăng nhập,
 * bắt buộc đăng nhập, đổi mật khẩu.
 *
 * Security stamp: mỗi tài khoản có 1 chuỗi ngẫu nhiên lưu ở CSDL, được chép vào session khi đăng nhập.
 * Khi đổi mật khẩu / đổi quyền / khóa tài khoản, stamp trong CSDL đổi -> các phiên cũ mang stamp cũ
 * không còn khớp -> current() trả về null -> phiên cũ bị đăng xuất ngay ở request kế tiếp.
 */
final class AuthService
{
    private AdminModel $admins;
    private RateLimiter $rateLimiter;
    private ActivityLogger $logger;
    private Request $request;

    /** @var array|false|null false = chưa tải trong request này */
    private $current = false;

    public function __construct(AdminModel $admins, RateLimiter $rateLimiter, ActivityLogger $logger, Request $request)
    {
        $this->admins      = $admins;
        $this->rateLimiter = $rateLimiter;
        $this->logger      = $logger;
        $this->request     = $request;
    }

    /** Chuỗi ngẫu nhiên 32 ký tự hex dùng làm security stamp. */
    public static function newSecurityStamp(): string
    {
        return bin2hex(random_bytes(16));
    }

    /** Băm mật khẩu bằng bcrypt (PASSWORD_DEFAULT): có salt ngẫu nhiên, chậm có chủ đích để chống dò. */
    public static function hashPassword(string $password): string
    {
        return password_hash($password, PASSWORD_DEFAULT);
    }

    /**
     * Quản trị viên đang đăng nhập; null nếu chưa đăng nhập, bị xóa, bị khóa
     * hoặc phiên đã bị vô hiệu (security_stamp trong CSDL khác stamp trong session).
     */
    public function current(): ?array
    {
        if (empty($_SESSION['admin_id'])) {
            return null;
        }
        if ($this->current === false || $this->current['admin_id'] !== (int) $_SESSION['admin_id']) {
            $row = $this->admins->findActive((int) $_SESSION['admin_id']);
            if ($row === null || !hash_equals((string) $row['security_stamp'], (string) ($_SESSION['admin_stamp'] ?? ''))) {
                $this->forgetSession();
                return null;
            }
            $row['admin_id'] = (int) $row['admin_id'];
            $this->current   = $row;
        }
        return $this->current;
    }

    /**
     * Bắt buộc đăng nhập (chưa đăng nhập -> chuyển tới trang login).
     * Tài khoản bị yêu cầu đổi mật khẩu chỉ được vào trang Hồ sơ cá nhân cho tới khi đổi xong.
     */
    public function requireAdmin(): array
    {
        $admin = $this->current();
        if ($admin === null) {
            Response::redirect('admin/login.php');
        }
        if ((int) $admin['must_change_password'] === 1 && $this->request->scriptBasename() !== 'profile.php') {
            Flash::add('info', 'Vì lý do bảo mật, bạn cần đổi mật khẩu trước khi tiếp tục sử dụng hệ thống.');
            Response::redirect('admin/profile.php');
        }
        return $admin;
    }

    /** Đăng nhập. Trả về thông báo lỗi hoặc null nếu thành công. */
    public function login(string $username, string $password): ?string
    {
        $key = 'admin:' . $username;
        if ($this->rateLimiter->exceeded($key, LOGIN_MAX_ATTEMPTS, LOGIN_LOCK_MINUTES)) {
            return 'Bạn đã nhập sai quá nhiều lần. Vui lòng thử lại sau ' . LOGIN_LOCK_MINUTES . ' phút.';
        }
        $admin = $this->admins->findByUsername(mb_strtolower($username));
        if ($admin === null || !password_verify($password, $admin['password_hash'])) {
            $this->rateLimiter->hit($key);
            return 'Tên đăng nhập hoặc mật khẩu không đúng.';
        }
        if ((int) $admin['is_active'] !== 1) {
            return 'Tài khoản này đã bị khóa. Vui lòng liên hệ quản trị viên.';
        }
        $this->rateLimiter->clear($key);
        Session::regenerate();
        Session::set('admin_id', (int) $admin['admin_id']);
        Session::set('admin_username', $admin['username']);
        Session::set('admin_stamp', $admin['security_stamp']);
        $this->admins->touchLastLogin((int) $admin['admin_id']);
        $this->logger->log('login', 'Quản trị viên đăng nhập.');
        return null;
    }

    public function logout(): void
    {
        $this->logger->log('logout', 'Quản trị viên đăng xuất.');
        $this->forgetSession();
        Session::regenerate();
    }

    /**
     * Tự đổi mật khẩu: lưu mật khẩu mới + stamp mới (đăng xuất mọi phiên KHÁC),
     * cập nhật stamp trong session hiện tại để phiên này vẫn đăng nhập.
     */
    public function changeOwnPassword(array $admin, string $newPassword): void
    {
        $stamp = self::newSecurityStamp();
        $this->admins->updatePassword((int) $admin['admin_id'], self::hashPassword($newPassword), $stamp, false);
        Session::set('admin_stamp', $stamp);
        Session::regenerate();
    }

    private function forgetSession(): void
    {
        Session::remove('admin_id', 'admin_username', 'admin_stamp');
    }
}
