<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/init.php';
require_permission('employees');

$action = get('action');
$errors = [];

/* ------------------------------ Xử lý POST ------------------------------ */
if (is_post()) {
    verify_csrf();
    $postAction = post('action');
    $id         = (int) post('employee_id');

    if ($postAction === 'delete') {
        $employee = db_row('SELECT * FROM employees WHERE employee_id = ?', [$id]);
        $upcoming = (int) db_value('SELECT COUNT(*) FROM appointments WHERE employee_id = ? AND canceled = 0 AND start_time >= NOW()', [$id]);
        $history  = (int) db_value('SELECT COUNT(*) FROM appointments WHERE employee_id = ?', [$id]);
        if ($employee === null) {
            flash('danger', 'Không tìm thấy nhân viên.');
        } elseif ($upcoming > 0) {
            flash('danger', "Nhân viên còn $upcoming lịch hẹn sắp tới. Hãy hủy các lịch này trước khi xóa.");
        } elseif ($history > 0) {
            flash('danger', 'Nhân viên đã có lịch sử lịch hẹn nên không thể xóa để bảo toàn dữ liệu. Bạn có thể bỏ chọn tất cả ngày làm việc trong form Sửa để ngừng nhận lịch mới.');
        } else {
            db_query('DELETE FROM employees WHERE employee_id = ?', [$id]);
            delete_uploaded_file('avatars', $employee['avatar']);
            log_activity('employee_delete', 'Xóa nhân viên: ' . $employee['full_name']);
            flash('success', 'Đã xóa nhân viên.');
        }
        redirect('admin/employees.php');
    }

    if ($postAction === 'save') {
        $existing = $id > 0 ? db_row('SELECT * FROM employees WHERE employee_id = ?', [$id]) : null;
        $data = [
            'full_name' => post('full_name'),
            'position'  => post('position'),
            'phone'     => post('phone'),
            'email'     => mb_strtolower(post('email')),
        ];
        $phone    = normalize_phone($data['phone']);
        $schedule = schedule_parse((array) ($_POST['days'] ?? []));

        if (!is_valid_full_name($data['full_name'])) {
            $errors['full_name'] = 'Họ tên không hợp lệ (2–100 ký tự, chỉ gồm chữ cái).';
        }
        if (mb_strlen($data['position']) < 2 || mb_strlen($data['position']) > 100) {
            $errors['position'] = 'Chức danh phải từ 2 đến 100 ký tự.';
        }
        if ($phone === null) {
            $errors['phone'] = phone_error_message();
        } elseif (db_value('SELECT COUNT(*) FROM employees WHERE phone_number = ? AND employee_id <> ?', [$phone, $id]) > 0) {
            $errors['phone'] = 'Số điện thoại đã thuộc về nhân viên khác.';
        }
        if (!is_valid_email($data['email'])) {
            $errors['email'] = 'Email không hợp lệ.';
        } elseif (db_value('SELECT COUNT(*) FROM employees WHERE email = ? AND employee_id <> ?', [$data['email'], $id]) > 0) {
            $errors['email'] = 'Email đã thuộc về nhân viên khác.';
        }
        if ($schedule['errors']) {
            $errors['schedule'] = 'Giờ làm việc chưa hợp lệ, vui lòng kiểm tra các ngày được đánh dấu.';
        } elseif ($existing) {
            // Không cho thu hẹp giờ làm nếu làm các lịch hẹn đã đặt nằm ngoài giờ làm
            $conflicts = schedule_conflicts($id, $schedule['rows']);
            if ($conflicts) {
                $errors['schedule'] = 'Không thể lưu giờ làm vì các lịch hẹn đã đặt sẽ nằm ngoài giờ làm: ' . implode(', ', array_slice($conflicts, 0, 5)) . (count($conflicts) > 5 ? '…' : '') . '. Hãy hủy các lịch này hoặc giữ nguyên giờ làm.';
            }
        }

        $newAvatar = null;
        if (!$errors) {
            $upload = save_uploaded_image('avatar', 'avatars', UPLOAD_MAX_AVATAR);
            if ($upload['error'] !== null) {
                $errors['avatar'] = $upload['error'];
            }
            $newAvatar = $upload['file'];
        }

        if (!$errors) {
            $avatar = $existing['avatar'] ?? null;
            $oldAvatar = $avatar;
            if ($newAvatar !== null) {
                $avatar = $newAvatar;
            } elseif ($existing && post('remove_avatar') === '1') {
                $avatar = null;
            }

            $pdo = db();
            try {
                $pdo->beginTransaction();
                if ($existing) {
                    db_query('UPDATE employees SET full_name = ?, position = ?, phone_number = ?, email = ?, avatar = ? WHERE employee_id = ?',
                        [$data['full_name'], $data['position'], $phone, $data['email'], $avatar, $id]);
                    $employeeId = $id;
                } else {
                    db_query('INSERT INTO employees (full_name, position, phone_number, email, avatar) VALUES (?, ?, ?, ?, ?)',
                        [$data['full_name'], $data['position'], $phone, $data['email'], $avatar]);
                    $employeeId = (int) $pdo->lastInsertId();
                }
                schedule_save($employeeId, $schedule['rows']);
                $pdo->commit();
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                error_log('employee save failed: ' . $e->getMessage());
                delete_uploaded_file('avatars', $newAvatar);
                flash('danger', 'Không thể lưu nhân viên, vui lòng thử lại.');
                redirect('admin/employees.php');
            }

            if ($avatar !== $oldAvatar) {
                delete_uploaded_file('avatars', $oldAvatar);
            }
            log_activity($existing ? 'employee_update' : 'employee_create', ($existing ? 'Cập nhật' : 'Thêm') . ' nhân viên: ' . $data['full_name']);
            flash('success', $existing ? 'Đã cập nhật nhân viên và giờ làm việc.' : 'Đã thêm nhân viên.');
            redirect('admin/employees.php');
        }
        $action        = $existing ? 'edit' : 'create';
        $_GET['id']    = (string) $id;
        $formData      = ['full_name' => $data['full_name'], 'position' => $data['position'], 'phone_number' => $data['phone'], 'email' => $data['email'], 'avatar' => $existing['avatar'] ?? null];
        $scheduleInput = $schedule['input'];
        $scheduleErrors = $schedule['errors'];
    }
}

