<?php

declare(strict_types=1);

namespace App\Helpers;

/**
 * Hiển thị thông tin lịch hẹn trong bảng quản trị
 */
final class AppointmentHtml
{
    /** Badge trạng thái: Đã hủy / Đã hoàn thành / Sắp tới. */
    public static function statusBadge(array $row): string
    {
        if ((int) $row['canceled'] === 1) {
            return '<span class="badge badge-secondary">Đã hủy</span>';
        }
        if (strtotime($row['start_time']) < time()) {
            return '<span class="badge badge-success">Đã hoàn thành</span>';
        }
        return '<span class="badge badge-kitty">Sắp tới</span>';
    }

    /** Danh sách dịch vụ rút gọn "A + B" (dòng đã được gắn 'services' bởi BookingService::attachServices()). */
    public static function servicesText(array $row): string
    {
        return implode(' + ', array_map(static function (array $s): string {
            return e($s['service_name']);
        }, $row['services']));
    }
}
