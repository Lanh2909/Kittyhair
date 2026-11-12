<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\App;
use App\Models\AdminModel;

/**
 * Nghiệp vụ quản lý tài khoản quản trị: thêm, sửa (kèm vai trò), đặt lại mật khẩu, khóa/mở khóa, xóa.
 *
 * Quy tắc an toàn:
 *  - Không tự khóa / xóa / hạ quyền chính mình; luôn còn ít nhất 1 Quản trị viên đang hoạt động.
 *  - Tài khoản mới và tài khoản vừa được đặt lại mật khẩu bị buộc đổi mật khẩu ở lần đăng nhập kế tiếp.
 *  - Đặt lại mật khẩu / đổi vai trò / khóa làm đổi security_stamp -> mọi phiên đăng nhập cũ bị vô hiệu.
 * Các phương thức thao tác trả về thông báo lỗi (string) hoặc null nếu thành công.
 */
final class AccountService
{
    private AdminModel $admins;
    private RateLimiter $rateLimiter;
    private ActivityLogger $logger;

    public function __construct(AdminModel $admins, RateLimiter $rateLimiter, ActivityLogger $logger)
    {
        $this->admins      = $admins;
        $this->rateLimiter = $rateLimiter;
        $this->logger      = $logger;
    }

    public function find(int $adminId): ?array
    {
        return $adminId > 0 ? $this->admins->find($adminId) : null;
    }

    /**
     * Lý do KHÔNG được thực hiện thao tác nguy hiểm trên $target (null nếu được phép).
     * @param string $operation lock | delete | demote
     */
    public function guardError(array $target, array $actor, string $operation): ?string
    {
        $verb = ['lock' => 'khóa', 'delete' => 'xóa', 'demote' => 'hạ quyền'][$operation] ?? $operation;
        if ((int) $target['admin_id'] === $actor['admin_id']) {
            return "Bạn không thể tự $verb tài khoản của chính mình.";
        }
        if ($target['role'] === 'admin' && (int) $target['is_active'] === 1 && $this->admins->countOtherActiveAdmins((int) $target['admin_id']) < 1) {
            return 'Phải còn ít nhất một Quản trị viên đang hoạt động.';
        }
        return null;
    }

    /** Khóa nếu đang hoạt động, mở khóa nếu đang bị khóa. */
    public function toggleActive(?array $target, array $actor): ?string
    {
        if ($target === null) {
            return 'Không tìm thấy tài khoản.';
        }
        if ((int) $target['is_active'] === 1 && ($guard = $this->guardError($target, $actor, 'lock')) !== null) {
            return $guard;
        }
        if ((int) $target['admin_id'] === $actor['admin_id']) {
            return 'Bạn không thể tự khóa tài khoản của chính mình.';
        }
        $activate = (int) $target['is_active'] !== 1;
        $this->admins->setActive((int) $target['admin_id'], $activate, AuthService::newSecurityStamp());
        $this->logger->log($activate ? 'account_unlock' : 'account_lock', ($activate ? 'Mở khóa' : 'Khóa') . ' tài khoản: ' . $target['username']);
        return null;
    }

    public function delete(?array $target, array $actor): ?string
    {
        if ($target === null) {
            return 'Không tìm thấy tài khoản.';
        }
        if (($guard = $this->guardError($target, $actor, 'delete')) !== null) {
            return $guard;
        }
        $this->admins->delete((int) $target['admin_id']);
        App::uploads()->delete('avatars', $target['avatar']);
        $this->logger->log('account_delete', 'Xóa tài khoản quản trị: ' . $target['username']);
        return null;
    }

