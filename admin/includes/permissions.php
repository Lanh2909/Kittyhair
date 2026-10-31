<?php

declare(strict_types=1);

/**
 * Phân quyền quản trị theo vai trò.
 *  - admin (Quản trị viên): toàn quyền.
 *  - staff (Lễ tân): chỉ xử lý nghiệp vụ hằng ngày – lịch hẹn, khách hàng, tin nhắn, hồ sơ cá nhân.
 * Mỗi trang gọi require_permission('<khóa>') ngay sau khi nạp includes/init.php.
 */

const ADMIN_ROLES = ['admin' => 'Quản trị viên', 'staff' => 'Lễ tân'];

/** Khóa quyền => các vai trò được phép. */
function admin_permission_map(): array
{
    return [
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
}

function admin_can(string $permission, ?array $admin = null): bool
{
    $admin = $admin ?? admin_current();
    if ($admin === null) {
        return false;
    }
    return in_array($admin['role'], admin_permission_map()[$permission] ?? [], true);
}

function admin_role_label(string $role): string
{
    return ADMIN_ROLES[$role] ?? $role;
}

function admin_role_badge(string $role): string
{
    $class = $role === 'admin' ? 'badge-primary' : 'badge-kitty';
    return '<span class="badge ' . $class . '">' . e(admin_role_label($role)) . '</span>';
}

/** Chặn truy cập (HTTP 403) nếu vai trò hiện tại không có quyền; ghi vào nhật ký. */
function require_permission(string $permission): void
{
    if (admin_can($permission)) {
        return;
    }
    log_activity('access_denied', 'Bị từ chối truy cập chức năng "' . $permission . '" (' . ($_SERVER['REQUEST_URI'] ?? '') . ').');
    http_response_code(403);
    $pageTitle  = 'Không có quyền truy cập';
    $activeMenu = '';
    require __DIR__ . '/../templates/header.php';
    echo '<div class="text-center py-5"><div class="display-4 text-gray-400 mb-3"><i class="fas fa-lock"></i></div>'
        . '<h1 class="h3 text-gray-800">Bạn không có quyền truy cập chức năng này</h1>'
        . '<p class="text-muted">Tài khoản vai trò <strong>' . e(admin_role_label((string) admin_current()['role'])) . '</strong> không được sử dụng mục này. Vui lòng liên hệ Quản trị viên.</p>'
        . '<a class="btn btn-primary" href="' . e(url('admin/index.php')) . '">Về bảng điều khiển</a></div>';
    require __DIR__ . '/../templates/footer.php';
    exit;
}
