<?php

declare(strict_types=1);

/**
 * Quản lý tài khoản quản trị (chỉ vai trò Quản trị viên): xem thông tin, thêm, sửa (kèm vai trò),
 * đặt lại mật khẩu, khóa/mở khóa, xóa.
 *
 * An toàn:
 *  - Không tự khóa / xóa / đổi vai trò chính mình; luôn còn ít nhất 1 Quản trị viên đang hoạt động.
 *  - Tài khoản mới và tài khoản vừa được đặt lại mật khẩu bị buộc đổi mật khẩu ở lần đăng nhập kế tiếp.
 *  - Đặt lại mật khẩu / đổi vai trò làm đổi security_stamp -> mọi phiên đăng nhập cũ của tài khoản đó bị vô hiệu.
 */

require_once __DIR__ . '/includes/init.php';
require_permission('accounts');

/** Tên đăng nhập: 3–30 ký tự chữ thường, số, dấu chấm, gạch dưới. */
function is_valid_username(string $username): bool
{
    return preg_match('/^[a-z0-9_.]{3,30}$/', $username) === 1;
}

/** Số Quản trị viên đang hoạt động, không tính tài khoản $excludeId. */
function count_other_active_admins(int $excludeId): int
{
    return (int) db_value("SELECT COUNT(*) FROM admins WHERE role = 'admin' AND is_active = 1 AND admin_id <> ?", [$excludeId]);
}

/**
 * Lý do KHÔNG được thực hiện thao tác nguy hiểm trên $target (null nếu được phép).
 * @param string $operation lock | delete | demote
 */
function account_guard_error(array $target, array $actor, string $operation): ?string
{
    $verb = ['lock' => 'khóa', 'delete' => 'xóa', 'demote' => 'hạ quyền'][$operation] ?? $operation;
    if ((int) $target['admin_id'] === $actor['admin_id']) {
        return "Bạn không thể tự $verb tài khoản của chính mình.";
    }
    if ($target['role'] === 'admin' && (int) $target['is_active'] === 1 && count_other_active_admins((int) $target['admin_id']) < 1) {
        return 'Phải còn ít nhất một Quản trị viên đang hoạt động.';
    }
    return null;
}

$action = get('action');
$errors = [];

