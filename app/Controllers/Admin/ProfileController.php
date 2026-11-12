<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\App;
use App\Models\AdminModel;
use App\Services\Validator;

/**
 * Hồ sơ cá nhân (admin/profile.php): đổi họ tên/email, ảnh đại diện, mật khẩu.
 * Không khai báo $permission: mọi vai trò đều vào được, kể cả tài khoản đang bị buộc đổi mật khẩu.
 */
final class ProfileController extends AdminController
{
    private AdminModel $admins;

    public function __construct()
    {
        parent::__construct();
        $this->admins = new AdminModel(App::db());
    }

    public function handle(): void
    {
        $errors = [];
        $old    = ['full_name' => $this->admin['full_name'], 'email' => $this->admin['email']];

        if ($this->request->isPost()) {
            $this->verifyCsrf();
            $action = $this->request->post('action');

            if ($action === 'info') {
                $old    = ['full_name' => $this->request->post('full_name'), 'email' => mb_strtolower($this->request->post('email'))];
                $errors = $this->updateInfo($old);
            } elseif ($action === 'avatar') {
                $this->uploadAvatar();
            } elseif ($action === 'avatar_delete') {
                $this->deleteAvatar();
            } elseif ($action === 'password') {
                $errors = $this->changePassword();
            }
        }

        $this->renderAdmin('admin/profile', 'Hồ sơ cá nhân', '', [
            'admin'     => $this->admin,
            'old'       => $old,
            'errors'    => $errors,
            'avatarUrl' => App::uploads()->url('avatars', $this->admin['avatar']),
        ]);
    }

    private function updateInfo(array $old): array
    {
        $errors = [];
        if (!Validator::isValidFullName($old['full_name'])) {
            $errors['full_name'] = 'Họ tên không hợp lệ (2–100 ký tự, chỉ gồm chữ cái).';
        }
        if (!Validator::isValidEmail($old['email'])) {
            $errors['email'] = 'Email không hợp lệ.';
        } elseif ($this->admins->emailTaken($old['email'], $this->admin['admin_id'])) {
            $errors['email'] = 'Email đã được sử dụng.';
        }
        if (!$errors) {
            $this->admins->updateProfile($this->admin['admin_id'], $old['full_name'], $old['email']);
            App::logger()->log('profile_update', 'Cập nhật hồ sơ quản trị viên.');
            $this->flash('success', 'Đã cập nhật hồ sơ.');
            $this->redirect('admin/profile.php');
        }
        return $errors;
    }

    private function uploadAvatar(): void
    {
        $uploads = App::uploads();
        $upload  = $uploads->saveImage('avatar', 'avatars', UPLOAD_MAX_AVATAR);
        if ($upload['error'] !== null || $upload['file'] === null) {
            $this->flash('danger', $upload['error'] ?? 'Vui lòng chọn một ảnh.');
        } else {
            $uploads->delete('avatars', $this->admin['avatar']);
            $this->admins->updateAvatar($this->admin['admin_id'], $upload['file']);
            App::logger()->log('file_upload', 'Cập nhật ảnh đại diện quản trị viên.');
            $this->flash('success', 'Đã cập nhật ảnh đại diện.');
        }
        $this->redirect('admin/profile.php');
    }

    private function deleteAvatar(): void
    {
        if ($this->admin['avatar']) {
            App::uploads()->delete('avatars', $this->admin['avatar']);
            $this->admins->updateAvatar($this->admin['admin_id'], null);
            App::logger()->log('file_delete', 'Xóa ảnh đại diện quản trị viên.');
            $this->flash('success', 'Đã xóa ảnh đại diện.');
        }
        $this->redirect('admin/profile.php');
    }

    /** Đổi mật khẩu: phải nhập đúng mật khẩu hiện tại, mật khẩu mới hợp lệ và khác mật khẩu cũ. */
    private function changePassword(): array
    {
        $current = $this->request->raw('current_password');
        $new     = $this->request->raw('new_password');
        $confirm = $this->request->raw('confirm_password');

        if (!password_verify($current, $this->admin['password_hash'])) {
            return ['current_password' => 'Mật khẩu hiện tại không đúng.'];
        }
        if (($pwError = Validator::passwordError($new, $this->admin['username'])) !== null) {
            return ['new_password' => $pwError];
        }
        if (password_verify($new, $this->admin['password_hash'])) {
            return ['new_password' => 'Mật khẩu mới phải khác mật khẩu hiện tại.'];
        }
        if ($new !== $confirm) {
            return ['confirm_password' => 'Mật khẩu nhập lại không khớp.'];
        }

        // Đổi security_stamp -> đăng xuất mọi phiên khác đang dùng mật khẩu cũ; phiên hiện tại được giữ lại
        App::auth()->changeOwnPassword($this->admin, $new);
        App::logger()->log('password_change', 'Đổi mật khẩu quản trị viên.');
        $this->flash('success', 'Đã đổi mật khẩu thành công.');
        $this->redirect('admin/profile.php');
        return [];
    }
}
