<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/init.php';

$errors = [];
$old    = ['full_name' => $admin['full_name'], 'email' => $admin['email']];

if (is_post()) {
    verify_csrf();
    $action = post('action');

    if ($action === 'info') {
        $old = ['full_name' => post('full_name'), 'email' => mb_strtolower(post('email'))];
        if (!is_valid_full_name($old['full_name'])) {
            $errors['full_name'] = 'Họ tên không hợp lệ (2–100 ký tự, chỉ gồm chữ cái).';
        }
        if (!is_valid_email($old['email'])) {
            $errors['email'] = 'Email không hợp lệ.';
        } elseif (db_value('SELECT COUNT(*) FROM admins WHERE email = ? AND admin_id <> ?', [$old['email'], $admin['admin_id']]) > 0) {
            $errors['email'] = 'Email đã được sử dụng.';
        }
        if (!$errors) {
            db_query('UPDATE admins SET full_name = ?, email = ? WHERE admin_id = ?', [$old['full_name'], $old['email'], $admin['admin_id']]);
            log_activity('profile_update', 'Cập nhật hồ sơ quản trị viên.');
            flash('success', 'Đã cập nhật hồ sơ.');
            redirect('admin/profile.php');
        }
    } elseif ($action === 'avatar') {
        $upload = save_uploaded_image('avatar', 'avatars', UPLOAD_MAX_AVATAR);
        if ($upload['error'] !== null || $upload['file'] === null) {
            flash('danger', $upload['error'] ?? 'Vui lòng chọn một ảnh.');
        } else {
            delete_uploaded_file('avatars', $admin['avatar']);
            db_query('UPDATE admins SET avatar = ? WHERE admin_id = ?', [$upload['file'], $admin['admin_id']]);
            log_activity('file_upload', 'Cập nhật ảnh đại diện quản trị viên.');
            flash('success', 'Đã cập nhật ảnh đại diện.');
        }
        redirect('admin/profile.php');
    } elseif ($action === 'avatar_delete') {
        if ($admin['avatar']) {
            delete_uploaded_file('avatars', $admin['avatar']);
            db_query('UPDATE admins SET avatar = NULL WHERE admin_id = ?', [$admin['admin_id']]);
            log_activity('file_delete', 'Xóa ảnh đại diện quản trị viên.');
            flash('success', 'Đã xóa ảnh đại diện.');
        }
        redirect('admin/profile.php');
    } elseif ($action === 'password') {
        $current = (string) ($_POST['current_password'] ?? '');
        $new     = (string) ($_POST['new_password'] ?? '');
        $confirm = (string) ($_POST['confirm_password'] ?? '');
        if (!password_verify($current, $admin['password_hash'])) {
            $errors['current_password'] = 'Mật khẩu hiện tại không đúng.';
        } elseif (($pwError = password_error($new, $admin['username'])) !== null) {
            $errors['new_password'] = $pwError;
        } elseif (password_verify($new, $admin['password_hash'])) {
            $errors['new_password'] = 'Mật khẩu mới phải khác mật khẩu hiện tại.';
        } elseif ($new !== $confirm) {
            $errors['confirm_password'] = 'Mật khẩu nhập lại không khớp.';
        } else {
            // Đổi security_stamp để đăng xuất mọi phiên khác đang dùng mật khẩu cũ; phiên hiện tại được giữ lại
            $stamp = new_security_stamp();
            db_query(
                'UPDATE admins SET password_hash = ?, security_stamp = ?, must_change_password = 0 WHERE admin_id = ?',
                [password_hash($new, PASSWORD_DEFAULT), $stamp, $admin['admin_id']]
            );
            $_SESSION['admin_stamp'] = $stamp;
            session_regenerate_id(true);
            log_activity('password_change', 'Đổi mật khẩu quản trị viên.');
            flash('success', 'Đã đổi mật khẩu thành công.');
            redirect('admin/profile.php');
        }
    }
}

$avatarUrl = upload_url('avatars', $admin['avatar']);

$pageTitle  = 'Hồ sơ cá nhân';
$activeMenu = '';
require __DIR__ . '/templates/header.php';
?>
<h1 class="h3 mb-3 text-gray-800">Hồ sơ cá nhân</h1>
<?php if ((int) $admin['must_change_password'] === 1): ?>
    <div class="alert alert-warning"><i class="fas fa-exclamation-triangle"></i> Mật khẩu của bạn do quản trị viên khác đặt. <strong>Vui lòng đổi mật khẩu mới</strong> ở phần “Đổi mật khẩu” bên dưới để tiếp tục sử dụng hệ thống.</div>
