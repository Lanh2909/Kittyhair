<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Phân quyền theo vai trò.
 *  - admin (Quản trị viên): toàn quyền.
 *  - staff (Lễ tân): chỉ xử lý nghiệp vụ hằng ngày – lịch hẹn, khách hàng, tin nhắn, hồ sơ cá nhân.
 * Mỗi Controller quản trị khai báo khóa quyền của mình; AdminController kiểm tra và trả 403 nếu không đủ quyền.
 */
final class Permission
{
    public const ROLES = ['admin' => 'Quản trị viên', 'staff' => 'Lễ tân'];

    /** Khóa quyền => các vai trò được phép. */
    public const MAP = [
        'dashboard'    => ['admin', 'staff'],
        'appointments' => ['admin', 'staff'],
        'clients'      => ['admin', 'staff'],
        'messages'     => ['admin', 'staff'],
        'catalog'      => ['admin'],   // Danh mục + Dịch vụ
        'employees'    => ['admin'],   // Nhân viên + giờ làm việc
        'accounts'     => ['admin'],   // Tài khoản quản trị
        'settings'     => ['admin'],
        'activity'     => ['admin'],   // Nhật ký hoạt động
    ];

    private AuthService $auth;

    public function __construct(AuthService $auth)
    {
        $this->auth = $auth;
    }

    /** Tài khoản $admin (mặc định: người đang đăng nhập) có quyền $permission không. */
    public function can(string $permission, ?array $admin = null): bool
    {
        $admin = $admin ?? $this->auth->current();
        if ($admin === null) {
            return false;
        }
        return in_array($admin['role'], self::MAP[$permission] ?? [], true);
    }

    public static function roleLabel(string $role): string
    {
        return self::ROLES[$role] ?? $role;
    }

    public static function roleBadge(string $role): string
    {
        $class = $role === 'admin' ? 'badge-primary' : 'badge-kitty';
        return '<span class="badge ' . $class . '">' . e(self::roleLabel($role)) . '</span>';
    }
}
