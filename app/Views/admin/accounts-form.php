<?php

declare(strict_types=1);

use App\Core\Csrf;
use App\Helpers\Form;
use App\Services\Permission;

/**
 * Form thêm/sửa tài khoản quản trị . Biến: $pageTitle, $editing, $form, $errors, $isSelf.
 */
?>
    <h1 class="h3 mb-3 text-gray-800"><?= e($pageTitle) ?></h1>
    <div class="card shadow mb-4"><div class="card-body">
        <form method="post" novalidate>
            <?= Csrf::field() ?><input type="hidden" name="action" value="save"><input type="hidden" name="admin_id" value="<?= (int) ($editing['admin_id'] ?? 0) ?>">
            <div class="form-row">
                <div class="form-group col-md-6">
                    <label for="username">Tên đăng nhập</label>
                    <input type="text" id="username" name="username" class="form-control<?= Form::invalidClass($errors, 'username') ?>" value="<?= e($form['username']) ?>" maxlength="30" autocomplete="off" <?= $editing ? 'disabled' : 'required' ?>>
                    <?php if ($editing): ?><span class="field-hint">Không thể đổi tên đăng nhập.</span><?php else: ?><span class="field-hint">3–30 ký tự: chữ thường không dấu, số, dấu chấm, gạch dưới.</span><?php endif; ?>
                    <?= Form::error($errors, 'username') ?>
                </div>
                <div class="form-group col-md-6">
                    <label for="full_name">Họ và tên</label>
                    <input type="text" id="full_name" name="full_name" class="form-control<?= Form::invalidClass($errors, 'full_name') ?>" value="<?= e($form['full_name']) ?>" maxlength="100" required>
                    <?= Form::error($errors, 'full_name') ?>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group col-md-6">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" class="form-control<?= Form::invalidClass($errors, 'email') ?>" value="<?= e($form['email']) ?>" maxlength="100" required>
                    <?= Form::error($errors, 'email') ?>
                </div>
                <div class="form-group col-md-6">
                    <label for="role">Vai trò</label>
                    <select id="role" name="role" class="custom-select<?= Form::invalidClass($errors, 'role') ?>" <?= $isSelf ? 'disabled' : '' ?>>
                        <?php foreach (Permission::ROLES as $value => $label): ?>
                            <option value="<?= e($value) ?>" <?= $form['role'] === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <span class="field-hint"><?= $isSelf ? 'Bạn không thể tự đổi vai trò của mình. ' : '' ?><strong>Quản trị viên:</strong> toàn quyền. <strong>Lễ tân:</strong> chỉ lịch hẹn, khách hàng, tin nhắn và hồ sơ cá nhân.</span>
                    <?= Form::error($errors, 'role') ?>
                </div>
            </div>
            <?php if (!$editing): ?>
                <div class="form-row">
                    <div class="form-group col-md-6">
                        <label for="password">Mật khẩu tạm</label>
                        <input type="password" id="password" name="password" class="form-control<?= Form::invalidClass($errors, 'password') ?>" autocomplete="new-password" required>
                        <span class="field-hint">Tối thiểu <?= PASSWORD_MIN_LENGTH ?> ký tự, gồm cả chữ và số, không chứa tên đăng nhập.</span>
                        <?= Form::error($errors, 'password') ?>
                    </div>
                    <div class="form-group col-md-6">
                        <label for="confirm_password">Nhập lại mật khẩu</label>
                        <input type="password" id="confirm_password" name="confirm_password" class="form-control<?= Form::invalidClass($errors, 'confirm_password') ?>" autocomplete="new-password" required>
                        <?= Form::error($errors, 'confirm_password') ?>
                    </div>
                </div>
                <p class="text-muted small">Người dùng sẽ <strong>bị yêu cầu đổi mật khẩu</strong> ở lần đăng nhập đầu tiên.</p>
            <?php else: ?>
                <p class="text-muted small">Để đặt lại mật khẩu, dùng nút <i class="fas fa-key"></i> ở danh sách tài khoản. Thay đổi vai trò sẽ đăng xuất các phiên đăng nhập hiện có của tài khoản này.</p>
            <?php endif; ?>
            <button type="submit" class="btn btn-primary">Lưu tài khoản</button>
            <a href="<?= e(url('admin/accounts.php')) ?>" class="btn btn-secondary">Quay lại</a>
        </form>
    </div></div>