/* ------------------------------ Xử lý POST ------------------------------ */
if (is_post()) {
    verify_csrf();
    $postAction = post('action');
    $id         = (int) post('admin_id');
    $target     = $id > 0 ? db_row('SELECT * FROM admins WHERE admin_id = ?', [$id]) : null;
    $backToList = 'admin/accounts.php' . (empty($_SERVER['QUERY_STRING']) ? '' : '?' . $_SERVER['QUERY_STRING']);

    if ($postAction === 'toggle_active') {
        if ($target === null) {
            flash('danger', 'Không tìm thấy tài khoản.');
        } elseif ((int) $target['is_active'] === 1 && ($guard = account_guard_error($target, $admin, 'lock')) !== null) {
            flash('danger', $guard);
        } elseif ($id === $admin['admin_id']) {
            flash('danger', 'Bạn không thể tự khóa tài khoản của chính mình.');
        } else {
            $newState = (int) $target['is_active'] === 1 ? 0 : 1;
            db_query('UPDATE admins SET is_active = ?, security_stamp = ? WHERE admin_id = ?', [$newState, new_security_stamp(), $id]);
            log_activity($newState ? 'account_unlock' : 'account_lock', ($newState ? 'Mở khóa' : 'Khóa') . ' tài khoản: ' . $target['username']);
            flash('success', ($newState ? 'Đã mở khóa' : 'Đã khóa') . ' tài khoản ' . $target['username'] . '.');
        }
        redirect($backToList);
    }

    if ($postAction === 'delete') {
        if ($target === null) {
            flash('danger', 'Không tìm thấy tài khoản.');
        } elseif (($guard = account_guard_error($target, $admin, 'delete')) !== null) {
            flash('danger', $guard);
        } else {
            db_query('DELETE FROM admins WHERE admin_id = ?', [$id]);
            delete_uploaded_file('avatars', $target['avatar']);
            log_activity('account_delete', 'Xóa tài khoản quản trị: ' . $target['username']);
            flash('success', 'Đã xóa tài khoản ' . $target['username'] . '.');
        }
        redirect('admin/accounts.php');
    }

    if ($postAction === 'reset_password') {
        $new     = (string) ($_POST['new_password'] ?? '');
        $confirm = (string) ($_POST['confirm_password'] ?? '');
        if ($target === null) {
            flash('danger', 'Không tìm thấy tài khoản.');
        } elseif ($id === $admin['admin_id']) {
            flash('danger', 'Hãy đổi mật khẩu của chính bạn trong Hồ sơ cá nhân (cần nhập mật khẩu hiện tại).');
        } elseif (($pwError = password_error($new, $target['username'])) !== null) {
            flash('danger', $pwError);
        } elseif ($new !== $confirm) {
            flash('danger', 'Mật khẩu nhập lại không khớp.');
        } else {
            db_query(
                'UPDATE admins SET password_hash = ?, must_change_password = 1, security_stamp = ? WHERE admin_id = ?',
                [password_hash($new, PASSWORD_DEFAULT), new_security_stamp(), $id]
            );
            rate_limit_clear('admin:' . $target['username']);
            log_activity('account_reset_password', 'Đặt lại mật khẩu tài khoản: ' . $target['username']);
            flash('success', 'Đã đặt lại mật khẩu cho ' . $target['username'] . '. Người dùng sẽ bị yêu cầu đổi mật khẩu ở lần đăng nhập tới và mọi phiên đăng nhập cũ đã bị đăng xuất.');
        }
        redirect($backToList);
    }

    if ($postAction === 'save') {
        $existing = $target;
        $data = [
            'username'  => mb_strtolower(post('username')),
            'full_name' => post('full_name'),
            'email'     => mb_strtolower(post('email')),
            'role'      => post('role'),
        ];
        $password = (string) ($_POST['password'] ?? '');
        $confirm  = (string) ($_POST['confirm_password'] ?? '');

        if ($id > 0 && $existing === null) {
            flash('danger', 'Không tìm thấy tài khoản.');
            redirect('admin/accounts.php');
        }
        $isSelf = $existing !== null && (int) $existing['admin_id'] === $admin['admin_id'];
        if ($isSelf) {
            $data['role'] = $existing['role'];   // không cho tự đổi vai trò của mình
        }

        if (!$existing) {
            if (!is_valid_username($data['username'])) {
                $errors['username'] = 'Tên đăng nhập 3–30 ký tự, chỉ gồm chữ thường không dấu, số, dấu chấm và gạch dưới.';
            } elseif (db_value('SELECT COUNT(*) FROM admins WHERE username = ?', [$data['username']]) > 0) {
                $errors['username'] = 'Tên đăng nhập đã tồn tại.';
            }
            if (($pwError = password_error($password, $data['username'])) !== null) {
                $errors['password'] = $pwError;
            } elseif ($password !== $confirm) {
                $errors['confirm_password'] = 'Mật khẩu nhập lại không khớp.';
            }
        }
        if (!isset(ADMIN_ROLES[$data['role']])) {
            $errors['role'] = 'Vai trò không hợp lệ.';
        } elseif ($existing && $existing['role'] === 'admin' && $data['role'] !== 'admin' && ($guard = account_guard_error($existing, $admin, 'demote')) !== null) {
            $errors['role'] = $guard;
        }
        if (!is_valid_full_name($data['full_name'])) {
            $errors['full_name'] = 'Họ tên không hợp lệ (2–100 ký tự, chỉ gồm chữ cái).';
        }
        if (!is_valid_email($data['email'])) {
            $errors['email'] = 'Email không hợp lệ.';
        } elseif (db_value('SELECT COUNT(*) FROM admins WHERE email = ? AND admin_id <> ?', [$data['email'], $id]) > 0) {
            $errors['email'] = 'Email đã được tài khoản khác sử dụng.';
        }

        if (!$errors) {
            if ($existing) {
                $roleChanged = $existing['role'] !== $data['role'];
                db_query(
                    'UPDATE admins SET full_name = ?, email = ?, role = ?, security_stamp = ? WHERE admin_id = ?',
                    [$data['full_name'], $data['email'], $data['role'], $roleChanged ? new_security_stamp() : $existing['security_stamp'], $id]
                );
                log_activity('account_update', 'Cập nhật tài khoản quản trị: ' . $existing['username'] . ($roleChanged ? ' (đổi vai trò: ' . admin_role_label($existing['role']) . ' → ' . admin_role_label($data['role']) . ')' : ''));
                flash('success', 'Đã cập nhật tài khoản.' . ($roleChanged ? ' Các phiên đăng nhập cũ của tài khoản này đã bị đăng xuất để áp dụng quyền mới.' : ''));
            } else {
                db_query(
                    'INSERT INTO admins (username, full_name, email, password_hash, role, must_change_password, security_stamp) VALUES (?, ?, ?, ?, ?, 1, ?)',
                    [$data['username'], $data['full_name'], $data['email'], password_hash($password, PASSWORD_DEFAULT), $data['role'], new_security_stamp()]
                );
                log_activity('account_create', 'Tạo tài khoản quản trị: ' . $data['username'] . ' (' . admin_role_label($data['role']) . ')');
                flash('success', 'Đã tạo tài khoản ' . $data['username'] . '. Người dùng sẽ phải đổi mật khẩu ở lần đăng nhập đầu tiên.');
            }
            redirect('admin/accounts.php');
        }
        $action     = $existing ? 'edit' : 'create';
        $_GET['id'] = (string) $id;
        $formData   = $data;
    }
}