/* ------------------------------ Form thêm/sửa ------------------------------ */
if ($action === 'create' || $action === 'edit') {
    $editing = $action === 'edit' ? db_row('SELECT * FROM employees WHERE employee_id = ?', [(int) get('id', '0')]) : null;
    if ($action === 'edit' && $editing === null) {
        flash('danger', 'Không tìm thấy nhân viên.');
        redirect('admin/employees.php');
    }
    $form = $formData ?? $editing ?? ['full_name' => '', 'position' => 'Stylist', 'phone_number' => '', 'email' => '', 'avatar' => null];
    if (!isset($formData) && $editing) {
        $form['phone_number'] = format_phone($form['phone_number']);
    }
    $scheduleInput  = $scheduleInput ?? ($editing ? schedule_load((int) $editing['employee_id']) : schedule_default());
    $scheduleErrors = $scheduleErrors ?? [];

    $pageTitle  = $editing ? 'Sửa nhân viên' : 'Thêm nhân viên';
    $activeMenu = 'employees';
    require __DIR__ . '/templates/header.php';
    $avatarUrl = upload_url('avatars', $editing['avatar'] ?? null);
    ?>
    <h1 class="h3 mb-3 text-gray-800"><?= e($pageTitle) ?></h1>
    <form method="post" enctype="multipart/form-data" novalidate>
        <?= csrf_field() ?><input type="hidden" name="action" value="save"><input type="hidden" name="employee_id" value="<?= (int) ($editing['employee_id'] ?? 0) ?>">
        <div class="card shadow mb-4">
            <div class="card-header"><h6 class="m-0 font-weight-bold text-primary">Thông tin nhân viên</h6></div>
            <div class="card-body">
                <div class="form-row">
                    <div class="form-group col-md-6">
                        <label for="full_name">Họ và tên</label>
                        <input type="text" id="full_name" name="full_name" class="form-control<?= invalid_class($errors, 'full_name') ?>" value="<?= e($form['full_name']) ?>" maxlength="100" required>
                        <?= field_error($errors, 'full_name') ?>
                    </div>
                    <div class="form-group col-md-6">
                        <label for="position">Chức danh</label>
                        <input type="text" id="position" name="position" class="form-control<?= invalid_class($errors, 'position') ?>" value="<?= e($form['position']) ?>" maxlength="100" required>
                        <?= field_error($errors, 'position') ?>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group col-md-6">
                        <label for="phone">Số điện thoại</label>
                        <input type="tel" id="phone" name="phone" class="form-control<?= invalid_class($errors, 'phone') ?>" value="<?= e($form['phone_number']) ?>" placeholder="0912 345 678" maxlength="20" inputmode="tel" required>
                        <span class="field-hint"><?= e(PHONE_FORMAT_HINT) ?></span>
                        <?= field_error($errors, 'phone') ?>
                    </div>
                    <div class="form-group col-md-6">
                        <label for="email">Email</label>
                        <input type="email" id="email" name="email" class="form-control<?= invalid_class($errors, 'email') ?>" value="<?= e($form['email']) ?>" maxlength="100" required>
                        <?= field_error($errors, 'email') ?>
                    </div>
                </div>
                <div class="form-group mb-0">
                    <label for="avatar">Ảnh đại diện <span class="text-muted">(không bắt buộc – JPG/PNG/WEBP, tối đa 2 MB)</span></label>
                    <?php if ($avatarUrl): ?>
                        <div class="mb-2">
                            <img class="thumb-md" src="<?= e($avatarUrl) ?>" alt="Ảnh hiện tại">
                            <div class="mt-2">
                                <a class="btn btn-sm btn-outline-primary" href="<?= e($avatarUrl) ?>" target="_blank" rel="noopener"><i class="far fa-eye"></i> Xem</a>
                                <a class="btn btn-sm btn-outline-primary" href="<?= e($avatarUrl) ?>" download><i class="fas fa-download"></i> Tải xuống</a>
                                <label class="ml-2 mb-0"><input type="checkbox" name="remove_avatar" value="1"> Xóa ảnh hiện tại</label>
                            </div>
                        </div>
                    <?php endif; ?>
                    <input type="file" id="avatar" name="avatar" class="form-control-file" accept="image/jpeg,image/png,image/webp" data-preview="#avatarPreview">
                    <img id="avatarPreview" class="thumb-md mt-2" alt="Xem trước" hidden>
                    <?= field_error($errors, 'avatar') ?>
                </div>
            </div>
        </div>

        <div class="card shadow mb-4">
            <div class="card-header"><h6 class="m-0 font-weight-bold text-primary">Giờ làm việc hằng tuần</h6></div>
            <div class="card-body">
                <p class="text-muted">Bật các ngày nhân viên làm việc và chọn giờ. Khách chỉ đặt được trong khung giờ này (đồng thời nằm trong giờ mở cửa của salon: <?= e(setting('open_time')) ?> – <?= e(setting('close_time')) ?>). Bỏ chọn tất cả các ngày để ngừng nhận lịch mới.</p>
                <?php if (isset($errors['schedule'])): ?><div class="alert alert-danger"><?= e($errors['schedule']) ?></div><?php endif; ?>
                <?php for ($day = 1; $day <= 7; $day++):
                    $enabled = $scheduleInput[$day]['enabled'] ?? false;
                    ?>
                    <div class="row worktime-day">
                        <div class="col-md-4 col-12">
                            <input type="checkbox" class="day-switch" id="day<?= $day ?>" name="days[<?= $day ?>][enabled]" value="1" <?= $enabled ? 'checked' : '' ?>>
                            <label class="day-name mb-0" for="day<?= $day ?>"><?= e(weekday_name($day)) ?></label>
                        </div>
                        <div class="col-md-8 col-12 time-range" style="<?= $enabled ? '' : 'display:none' ?>">
                            <div class="form-inline">
                                <input type="time" class="form-control mr-2" name="days[<?= $day ?>][from]" value="<?= e($scheduleInput[$day]['from'] ?? '09:00') ?>" aria-label="Từ giờ">
                                <span class="mr-2">đến</span>
                                <input type="time" class="form-control" name="days[<?= $day ?>][to]" value="<?= e($scheduleInput[$day]['to'] ?? '18:00') ?>" aria-label="Đến giờ">
                                <?= field_error($scheduleErrors, (string) $day) ?>
                            </div>
                        </div>
                    </div>
                <?php endfor; ?>
            </div>
        </div>

        <button type="submit" class="btn btn-primary">Lưu nhân viên</button>
        <a href="<?= e(url('admin/employees.php')) ?>" class="btn btn-secondary">Quay lại</a>
    </form>
    <?php
    require __DIR__ . '/templates/footer.php';
    exit;
}

