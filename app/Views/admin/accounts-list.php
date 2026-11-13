<?php

declare(strict_types=1);

use App\Core\Csrf;
use App\Helpers\Format;
use App\Helpers\SearchForm;
use App\Services\Permission;

/**
 * Danh sách tài khoản quản trị (TV3). Biến: $admin, $rows, $statusFilters, $status, $role, $keyword, $pager, $keep.
 */
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h3 mb-0 text-gray-800">Tài khoản quản trị</h1>
    <a class="btn btn-primary" href="<?= e(url('admin/accounts.php?action=create')) ?>"><i class="fas fa-user-plus"></i> Thêm tài khoản</a>
</div>
<div class="card shadow mb-4">
    <div class="card-body">
        <div class="filter-pills">
            <?php foreach ($statusFilters as $key => $label): ?>
                <a href="<?= e('?' . http_build_query(['status' => $key === 'all' ? '' : $key, 'role' => $role, 'q' => $keyword])) ?>" class="<?= $status === $key ? 'active' : '' ?>"><?= e($label) ?></a>
            <?php endforeach; ?>
            <span class="mx-2 text-muted">|</span>
            <a href="<?= e('?' . http_build_query(['status' => $status === 'all' ? '' : $status, 'q' => $keyword])) ?>" class="<?= $role === '' ? 'active' : '' ?>">Mọi vai trò</a>
            <?php foreach (Permission::ROLES as $value => $label): ?>
                <a href="<?= e('?' . http_build_query(['status' => $status === 'all' ? '' : $status, 'role' => $value, 'q' => $keyword])) ?>" class="<?= $role === $value ? 'active' : '' ?>"><?= e($label) ?></a>
            <?php endforeach; ?>
        </div>
        <?= SearchForm::render($keyword, 'Tìm theo tên đăng nhập, họ tên hoặc email…', ['status' => $status === 'all' ? '' : $status, 'role' => $role]) ?>

        <div class="table-responsive">
            <table class="table table-hover">
                <thead><tr><th></th><th>Tên đăng nhập</th><th>Họ và tên</th><th>Email</th><th>Vai trò</th><th>Trạng thái</th><th>Đăng nhập gần nhất</th><th class="text-right">Thao tác</th></tr></thead>
                <tbody>
                <?php if (!$rows): ?><tr><td colspan="8" class="text-center text-muted py-4">Không tìm thấy tài khoản.</td></tr><?php endif; ?>
                <?php foreach ($rows as $row):
                    $isSelf   = (int) $row['admin_id'] === $admin['admin_id'];
                    $isActive = (int) $row['is_active'] === 1;
                    ?>
                    <tr>
                        <td><?= $uploads->avatar('avatars', $row['avatar'], $row['full_name']) ?></td>
                        <td><strong><?= e($row['username']) ?></strong> <?= $isSelf ? '<span class="badge badge-kitty">Bạn</span>' : '' ?>
                            <?= (int) $row['must_change_password'] === 1 ? '<br><small class="text-warning"><i class="fas fa-key"></i> chờ đổi mật khẩu</small>' : '' ?></td>
                        <td><?= e($row['full_name']) ?></td>
                        <td><?= e($row['email']) ?></td>
                        <td><?= Permission::roleBadge($row['role']) ?></td>
                        <td><?= $isActive ? '<span class="badge badge-success">Hoạt động</span>' : '<span class="badge badge-secondary">Đã khóa</span>' ?></td>
                        <td class="text-nowrap"><?= $row['last_login_at'] ? e(Format::datetime($row['last_login_at'])) : '<span class="text-muted">Chưa đăng nhập</span>' ?></td>
                        <td class="text-right text-nowrap">
                            <a class="btn btn-sm btn-outline-info" href="<?= e(url('admin/accounts.php?action=view&id=' . (int) $row['admin_id'])) ?>" title="Xem thông tin"><i class="far fa-eye"></i></a>
                            <a class="btn btn-sm btn-outline-primary" href="<?= e(url('admin/accounts.php?action=edit&id=' . (int) $row['admin_id'])) ?>" title="Sửa"><i class="fas fa-edit"></i></a>
                            <?php if ($isSelf): ?>
                                <a class="btn btn-sm btn-outline-primary" href="<?= e(url('admin/profile.php')) ?>" title="Đổi mật khẩu của bạn"><i class="fas fa-key"></i></a>
                            <?php else: ?>
                                <button type="button" class="btn btn-sm btn-outline-primary" data-toggle="modal" data-target="#resetModal" data-id="<?= (int) $row['admin_id'] ?>" data-name="<?= e($row['username']) ?>" title="Đặt lại mật khẩu"><i class="fas fa-key"></i></button>
                                <form method="post" class="d-inline" data-confirm="<?= $isActive ? 'Khóa' : 'Mở khóa' ?> tài khoản &quot;<?= e($row['username']) ?>&quot;?">
                                    <?= Csrf::field() ?><input type="hidden" name="action" value="toggle_active"><input type="hidden" name="admin_id" value="<?= (int) $row['admin_id'] ?>">
                                    <button class="btn btn-sm btn-warning" type="submit" title="<?= $isActive ? 'Khóa' : 'Mở khóa' ?>"><i class="fas <?= $isActive ? 'fa-lock' : 'fa-lock-open' ?>"></i></button>
                                </form>
                                <form method="post" class="d-inline" data-confirm="Xóa vĩnh viễn tài khoản &quot;<?= e($row['username']) ?>&quot;?">
                                    <?= Csrf::field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="admin_id" value="<?= (int) $row['admin_id'] ?>">
                                    <button class="btn btn-sm btn-danger" type="submit" title="Xóa"><i class="far fa-trash-alt"></i></button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="d-flex justify-content-between align-items-center flex-wrap">
            <?= $pager->summary() ?>
            <?= $pager->links($keep) ?>
        </div>
    </div>
