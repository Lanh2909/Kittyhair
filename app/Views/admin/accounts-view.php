<?php

declare(strict_types=1);

use App\Helpers\Format;
use App\Helpers\IpAddress;
use App\Services\Permission;

/**
 * Xem thông tin tài khoản . Biến: $viewing, $isSelf, $actCount, $recent.
 */
?>
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap">
        <h1 class="h3 mb-0 text-gray-800">Thông tin tài khoản</h1>
        <div>
            <a class="btn btn-secondary" href="<?= e(url('admin/accounts.php')) ?>">&larr; Danh sách</a>
            <a class="btn btn-primary" href="<?= e(url('admin/accounts.php?action=edit&id=' . (int) $viewing['admin_id'])) ?>"><i class="fas fa-edit"></i> Sửa</a>
        </div>
    </div>
    <div class="row">
        <div class="col-lg-4 mb-4">
            <div class="card shadow"><div class="card-body text-center">
                <?= $uploads->avatar('avatars', $viewing['avatar'], $viewing['full_name'], 'avatar-lg') ?>
                <h2 class="h5 mt-3 mb-1"><?= e($viewing['full_name']) ?></h2>
                <p class="text-muted mb-2">@<?= e($viewing['username']) ?></p>
                <p><?= Permission::roleBadge($viewing['role']) ?>
                    <?= (int) $viewing['is_active'] === 1 ? '<span class="badge badge-success">Hoạt động</span>' : '<span class="badge badge-secondary">Đã khóa</span>' ?>
                    <?= $isSelf ? '<span class="badge badge-kitty">Bạn</span>' : '' ?></p>
            </div></div>
        </div>
        <div class="col-lg-8">
            <div class="card shadow mb-4">
                <div class="card-header"><h6 class="m-0 font-weight-bold text-primary">Chi tiết</h6></div>
                <div class="card-body">
                    <table class="table table-borderless mb-0">
                        <tr><th style="width:220px">Tên đăng nhập</th><td><?= e($viewing['username']) ?></td></tr>
                        <tr><th>Họ và tên</th><td><?= e($viewing['full_name']) ?></td></tr>
                        <tr><th>Email</th><td><?= e($viewing['email']) ?></td></tr>
                        <tr><th>Vai trò</th><td><?= Permission::roleBadge($viewing['role']) ?> <span class="text-muted small"><?= $viewing['role'] === 'admin' ? 'Toàn quyền hệ thống' : 'Lịch hẹn, khách hàng, tin nhắn, hồ sơ cá nhân' ?></span></td></tr>
                        <tr><th>Ngày tạo</th><td><?= e(Format::datetime($viewing['created_at'])) ?></td></tr>
                        <tr><th>Đăng nhập gần nhất</th><td><?= $viewing['last_login_at'] ? e(Format::datetime($viewing['last_login_at'])) : '<span class="text-muted">Chưa đăng nhập</span>' ?></td></tr>
                        <tr><th>Yêu cầu đổi mật khẩu</th><td><?= (int) $viewing['must_change_password'] === 1 ? '<span class="badge badge-warning">Có – chờ người dùng đổi</span>' : 'Không' ?></td></tr>
                        <tr><th>Số thao tác đã ghi nhật ký</th><td><?= $actCount ?></td></tr>
                    </table>
                </div>
            </div>
            <div class="card shadow mb-4">
                <div class="card-header"><h6 class="m-0 font-weight-bold text-primary">10 hoạt động gần nhất</h6></div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead><tr><th>Thời gian</th><th>Hành động</th><th>Nội dung</th><th>IP</th></tr></thead>
                            <tbody>
                            <?php if (!$recent): ?><tr><td colspan="4" class="text-center text-muted py-3">Chưa có hoạt động.</td></tr><?php endif; ?>
                            <?php foreach ($recent as $log): ?>
                                <tr>
                                    <td class="text-nowrap"><?= e(Format::datetime($log['created_at'])) ?></td>
                                    <td><code><?= e($log['action']) ?></code></td>
                                    <td><?= e($log['description']) ?></td>
                                    <td class="small"><?= IpAddress::html((string) $log['ip_address']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
