<?php

declare(strict_types=1);

use App\Core\Csrf;
use App\Helpers\Form;
use App\Helpers\Format;
use App\Services\Validator;

/**
 * Form thêm/sửa nhân viên kèm giờ làm 7 ngày (TV4). Biến: $pageTitle, $editing, $form, $errors, $scheduleInput, $scheduleErrors, $avatarUrl.
 */
?>
    <h1 class="h3 mb-3 text-gray-800"><?= e($pageTitle) ?></h1>
    <form method="post" enctype="multipart/form-data" novalidate>
        <?= Csrf::field() ?><input type="hidden" name="action" value="save"><input type="hidden" name="employee_id" value="<?= (int) ($editing['employee_id'] ?? 0) ?>">
        <div class="card shadow mb-4">
            <div class="card-header"><h6 class="m-0 font-weight-bold text-primary">Thông tin nhân viên</h6></div>
            <div class="card-body">
                <div class="form-row">
                    <div class="form-group col-md-6">
                        <label for="full_name">Họ và tên</label>
                        <input type="text" id="full_name" name="full_name" class="form-control<?= Form::invalidClass($errors, 'full_name') ?>" value="<?= e($form['full_name']) ?>" maxlength="100" required>
                        <?= Form::error($errors, 'full_name') ?>
                    </div>
                    <div class="form-group col-md-6">
                        <label for="position">Chức danh</label>
                        <input type="text" id="position" name="position" class="form-control<?= Form::invalidClass($errors, 'position') ?>" value="<?= e($form['position']) ?>" maxlength="100" required>
                        <?= Form::error($errors, 'position') ?>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group col-md-6">
                        <label for="phone">Số điện thoại</label>
                        <input type="tel" id="phone" name="phone" class="form-control<?= Form::invalidClass($errors, 'phone') ?>" value="<?= e($form['phone_number']) ?>" placeholder="0912 345 678" maxlength="20" inputmode="tel" required>
                        <span class="field-hint"><?= e(Validator::PHONE_FORMAT_HINT) ?></span>
                        <?= Form::error($errors, 'phone') ?>
                    </div>
                    <div class="form-group col-md-6">
                        <label for="email">Email</label>
                        <input type="email" id="email" name="email" class="form-control<?= Form::invalidClass($errors, 'email') ?>" value="<?= e($form['email']) ?>" maxlength="100" required>
                        <?= Form::error($errors, 'email') ?>
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
                    <?= Form::error($errors, 'avatar') ?>
                </div>
            </div>
        </div>

        <div class="card shadow mb-4">
            <div class="card-header"><h6 class="m-0 font-weight-bold text-primary">Giờ làm việc hằng tuần</h6></div>
            <div class="card-body">
                <p class="text-muted">Bật các ngày nhân viên làm việc và chọn giờ. Khách chỉ đặt được trong khung giờ này (đồng thời nằm trong giờ mở cửa của salon: <?= e($settings->get('open_time')) ?> – <?= e($settings->get('close_time')) ?>). Bỏ chọn tất cả các ngày để ngừng nhận lịch mới.</p>
                <?php if (isset($errors['schedule'])): ?><div class="alert alert-danger"><?= e($errors['schedule']) ?></div><?php endif; ?>
                <?php for ($day = 1; $day <= 7; $day++):
                    $enabled = $scheduleInput[$day]['enabled'] ?? false;
                    ?>
                    <div class="row worktime-day">
                        <div class="col-md-4 col-12">
                            <input type="checkbox" class="day-switch" id="day<?= $day ?>" name="days[<?= $day ?>][enabled]" value="1" <?= $enabled ? 'checked' : '' ?>>
                            <label class="day-name mb-0" for="day<?= $day ?>"><?= e(Format::weekday($day)) ?></label>
                        </div>
                        <div class="col-md-8 col-12 time-range" style="<?= $enabled ? '' : 'display:none' ?>">
                            <div class="form-inline">
                                <input type="time" class="form-control mr-2" name="days[<?= $day ?>][from]" value="<?= e($scheduleInput[$day]['from'] ?? '09:00') ?>" aria-label="Từ giờ">
                                <span class="mr-2">đến</span>
                                <input type="time" class="form-control" name="days[<?= $day ?>][to]" value="<?= e($scheduleInput[$day]['to'] ?? '18:00') ?>" aria-label="Đến giờ">
                                <?= Form::error($scheduleErrors, (string) $day) ?>
                            </div>
                        </div>
                    </div>
                <?php endfor; ?>
            </div>
        </div>

        <button type="submit" class="btn btn-primary">Lưu nhân viên</button>
        <a href="<?= e(url('admin/employees.php')) ?>" class="btn btn-secondary">Quay lại</a>
    </form>