</div>

<div class="modal fade" id="resetModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <form method="post" class="modal-content" autocomplete="off">
            <?= Csrf::field() ?><input type="hidden" name="action" value="reset_password"><input type="hidden" name="admin_id" id="resetAdminId">
            <div class="modal-header">
                <h5 class="modal-title">Đặt lại mật khẩu: <span id="resetAdminName"></span></h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Đóng"><span aria-hidden="true">&times;</span></button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label for="resetPassword">Mật khẩu mới</label>
                    <div class="input-group">
                        <input type="password" id="resetPassword" name="new_password" class="form-control" autocomplete="new-password" required>
                        <div class="input-group-append"><button type="button" class="btn btn-outline-secondary" id="btnGeneratePassword" title="Tạo mật khẩu ngẫu nhiên và hiển thị để bạn gửi cho người dùng"><i class="fas fa-random"></i> Tạo ngẫu nhiên</button></div>
                    </div>
                    <span class="field-hint">Tối thiểu <?= PASSWORD_MIN_LENGTH ?> ký tự, gồm cả chữ và số.</span>
                </div>
                <div class="form-group mb-2">
                    <label for="resetConfirm">Nhập lại mật khẩu mới</label>
                    <input type="password" id="resetConfirm" name="confirm_password" class="form-control" autocomplete="new-password" required>
                </div>
                <div class="alert alert-warning small mb-0"><i class="fas fa-info-circle"></i> Người dùng sẽ bị <strong>đăng xuất khỏi mọi thiết bị</strong> và <strong>phải đổi mật khẩu</strong> ở lần đăng nhập kế tiếp. Hãy gửi mật khẩu tạm qua kênh an toàn.</div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Đóng</button>
                <button type="submit" class="btn btn-primary">Đặt lại mật khẩu</button>
            </div>
        </form>
    </div>
</div>
