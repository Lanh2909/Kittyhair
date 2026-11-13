<?php

declare(strict_types=1);

use App\Core\Csrf;
use App\Helpers\Form;
use App\Services\Permission;

/**
 * Hồ sơ cá nhân. Biến: $admin, $old, $errors, $avatarUrl.
 */
?>
<h1 class="h3 mb-3 text-gray-800">Hồ sơ cá nhân</h1>
<?php if ((int) $admin['must_change_password'] === 1): ?>
    <div class="alert alert-warning"><i class="fas fa-exclamation-triangle"></i> Mật khẩu của bạn do quản trị viên khác đặt. <strong>Vui lòng đổi mật khẩu mới</strong> ở phần “Đổi mật khẩu” bên dưới để tiếp tục sử dụng hệ thống.</div>
<?php endif; ?>
<div class="row">
    <div class="col-lg-4 mb-4">
        <div class="card shadow"><div class="card-body text-center">
            <?= $uploads->avatar('avatars', $admin['avatar'], $admin['full_name'], 'avatar-lg') ?>
            <h2 class="h5 mt-3 mb-0"><?= e($admin['full_name']) ?></h2>
            <p class="text-muted mb-1">@<?= e($admin['username']) ?></p>
            <p><?= Permission::roleBadge($admin['role']) ?></p>
            <form method="post" enctype="multipart/form-data">
                <?= Csrf::field() ?><input type="hidden" name="action" value="avatar">
                <img id="avatarPreview" class="avatar-img avatar-lg mb-2" alt="Xem trước" hidden>
                <input type="file" name="avatar" class="form-control-file mb-2" accept="image/jpeg,image/png,image/webp" data-preview="#avatarPreview" required>
                <small class="field-hint d-block mb-2">JPG, PNG, WEBP – tối đa 2 MB</small>
                <button class="btn btn-primary btn-sm" type="submit"><i class="fas fa-upload"></i> Tải ảnh lên</button>
            </form>
            <?php if ($avatarUrl): ?>
                <div class="mt-2">
                    <a class="btn btn-sm btn-outline-primary" href="<?= e($avatarUrl) ?>" download><i class="fas fa-download"></i> Tải xuống</a>
                    <form method="post" class="d-inline" data-confirm="Xóa ảnh đại diện hiện tại?">
                        <?= Csrf::field() ?><input type="hidden" name="action" value="avatar_delete">
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
                    <?= Csrf::field() ?><input type="hidden" name="action" value="info">
                    <div class="form-group">
                        <label>Tên đăng nhập</label>
                        <input type="text" class="form-control" value="<?= e($admin['username']) ?>" disabled>
                    </div>
                    <div class="form-row">
                        <div class="form-group col-md-6">
                            <label for="full_name">Họ và tên</label>
                            <input type="text" id="full_name" name="full_name" class="form-control<?= Form::invalidClass($errors, 'full_name') ?>" value="<?= e($old['full_name']) ?>" maxlength="100" required>
                            <?= Form::error($errors, 'full_name') ?>
                        </div>
                        <div class="form-group col-md-6">
                            <label for="email">Email</label>
                            <input type="email" id="email" name="email" class="form-control<?= Form::invalidClass($errors, 'email') ?>" value="<?= e($old['email']) ?>" maxlength="100" required>
                            <?= Form::error($errors, 'email') ?>
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
                    <?= Csrf::field() ?><input type="hidden" name="action" value="password">
                    <div class="form-group">
                        <label for="current_password">Mật khẩu hiện tại</label>
                        <input type="password" id="current_password" name="current_password" class="form-control<?= Form::invalidClass($errors, 'current_password') ?>" autocomplete="current-password" required>
                        <?= Form::error($errors, 'current_password') ?>
                    </div>
                    <div class="form-row">
                        <div class="form-group col-md-6">
                            <label for="new_password">Mật khẩu mới</label>
                            <input type="password" id="new_password" name="new_password" class="form-control<?= Form::invalidClass($errors, 'new_password') ?>" autocomplete="new-password" required>
                            <?= Form::error($errors, 'new_password') ?>
                        </div>
                        <div class="form-group col-md-6">
                            <label for="confirm_password">Nhập lại mật khẩu mới</label>
                            <input type="password" id="confirm_password" name="confirm_password" class="form-control<?= Form::invalidClass($errors, 'confirm_password') ?>" autocomplete="new-password" required>
                            <?= Form::error($errors, 'confirm_password') ?>
                        </div>
                    </div>
                    <span class="field-hint d-block mb-2">Tối thiểu <?= PASSWORD_MIN_LENGTH ?> ký tự, gồm cả chữ và số, không chứa tên đăng nhập, khác mật khẩu hiện tại. Đổi mật khẩu sẽ đăng xuất các thiết bị khác.</span>
                    <button type="submit" class="btn btn-primary">Đổi mật khẩu</button>
                </form>
            </div>
        </div>
    </div>
</div>
