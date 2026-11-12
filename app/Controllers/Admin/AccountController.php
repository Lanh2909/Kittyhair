<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\App;
use App\Core\Paginator;
use App\Core\QueryFilter;
use App\Models\ActivityLogModel;
use App\Models\AdminModel;
use App\Services\Permission;

/**
 * Tài khoản quản trị (admin/accounts.php – chỉ Quản trị viên): danh sách + lọc, xem, thêm, sửa,
 * đặt lại mật khẩu, khóa/mở khóa, xóa. Quy tắc nghiệp vụ nằm trong AccountService.
 *   ?action=view&id= | ?action=create | ?action=edit&id= | (không có) danh sách
 */
final class AccountController extends AdminController
{
    protected ?string $permission = 'accounts';

    private const STATUS_FILTERS = ['all' => 'Tất cả', 'active' => 'Đang hoạt động', 'locked' => 'Đã khóa'];

    private AdminModel $admins;

    public function __construct()
    {
        parent::__construct();
        $this->admins = new AdminModel(App::db());
    }

    public function handle(): void
    {
        $action   = $this->request->get('action');
        $errors   = [];
        $formData = null;

        if ($this->request->isPost()) {
            $this->verifyCsrf();
            [$action, $errors, $formData] = $this->handlePost($action);
        }

        if ($action === 'view') {
            $this->show();
        } elseif ($action === 'create' || $action === 'edit') {
            $this->form($action, $errors, $formData);
        } else {
            $this->index();
        }
    }

    /**
     * Các thao tác POST. Thao tác thành công/thất bại đều chuyển hướng, trừ lưu form có lỗi.
     * @return array{0:string,1:array,2:?array} [action hiển thị tiếp, lỗi, dữ liệu form]
     */
    private function handlePost(string $action): array
    {
        $accounts   = App::accounts();
        $postAction = $this->request->post('action');
        $id         = (int) $this->request->post('admin_id');
        $target     = $accounts->find($id);
        $backToList = $this->withQuery('admin/accounts.php');

        if ($postAction === 'toggle_active') {
            $error = $accounts->toggleActive($target, $this->admin);
            if ($error !== null) {
                $this->flash('danger', $error);
            } else {
                $unlocked = (int) $target['is_active'] !== 1;
                $this->flash('success', ($unlocked ? 'Đã mở khóa' : 'Đã khóa') . ' tài khoản ' . $target['username'] . '.');
            }
            $this->redirect($backToList);
        }

        if ($postAction === 'delete') {
            $error = $accounts->delete($target, $this->admin);
            $error !== null ? $this->flash('danger', $error) : $this->flash('success', 'Đã xóa tài khoản ' . $target['username'] . '.');
            $this->redirect('admin/accounts.php');
        }

        if ($postAction === 'reset_password') {
            $error = $accounts->resetPassword($target, $this->admin, $this->request->raw('new_password'), $this->request->raw('confirm_password'));
            $error !== null
                ? $this->flash('danger', $error)
                : $this->flash('success', 'Đã đặt lại mật khẩu cho ' . $target['username'] . '. Người dùng sẽ bị yêu cầu đổi mật khẩu ở lần đăng nhập tới và mọi phiên đăng nhập cũ đã bị đăng xuất.');
            $this->redirect($backToList);
        }

        if ($postAction === 'save') {
            if ($id > 0 && $target === null) {
                $this->flash('danger', 'Không tìm thấy tài khoản.');
                $this->redirect('admin/accounts.php');
            }
            $data = [
                'username'  => mb_strtolower($this->request->post('username')),
                'full_name' => $this->request->post('full_name'),
                'email'     => mb_strtolower($this->request->post('email')),
                'role'      => $this->request->post('role'),
            ];
            $result = $accounts->save($target, $this->admin, $data, $this->request->raw('password'), $this->request->raw('confirm_password'));
            if (!$result['errors']) {
                $this->flash('success', (string) $result['message']);
                $this->redirect('admin/accounts.php');
            }
            $this->request->setQuery('id', (string) $id);
            return [$target ? 'edit' : 'create', $result['errors'], $result['data']];
        }

        return [$action, [], null];
    }

    /** Trang xem thông tin 1 tài khoản kèm 10 hoạt động gần nhất. */
    private function show(): void
    {
        $viewing = $this->admins->find((int) $this->request->get('id', '0'));
        if ($viewing === null) {
            $this->flash('danger', 'Không tìm thấy tài khoản.');
            $this->redirect('admin/accounts.php');
        }
        $logs = new ActivityLogModel(App::db());

        $this->renderAdmin('admin/accounts-view', 'Thông tin tài khoản', 'accounts', [
            'viewing'  => $viewing,
            'isSelf'   => (int) $viewing['admin_id'] === $this->admin['admin_id'],
            'actCount' => $logs->countByAdmin((int) $viewing['admin_id']),
            'recent'   => $logs->recentByAdmin((int) $viewing['admin_id'], 10),
        ]);
    }

    private function form(string $action, array $errors, ?array $formData): void
    {
        $editing = $action === 'edit' ? $this->admins->find((int) $this->request->get('id', '0')) : null;
        if ($action === 'edit' && $editing === null) {
            $this->flash('danger', 'Không tìm thấy tài khoản.');
            $this->redirect('admin/accounts.php');
        }
        $pageTitle = $editing ? 'Sửa tài khoản quản trị' : 'Thêm tài khoản quản trị';

        $this->renderAdmin('admin/accounts-form', $pageTitle, 'accounts', [
            'editing' => $editing,
            'form'    => $formData ?? $editing ?? ['username' => '', 'full_name' => '', 'email' => '', 'role' => 'staff'],
            'errors'  => $errors,
            'isSelf'  => $editing !== null && (int) $editing['admin_id'] === $this->admin['admin_id'],
        ]);
    }

    private function index(): void
    {
        $status  = array_key_exists($this->request->get('status'), self::STATUS_FILTERS) ? $this->request->get('status') : 'all';
        $role    = array_key_exists($this->request->get('role'), Permission::ROLES) ? $this->request->get('role') : '';
        $keyword = $this->keyword();

        $filter = new QueryFilter();
        if ($status === 'active') {
            $filter->where('is_active = 1');
        } elseif ($status === 'locked') {
            $filter->where('is_active = 0');
        }
        if ($role !== '') {
            $filter->where('role = ?', [$role]);
        }
        $filter->whereLikeAny(['username', 'full_name', 'email'], $keyword);
        $pager = new Paginator($this->admins->count($filter), PER_PAGE_ADMIN);

        $this->renderAdmin('admin/accounts-list', 'Tài khoản quản trị', 'accounts', [
            'admin'         => $this->admin,
            'rows'          => $this->admins->page($filter, $pager),
            'statusFilters' => self::STATUS_FILTERS,
            'status'        => $status,
            'role'          => $role,
            'keyword'       => $keyword,
            'pager'         => $pager,
            'keep'          => ['status' => $status === 'all' ? '' : $status, 'role' => $role, 'q' => $keyword],
        ]);
    }
}
