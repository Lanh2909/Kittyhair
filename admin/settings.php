<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/init.php';
require_permission('settings');

$errors = [];
$values = settings_all();
$values['salon_phone'] = format_phone($values['salon_phone']);

if (is_post()) {
    verify_csrf();
    $keys = ['salon_name', 'salon_slogan', 'salon_address', 'salon_phone', 'salon_email', 'open_time', 'close_time', 'slot_step',
             'booking_days_ahead', 'min_notice_minutes', 'facebook_url', 'instagram_url', 'tiktok_url'];
    foreach ($keys as $key) {
        $values[$key] = post($key);
    }

    if (mb_strlen($values['salon_name']) < 2 || mb_strlen($values['salon_name']) > 60) {
        $errors['salon_name'] = 'Tên salon phải từ 2 đến 60 ký tự.';
    }
    if (mb_strlen($values['salon_slogan']) > 120) {
        $errors['salon_slogan'] = 'Khẩu hiệu tối đa 120 ký tự.';
    }
    if ($values['salon_address'] === '' || mb_strlen($values['salon_address']) > 200) {
        $errors['salon_address'] = 'Vui lòng nhập địa chỉ (tối đa 200 ký tự).';
    }
    $phone = normalize_phone($values['salon_phone']);
    if ($phone === null) {
        $errors['salon_phone'] = phone_error_message();
    }
    if (!is_valid_email($values['salon_email'])) {
        $errors['salon_email'] = 'Email không hợp lệ.';
    }
    if (!is_valid_time($values['open_time']) || !is_valid_time($values['close_time'])) {
        $errors['open_time'] = 'Giờ mở/đóng cửa không hợp lệ.';
    } elseif (time_to_minutes($values['open_time']) >= time_to_minutes($values['close_time'])) {
        $errors['open_time'] = 'Giờ đóng cửa phải sau giờ mở cửa.';
    }
    if (!in_array($values['slot_step'], ['15', '30', '60'], true)) {
        $errors['slot_step'] = 'Bước thời gian không hợp lệ.';
    }
    $ranges = ['booking_days_ahead' => [1, 60], 'min_notice_minutes' => [0, 1440]];
    foreach ($ranges as $key => [$min, $max]) {
        if (!ctype_digit($values[$key]) || (int) $values[$key] < $min || (int) $values[$key] > $max) {
            $errors[$key] = "Giá trị từ $min đến $max.";
        }
    }
    foreach (['facebook_url', 'instagram_url', 'tiktok_url'] as $key) {
        if ($values[$key] !== '' && (!preg_match('#^https?://#i', $values[$key]) || filter_var($values[$key], FILTER_VALIDATE_URL) === false || mb_strlen($values[$key]) > 255)) {
            $errors[$key] = 'Liên kết phải bắt đầu bằng http:// hoặc https://';
        }
    }

    if (!$errors) {
        $values['salon_phone'] = (string) $phone;
        $pdo = db();
        $pdo->beginTransaction();
        foreach ($keys as $key) {
            setting_save($key, $values[$key]);
        }
        $pdo->commit();
        log_activity('settings_update', 'Cập nhật cài đặt hệ thống.');
        flash('success', 'Đã lưu cài đặt.');
        redirect('admin/settings.php');
    }
}

/** Render 1 ô nhập của form cài đặt. */
function settings_field(string $key, string $label, array $values, array $errors, string $type = 'text', string $hint = ''): void
{
    echo '<div class="form-group"><label for="' . e($key) . '">' . e($label) . '</label>';
    echo '<input type="' . e($type) . '" id="' . e($key) . '" name="' . e($key) . '" class="form-control' . invalid_class($errors, $key) . '" value="' . e($values[$key]) . '">';
    if ($hint !== '') {
        echo '<span class="field-hint">' . e($hint) . '</span>';
    }
    echo field_error($errors, $key) . '</div>';
}

$pageTitle  = 'Cài đặt';
$activeMenu = 'settings';
require __DIR__ . '/templates/header.php';
?>
<h1 class="h3 mb-3 text-gray-800">Cài đặt hệ thống</h1>
<form method="post" novalidate>
    <?= csrf_field() ?>
    <div class="row">
        <div class="col-lg-6">
            <div class="card shadow mb-4">
                <div class="card-header"><h6 class="m-0 font-weight-bold text-primary">Thông tin salon</h6></div>
                <div class="card-body">
                    <?php
                    settings_field('salon_name', 'Tên salon', $values, $errors);
                    settings_field('salon_slogan', 'Khẩu hiệu', $values, $errors);
                    settings_field('salon_address', 'Địa chỉ', $values, $errors);
                    settings_field('salon_phone', 'Số điện thoại (hotline)', $values, $errors, 'tel', 'Số di động Việt Nam 10 số. ' . PHONE_FORMAT_HINT);
                    settings_field('salon_email', 'Email liên hệ', $values, $errors, 'email');
                    ?>
                </div>
            </div>
            <div class="card shadow mb-4">
                <div class="card-header"><h6 class="m-0 font-weight-bold text-primary">Mạng xã hội</h6></div>
                <div class="card-body">
                    <?php
                    settings_field('facebook_url', 'Facebook', $values, $errors, 'url', 'Để trống để ẩn biểu tượng trên website.');
                    settings_field('instagram_url', 'Instagram', $values, $errors, 'url');
                    settings_field('tiktok_url', 'TikTok', $values, $errors, 'url');
                    ?>
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card shadow mb-4">
                <div class="card-header"><h6 class="m-0 font-weight-bold text-primary">Giờ mở cửa & quy tắc đặt lịch</h6></div>
                <div class="card-body">
                    <div class="form-row">
                        <div class="col-md-6"><?php settings_field('open_time', 'Giờ mở cửa', $values, $errors, 'time'); ?></div>
                        <div class="col-md-6"><?php settings_field('close_time', 'Giờ đóng cửa', $values, $errors, 'time'); ?></div>
                    </div>
                    <div class="form-group">
                        <label for="slot_step">Bước thời gian giữa các khung giờ</label>
                        <select id="slot_step" name="slot_step" class="custom-select<?= invalid_class($errors, 'slot_step') ?>">
                            <?php foreach (['15' => '15 phút', '30' => '30 phút', '60' => '60 phút'] as $v => $l): ?>
                                <option value="<?= $v ?>" <?= $values['slot_step'] === $v ? 'selected' : '' ?>><?= $l ?></option>
                            <?php endforeach; ?>
                        </select>
                        <?= field_error($errors, 'slot_step') ?>
                    </div>
                    <?php
                    settings_field('booking_days_ahead', 'Cho phép đặt trước tối đa (ngày)', $values, $errors, 'number');
                    settings_field('min_notice_minutes', 'Đặt tối thiểu trước giờ hẹn (phút)', $values, $errors, 'number', 'Áp dụng cho lịch trong ngày hôm nay.');
                    ?>
                </div>
            </div>
        </div>
    </div>
    <button type="submit" class="btn btn-primary btn-lg mb-4"><i class="fas fa-save"></i> Lưu cài đặt</button>
</form>
<?php require __DIR__ . '/templates/footer.php'; ?>
