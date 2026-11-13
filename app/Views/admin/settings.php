<?php

declare(strict_types=1);

use App\Core\Csrf;
use App\Helpers\Form;
use App\Services\Validator;

/**
 * Cài đặt hệ thống (TV4). Biến: $values, $errors.
 */

/** Render 1 ô nhập của form cài đặt (trước đây là hàm settings_field() trong admin/settings.php). */
$field = static function (string $key, string $label, array $values, array $errors, string $type = 'text', string $hint = ''): void {
    echo '<div class="form-group"><label for="' . e($key) . '">' . e($label) . '</label>';
    echo '<input type="' . e($type) . '" id="' . e($key) . '" name="' . e($key) . '" class="form-control' . Form::invalidClass($errors, $key) . '" value="' . e($values[$key]) . '">';
    if ($hint !== '') {
        echo '<span class="field-hint">' . e($hint) . '</span>';
    }
    echo Form::error($errors, $key) . '</div>';
};
?>
<h1 class="h3 mb-3 text-gray-800">Cài đặt hệ thống</h1>
<form method="post" novalidate>
    <?= Csrf::field() ?>
    <div class="row">
        <div class="col-lg-6">
            <div class="card shadow mb-4">
                <div class="card-header"><h6 class="m-0 font-weight-bold text-primary">Thông tin salon</h6></div>
                <div class="card-body">
                    <?php
                    $field('salon_name', 'Tên salon', $values, $errors);
                    $field('salon_slogan', 'Khẩu hiệu', $values, $errors);
                    $field('salon_address', 'Địa chỉ', $values, $errors);
                    $field('salon_phone', 'Số điện thoại (hotline)', $values, $errors, 'tel', 'Số di động Việt Nam 10 số. ' . Validator::PHONE_FORMAT_HINT);
                    $field('salon_email', 'Email liên hệ', $values, $errors, 'email');
                    ?>
                </div>
            </div>
            <div class="card shadow mb-4">
                <div class="card-header"><h6 class="m-0 font-weight-bold text-primary">Mạng xã hội</h6></div>
                <div class="card-body">
                    <?php
                    $field('facebook_url', 'Facebook', $values, $errors, 'url', 'Để trống để ẩn biểu tượng trên website.');
                    $field('instagram_url', 'Instagram', $values, $errors, 'url');
                    $field('tiktok_url', 'TikTok', $values, $errors, 'url');
                    ?>
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card shadow mb-4">
                <div class="card-header"><h6 class="m-0 font-weight-bold text-primary">Giờ mở cửa & quy tắc đặt lịch</h6></div>
                <div class="card-body">
                    <div class="form-row">
                        <div class="col-md-6"><?php $field('open_time', 'Giờ mở cửa', $values, $errors, 'time'); ?></div>
                        <div class="col-md-6"><?php $field('close_time', 'Giờ đóng cửa', $values, $errors, 'time'); ?></div>
                    </div>
                    <div class="form-group">
                        <label for="slot_step">Bước thời gian giữa các khung giờ</label>
                        <select id="slot_step" name="slot_step" class="custom-select<?= Form::invalidClass($errors, 'slot_step') ?>">
                            <?php foreach (['15' => '15 phút', '30' => '30 phút', '60' => '60 phút'] as $v => $l): ?>
                                <option value="<?= $v ?>" <?= $values['slot_step'] === $v ? 'selected' : '' ?>><?= $l ?></option>
                            <?php endforeach; ?>
                        </select>
                        <?= Form::error($errors, 'slot_step') ?>
                    </div>
                    <?php
                    $field('booking_days_ahead', 'Cho phép đặt trước tối đa (ngày)', $values, $errors, 'number');
                    $field('min_notice_minutes', 'Đặt tối thiểu trước giờ hẹn (phút)', $values, $errors, 'number', 'Áp dụng cho lịch trong ngày hôm nay.');
                    ?>
                </div>
            </div>
        </div>
    </div>
    <button type="submit" class="btn btn-primary btn-lg mb-4"><i class="fas fa-save"></i> Lưu cài đặt</button>
</form>