/* ------------------------------ Xem thông tin ------------------------------ */
if ($action === 'view') {
    $viewing = db_row('SELECT * FROM admins WHERE admin_id = ?', [(int) get('id', '0')]);
    if ($viewing === null) {
        flash('danger', 'Không tìm thấy tài khoản.');
        redirect('admin/accounts.php');
    }
    $isSelf   = (int) $viewing['admin_id'] === $admin['admin_id'];
    $actCount = (int) db_value("SELECT COUNT(*) FROM activity_logs WHERE actor_type = 'admin' AND actor_id = ?", [$viewing['admin_id']]);
    $recent   = db_all("SELECT * FROM activity_logs WHERE actor_type = 'admin' AND actor_id = ? ORDER BY log_id DESC LIMIT 10", [$viewing['admin_id']]);

    $pageTitle  = 'Thông tin tài khoản';
    $activeMenu = 'accounts';
    require __DIR__ . '/templates/header.php';
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
                <?= render_avatar('avatars', $viewing['avatar'], $viewing['full_name'], 'avatar-lg') ?>
                <h2 class="h5 mt-3 mb-1"><?= e($viewing['full_name']) ?></h2>
                <p class="text-muted mb-2">@<?= e($viewing['username']) ?></p>
                <p><?= admin_role_badge($viewing['role']) ?>
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
                        <tr><th>Vai trò</th><td><?= admin_role_badge($viewing['role']) ?> <span class="text-muted small"><?= $viewing['role'] === 'admin' ? 'Toàn quyền hệ thống' : 'Lịch hẹn, khách hàng, tin nhắn, hồ sơ cá nhân' ?></span></td></tr>
                        <tr><th>Ngày tạo</th><td><?= e(format_datetime($viewing['created_at'])) ?></td></tr>
                        <tr><th>Đăng nhập gần nhất</th><td><?= $viewing['last_login_at'] ? e(format_datetime($viewing['last_login_at'])) : '<span class="text-muted">Chưa đăng nhập</span>' ?></td></tr>
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
                                    <td class="text-nowrap"><?= e(format_datetime($log['created_at'])) ?></td>
                                    <td><code><?= e($log['action']) ?></code></td>
                                    <td><?= e($log['description']) ?></td>
                                    <td class="small"><?= format_ip_html((string) $log['ip_address']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php
    require __DIR__ . '/templates/footer.php';
    exit;
}

/* ------------------------------ Form thêm/sửa ------------------------------ */
if ($action === 'create' || $action === 'edit') {
    $editing = $action === 'edit' ? db_row('SELECT * FROM admins WHERE admin_id = ?', [(int) get('id', '0')]) : null;
    if ($action === 'edit' && $editing === null) {
        flash('danger', 'Không tìm thấy tài khoản.');
        redirect('admin/accounts.php');
    }
    $form   = $formData ?? $editing ?? ['username' => '', 'full_name' => '', 'email' => '', 'role' => 'staff'];
    $isSelf = $editing !== null && (int) $editing['admin_id'] === $admin['admin_id'];

    $pageTitle  = $editing ? 'Sửa tài khoản quản trị' : 'Thêm tài khoản quản trị';
    $activeMenu = 'accounts';
    require __DIR__ . '/templates/header.php';
    ?>
    <h1 class="h3 mb-3 text-gray-800"><?= e($pageTitle) ?></h1>
    <div class="card shadow mb-4"><div class="card-body">
        <form method="post" novalidate>
            <?= csrf_field() ?><input type="hidden" name="action" value="save"><input type="hidden" name="admin_id" value="<?= (int) ($editing['admin_id'] ?? 0) ?>">
            <div class="form-row">
                <div class="form-group col-md-6">
                    <label for="username">Tên đăng nhập</label>
                    <input type="text" id="username" name="username" class="form-control<?= invalid_class($errors, 'username') ?>" value="<?= e($form['username']) ?>" maxlength="30" autocomplete="off" <?= $editing ? 'disabled' : 'required' ?>>
                    <?php if ($editing): ?><span class="field-hint">Không thể đổi tên đăng nhập.</span><?php else: ?><span class="field-hint">3–30 ký tự: chữ thường không dấu, số, dấu chấm, gạch dưới.</span><?php endif; ?>
                    <?= field_error($errors, 'username') ?>
                </div>
                <div class="form-group col-md-6">
                    <label for="full_name">Họ và tên</label>
                    <input type="text" id="full_name" name="full_name" class="form-control<?= invalid_class($errors, 'full_name') ?>" value="<?= e($form['full_name']) ?>" maxlength="100" required>
                    <?= field_error($errors, 'full_name') ?>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group col-md-6">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" class="form-control<?= invalid_class($errors, 'email') ?>" value="<?= e($form['email']) ?>" maxlength="100" required>
                    <?= field_error($errors, 'email') ?>
                </div>
                <div class="form-group col-md-6">
                    <label for="role">Vai trò</label>
                    <select id="role" name="role" class="custom-select<?= invalid_class($errors, 'role') ?>" <?= $isSelf ? 'disabled' : '' ?>>
                        <?php foreach (ADMIN_ROLES as $value => $label): ?>
                            <option value="<?= e($value) ?>" <?= $form['role'] === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <span class="field-hint"><?= $isSelf ? 'Bạn không thể tự đổi vai trò của mình. ' : '' ?><strong>Quản trị viên:</strong> toàn quyền. <strong>Lễ tân:</strong> chỉ lịch hẹn, khách hàng, tin nhắn và hồ sơ cá nhân.</span>
                    <?= field_error($errors, 'role') ?>
                </div>
            </div>
            <?php if (!$editing): ?>
                <div class="form-row">
                    <div class="form-group col-md-6">
                        <label for="password">Mật khẩu tạm</label>
                        <input type="password" id="password" name="password" class="form-control<?= invalid_class($errors, 'password') ?>" autocomplete="new-password" required>
                        <span class="field-hint">Tối thiểu <?= PASSWORD_MIN_LENGTH ?> ký tự, gồm cả chữ và số, không chứa tên đăng nhập.</span>
                        <?= field_error($errors, 'password') ?>
                    </div>
                    <div class="form-group col-md-6">
                        <label for="confirm_password">Nhập lại mật khẩu</label>
                        <input type="password" id="confirm_password" name="confirm_password" class="form-control<?= invalid_class($errors, 'confirm_password') ?>" autocomplete="new-password" required>
                        <?= field_error($errors, 'confirm_password') ?>
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
    <?php
    require __DIR__ . '/templates/footer.php';
    exit;
}

/* ------------------------------ Danh sách ------------------------------ */
$statusFilters = ['all' => 'Tất cả', 'active' => 'Đang hoạt động', 'locked' => 'Đã khóa'];
$status  = array_key_exists(get('status'), $statusFilters) ? get('status') : 'all';
$role    = array_key_exists(get('role'), ADMIN_ROLES) ? get('role') : '';
$keyword = mb_substr(get('q'), 0, 100);

$where  = ['1 = 1'];
$params = [];
if ($status === 'active') {
    $where[] = 'is_active = 1';
} elseif ($status === 'locked') {
    $where[] = 'is_active = 0';
}
if ($role !== '') {
    $where[]  = 'role = ?';
    $params[] = $role;
}
if ($keyword !== '') {
    $where[] = '(username LIKE ? OR full_name LIKE ? OR email LIKE ?)';
    array_push($params, ...array_fill(0, 3, like_param($keyword)));
}
$whereSql = implode(' AND ', $where);

$total = (int) db_value("SELECT COUNT(*) FROM admins WHERE $whereSql", $params);
$pager = paginate($total, PER_PAGE_ADMIN);
$rows  = db_all("SELECT * FROM admins WHERE $whereSql ORDER BY created_at, admin_id LIMIT {$pager['per_page']} OFFSET {$pager['offset']}", $params);
$keep  = ['status' => $status === 'all' ? '' : $status, 'role' => $role, 'q' => $keyword];

$pageTitle  = 'Tài khoản quản trị';
$activeMenu = 'accounts';
require __DIR__ . '/templates/header.php';
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
            <?php foreach (ADMIN_ROLES as $value => $label): ?>
                <a href="<?= e('?' . http_build_query(['status' => $status === 'all' ? '' : $status, 'role' => $value, 'q' => $keyword])) ?>" class="<?= $role === $value ? 'active' : '' ?>"><?= e($label) ?></a>
            <?php endforeach; ?>
        </div>
        <?= render_search_form($keyword, 'Tìm theo tên đăng nhập, họ tên hoặc email…', ['status' => $status === 'all' ? '' : $status, 'role' => $role]) ?>

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
                        <td><?= render_avatar('avatars', $row['avatar'], $row['full_name']) ?></td>
                        <td><strong><?= e($row['username']) ?></strong> <?= $isSelf ? '<span class="badge badge-kitty">Bạn</span>' : '' ?>
                            <?= (int) $row['must_change_password'] === 1 ? '<br><small class="text-warning"><i class="fas fa-key"></i> chờ đổi mật khẩu</small>' : '' ?></td>
                        <td><?= e($row['full_name']) ?></td>
                        <td><?= e($row['email']) ?></td>
                        <td><?= admin_role_badge($row['role']) ?></td>
                        <td><?= $isActive ? '<span class="badge badge-success">Hoạt động</span>' : '<span class="badge badge-secondary">Đã khóa</span>' ?></td>
                        <td class="text-nowrap"><?= $row['last_login_at'] ? e(format_datetime($row['last_login_at'])) : '<span class="text-muted">Chưa đăng nhập</span>' ?></td>
                        <td class="text-right text-nowrap">
                            <a class="btn btn-sm btn-outline-info" href="<?= e(url('admin/accounts.php?action=view&id=' . (int) $row['admin_id'])) ?>" title="Xem thông tin"><i class="far fa-eye"></i></a>
                            <a class="btn btn-sm btn-outline-primary" href="<?= e(url('admin/accounts.php?action=edit&id=' . (int) $row['admin_id'])) ?>" title="Sửa"><i class="fas fa-edit"></i></a>
                            <?php if ($isSelf): ?>
                                <a class="btn btn-sm btn-outline-primary" href="<?= e(url('admin/profile.php')) ?>" title="Đổi mật khẩu của bạn"><i class="fas fa-key"></i></a>
                            <?php else: ?>
                                <button type="button" class="btn btn-sm btn-outline-primary" data-toggle="modal" data-target="#resetModal" data-id="<?= (int) $row['admin_id'] ?>" data-name="<?= e($row['username']) ?>" title="Đặt lại mật khẩu"><i class="fas fa-key"></i></button>
                                <form method="post" class="d-inline" data-confirm="<?= $isActive ? 'Khóa' : 'Mở khóa' ?> tài khoản &quot;<?= e($row['username']) ?>&quot;?">
                                    <?= csrf_field() ?><input type="hidden" name="action" value="toggle_active"><input type="hidden" name="admin_id" value="<?= (int) $row['admin_id'] ?>">
                                    <button class="btn btn-sm btn-warning" type="submit" title="<?= $isActive ? 'Khóa' : 'Mở khóa' ?>"><i class="fas <?= $isActive ? 'fa-lock' : 'fa-lock-open' ?>"></i></button>
                                </form>
                                <form method="post" class="d-inline" data-confirm="Xóa vĩnh viễn tài khoản &quot;<?= e($row['username']) ?>&quot;?">
                                    <?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="admin_id" value="<?= (int) $row['admin_id'] ?>">
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
            <?= render_pager_summary($pager) ?>
            <?= render_pagination($pager, $keep) ?>
        </div>
    </div>
</div>

<div class="modal fade" id="resetModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <form method="post" class="modal-content" autocomplete="off">
            <?= csrf_field() ?><input type="hidden" name="action" value="reset_password"><input type="hidden" name="admin_id" id="resetAdminId">
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
<?php require __DIR__ . '/templates/footer.php'; ?>