<?php endif; ?>
<div class="row">
    <div class="col-lg-4 mb-4">
        <div class="card shadow"><div class="card-body text-center">
            <?= render_avatar('avatars', $admin['avatar'], $admin['full_name'], 'avatar-lg') ?>
            <h2 class="h5 mt-3 mb-0"><?= e($admin['full_name']) ?></h2>
            <p class="text-muted mb-1">@<?= e($admin['username']) ?></p>
            <p><?= admin_role_badge($admin['role']) ?></p>
            <form method="post" enctype="multipart/form-data">
                <?= csrf_field() ?><input type="hidden" name="action" value="avatar">
                <img id="avatarPreview" class="avatar-img avatar-lg mb-2" alt="Xem trước" hidden>
                <input type="file" name="avatar" class="form-control-file mb-2" accept="image/jpeg,image/png,image/webp" data-preview="#avatarPreview" required>
                <small class="field-hint d-block mb-2">JPG, PNG, WEBP – tối đa 2 MB</small>
                <button class="btn btn-primary btn-sm" type="submit"><i class="fas fa-upload"></i> Tải ảnh lên</button>
            </form>
            <?php if ($avatarUrl): ?>
                <div class="mt-2">
                    <a class="btn btn-sm btn-outline-primary" href="<?= e($avatarUrl) ?>" download><i class="fas fa-download"></i> Tải xuống</a>
                    <form method="post" class="d-inline" data-confirm="Xóa ảnh đại diện hiện tại?">
                        <?= csrf_field() ?><input type="hidden" name="action" value="avatar_delete">
                        <button class="btn btn-sm btn-outline-primary" type="submit"><i class="far fa-trash-alt"></i> Xóa ảnh</button>
                    </form>
                </div>
            <?php endif; ?>
        </div></div>
    </div>
    <div class="col-lg-8">
        <div class="card shadow mb-4">
            <div class="card-header"><h6 class="m-0 font-weight-bold text-primary">Thông tin tài khoản</h6></div>
            <div class="card-body">
                <form method="post" novalidate>
                    <?= csrf_field() ?><input type="hidden" name="action" value="info">
                    <div class="form-group">
                        <label>Tên đăng nhập</label>
                        <input type="text" class="form-control" value="<?= e($admin['username']) ?>" disabled>
                    </div>
                    <div class="form-row">
                        <div class="form-group col-md-6">
                            <label for="full_name">Họ và tên</label>
                            <input type="text" id="full_name" name="full_name" class="form-control<?= invalid_class($errors, 'full_name') ?>" value="<?= e($old['full_name']) ?>" maxlength="100" required>
                            <?= field_error($errors, 'full_name') ?>
                        </div>
                        <div class="form-group col-md-6">
                            <label for="email">Email</label>
                            <input type="email" id="email" name="email" class="form-control<?= invalid_class($errors, 'email') ?>" value="<?= e($old['email']) ?>" maxlength="100" required>
                            <?= field_error($errors, 'email') ?>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary">Lưu thay đổi</button>
                </form>
            </div>
        </div>
        <div class="card shadow">
            <div class="card-header"><h6 class="m-0 font-weight-bold text-primary">Đổi mật khẩu</h6></div>
            <div class="card-body">
                <form method="post" novalidate>
                    <?= csrf_field() ?><input type="hidden" name="action" value="password">
                    <div class="form-group">
                        <label for="current_password">Mật khẩu hiện tại</label>
                        <input type="password" id="current_password" name="current_password" class="form-control<?= invalid_class($errors, 'current_password') ?>" autocomplete="current-password" required>
                        <?= field_error($errors, 'current_password') ?>
                    </div>
                    <div class="form-row">
                        <div class="form-group col-md-6">
                            <label for="new_password">Mật khẩu mới</label>
                            <input type="password" id="new_password" name="new_password" class="form-control<?= invalid_class($errors, 'new_password') ?>" autocomplete="new-password" required>
                            <?= field_error($errors, 'new_password') ?>
                        </div>
                        <div class="form-group col-md-6">
                            <label for="confirm_password">Nhập lại mật khẩu mới</label>
                            <input type="password" id="confirm_password" name="confirm_password" class="form-control<?= invalid_class($errors, 'confirm_password') ?>" autocomplete="new-password" required>
                            <?= field_error($errors, 'confirm_password') ?>
                        </div>
                    </div>
                    <span class="field-hint d-block mb-2">Tối thiểu <?= PASSWORD_MIN_LENGTH ?> ký tự, gồm cả chữ và số, không chứa tên đăng nhập, khác mật khẩu hiện tại. Đổi mật khẩu sẽ đăng xuất các thiết bị khác.</span>
                    <button type="submit" class="btn btn-primary">Đổi mật khẩu</button>
                </form>
            </div>
        </div>
    </div>
</div>
<?php require __DIR__ . '/templates/footer.php'; ?>