    /** Quản trị viên đặt lại mật khẩu cho tài khoản KHÁC (người dùng phải tự đổi lại ở lần đăng nhập tới). */
    public function resetPassword(?array $target, array $actor, string $new, string $confirm): ?string
    {
        if ($target === null) {
            return 'Không tìm thấy tài khoản.';
        }
        if ((int) $target['admin_id'] === $actor['admin_id']) {
            return 'Hãy đổi mật khẩu của chính bạn trong Hồ sơ cá nhân (cần nhập mật khẩu hiện tại).';
        }
        if (($pwError = Validator::passwordError($new, $target['username'])) !== null) {
            return $pwError;
        }
        if ($new !== $confirm) {
            return 'Mật khẩu nhập lại không khớp.';
        }
        $this->admins->updatePassword((int) $target['admin_id'], AuthService::hashPassword($new), AuthService::newSecurityStamp(), true);
        $this->rateLimiter->clear('admin:' . $target['username']);
        $this->logger->log('account_reset_password', 'Đặt lại mật khẩu tài khoản: ' . $target['username']);
        return null;
    }

    /**
     * Kiểm tra và lưu form thêm/sửa tài khoản.
     * @param array $data username, full_name, email, role (đã trim, username/email đã viết thường)
     * @return array{errors:array<string,string>,data:array,message:?string} message = thông báo thành công
     */
    public function save(?array $existing, array $actor, array $data, string $password, string $confirm): array
    {
        $errors = [];
        $id     = $existing ? (int) $existing['admin_id'] : 0;
        if ($existing !== null && $id === $actor['admin_id']) {
            $data['role'] = $existing['role'];   // không cho tự đổi vai trò của mình
        }

        if (!$existing) {
            if (!Validator::isValidUsername($data['username'])) {
                $errors['username'] = 'Tên đăng nhập 3–30 ký tự, chỉ gồm chữ thường không dấu, số, dấu chấm và gạch dưới.';
            } elseif ($this->admins->usernameExists($data['username'])) {
                $errors['username'] = 'Tên đăng nhập đã tồn tại.';
            }
            if (($pwError = Validator::passwordError($password, $data['username'])) !== null) {
                $errors['password'] = $pwError;
            } elseif ($password !== $confirm) {
                $errors['confirm_password'] = 'Mật khẩu nhập lại không khớp.';
            }
        }
        if (!isset(Permission::ROLES[$data['role']])) {
            $errors['role'] = 'Vai trò không hợp lệ.';
        } elseif ($existing && $existing['role'] === 'admin' && $data['role'] !== 'admin' && ($guard = $this->guardError($existing, $actor, 'demote')) !== null) {
            $errors['role'] = $guard;
        }
        if (!Validator::isValidFullName($data['full_name'])) {
            $errors['full_name'] = 'Họ tên không hợp lệ (2–100 ký tự, chỉ gồm chữ cái).';
        }
        if (!Validator::isValidEmail($data['email'])) {
            $errors['email'] = 'Email không hợp lệ.';
        } elseif ($this->admins->emailTaken($data['email'], $id)) {
            $errors['email'] = 'Email đã được tài khoản khác sử dụng.';
        }
        if ($errors) {
            return ['errors' => $errors, 'data' => $data, 'message' => null];
        }

        if ($existing) {
            $roleChanged = $existing['role'] !== $data['role'];
            $this->admins->updateAccount($id, $data['full_name'], $data['email'], $data['role'], $roleChanged ? AuthService::newSecurityStamp() : $existing['security_stamp']);
            $this->logger->log('account_update', 'Cập nhật tài khoản quản trị: ' . $existing['username'] . ($roleChanged ? ' (đổi vai trò: ' . Permission::roleLabel($existing['role']) . ' → ' . Permission::roleLabel($data['role']) . ')' : ''));
            $message = 'Đã cập nhật tài khoản.' . ($roleChanged ? ' Các phiên đăng nhập cũ của tài khoản này đã bị đăng xuất để áp dụng quyền mới.' : '');
        } else {
            $this->admins->create($data['username'], $data['full_name'], $data['email'], AuthService::hashPassword($password), $data['role'], AuthService::newSecurityStamp());
            $this->logger->log('account_create', 'Tạo tài khoản quản trị: ' . $data['username'] . ' (' . Permission::roleLabel($data['role']) . ')');
            $message = 'Đã tạo tài khoản ' . $data['username'] . '. Người dùng sẽ phải đổi mật khẩu ở lần đăng nhập đầu tiên.';
        }
        return ['errors' => [], 'data' => $data, 'message' => $message];
    }
}