/* ------------------------------ Danh sách ------------------------------ */
$keyword = mb_substr(get('q'), 0, 100);
$where   = ['1 = 1'];
$params  = [];
if ($keyword !== '') {
    $digits  = preg_replace('/\D/', '', $keyword);
    $where[] = '(e.full_name LIKE ? OR e.position LIKE ? OR e.email LIKE ? OR e.phone_number LIKE ?)';
    array_push($params, like_param($keyword), like_param($keyword), like_param($keyword), like_param($digits !== '' ? $digits : $keyword));
}
$whereSql = implode(' AND ', $where);

$total = (int) db_value("SELECT COUNT(*) FROM employees e WHERE $whereSql", $params);
$pager = paginate($total, PER_PAGE_ADMIN);
$rows  = db_all(
    "SELECT e.*, (SELECT COUNT(*) FROM appointments a WHERE a.employee_id = e.employee_id AND a.canceled = 0 AND a.start_time >= NOW()) AS upcoming
     FROM employees e WHERE $whereSql ORDER BY e.full_name LIMIT {$pager['per_page']} OFFSET {$pager['offset']}",
    $params
);

$pageTitle  = 'Nhân viên';
$activeMenu = 'employees';
require __DIR__ . '/templates/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h3 mb-0 text-gray-800">Nhân viên</h1>
    <a class="btn btn-primary" href="<?= e(url('admin/employees.php?action=create')) ?>"><i class="fas fa-plus"></i> Thêm nhân viên</a>
