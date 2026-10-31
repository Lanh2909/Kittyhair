<?php

declare(strict_types=1);

/**
 * Khởi tạo chung cho mọi trang quản trị (trừ login/logout):
 * nạp bootstrap, bắt buộc đăng nhập admin, nạp helper form.
 */

require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../templates/form_helpers.php';
require_once __DIR__ . '/permissions.php';

$admin = require_admin();

/** Badge trạng thái lịch hẹn cho bảng quản trị. */
function appointment_status_badge(array $row): string
{
    if ((int) $row['canceled'] === 1) {
        return '<span class="badge badge-secondary">Đã hủy</span>';
    }
    if (strtotime($row['start_time']) < time()) {
        return '<span class="badge badge-success">Đã hoàn thành</span>';
    }
    return '<span class="badge badge-kitty">Sắp tới</span>';
}

/** Danh sách dịch vụ rút gọn "A + B" của 1 lịch hẹn (đã gắn bằng appointments_attach_services). */
function appointment_services_text(array $row): string
{
    return implode(' + ', array_map(static function (array $s): string {
        return e($s['service_name']);
    }, $row['services']));
}
