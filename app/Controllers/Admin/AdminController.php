<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\Controller;
use App\Core\App;
use App\Models\ContactMessageModel;

/**
 * Lớp cha của mọi trang quản trị (thay cho admin/includes/init.php + permissions.php).
 *
 * Khi tạo đối tượng (constructor):
 *   1. Bắt buộc đăng nhập (AuthService::requireAdmin) -> $this->admin là người đang đăng nhập.
 *   2. Nếu lớp con khai báo $permission, kiểm tra quyền; không đủ quyền -> ghi nhật ký + trả HTTP 403.
 * Lớp con chỉ cần khai báo: protected ?string $permission = 'appointments';
 */
abstract class AdminController extends Controller
{
    /** Khóa quyền của trang (xem Permission::MAP); null = mọi tài khoản đăng nhập đều vào được. */
    protected ?string $permission = null;

    protected array $admin;

    public function __construct()
    {
        parent::__construct();
        $this->admin = App::auth()->requireAdmin();
        if ($this->permission !== null) {
            $this->authorize($this->permission);
        }
    }

    /** Chặn truy cập (HTTP 403) nếu vai trò hiện tại không có quyền. */
    protected function authorize(string $permission): void
    {
        if (App::permission()->can($permission)) {
            return;
        }
        App::logger()->log('access_denied', 'Bị từ chối truy cập chức năng "' . $permission . '" (' . $this->request->uri() . ').');
        http_response_code(403);
        $this->renderAdmin('admin/403', 'Không có quyền truy cập', '', ['currentAdmin' => App::auth()->current()]);
        exit;
    }

    /**
     * Hiển thị view trong khung quản trị (sidebar + topbar).
     * @param string $activeMenu khóa menu được tô sáng ở sidebar
     */
    protected function renderAdmin(string $template, string $pageTitle, string $activeMenu, array $data = []): void
    {
        $this->view->render($template, $data + [
            'pageTitle'      => $pageTitle,
            'activeMenu'     => $activeMenu,
            'currentAdmin'   => App::auth()->current(),
            'unreadMessages' => (new ContactMessageModel(App::db()))->countUnread(),
            'permission'     => App::permission(),
        ], 'admin/layout');
    }
}