</div>
<div class="card shadow mb-4">
    <div class="card-body">
        <?= render_search_form($keyword, 'Tìm theo tên, chức danh, số điện thoại hoặc email…') ?>
        <div class="table-responsive">
            <table class="table table-hover">
                <thead><tr><th></th><th>Họ và tên</th><th>Chức danh</th><th>Liên hệ</th><th>Giờ làm việc</th><th>Lịch sắp tới</th><th class="text-right">Thao tác</th></tr></thead>
                <tbody>
                <?php if (!$rows): ?><tr><td colspan="7" class="text-center text-muted py-4">Không tìm thấy nhân viên.</td></tr><?php endif; ?>
                <?php foreach ($rows as $row):
                    $summary = schedule_summary(schedule_load((int) $row['employee_id']));
                    ?>
                    <tr>
                        <td><?= render_avatar('avatars', $row['avatar'], $row['full_name']) ?></td>
                        <td><strong><?= e($row['full_name']) ?></strong></td>
                        <td><?= e($row['position']) ?></td>
                        <td class="text-nowrap"><?= e(format_phone($row['phone_number'])) ?><br><small class="text-muted"><?= e($row['email']) ?></small></td>
                        <td><?= $summary !== '' ? e($summary) : '<span class="text-danger small">Chưa có lịch – không nhận đặt lịch</span>' ?></td>
                        <td><?= (int) $row['upcoming'] ?></td>
                        <td class="text-right text-nowrap">
                            <a class="btn btn-sm btn-outline-primary" href="<?= e(url('admin/employees.php?action=edit&id=' . (int) $row['employee_id'])) ?>"><i class="fas fa-edit"></i> Sửa</a>
                            <form method="post" class="d-inline" data-confirm="Xóa nhân viên &quot;<?= e($row['full_name']) ?>&quot;?">
                                <?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="employee_id" value="<?= (int) $row['employee_id'] ?>">
                                <button class="btn btn-sm btn-danger" type="submit"><i class="far fa-trash-alt"></i> Xóa</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="d-flex justify-content-between align-items-center flex-wrap">
            <?= render_pager_summary($pager) ?>
            <?= render_pagination($pager, ['q' => $keyword]) ?>
        </div>
    </div>
</div>
<?php require __DIR__ . '/templates/footer.php'; ?>
