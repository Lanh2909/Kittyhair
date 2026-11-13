<?php

declare(strict_types=1);

namespace App\Models;

/**
 * Số liệu tổng hợp cho bảng điều khiển (đọc từ nhiều bảng trong 1 truy vấn).
 */
final class DashboardModel extends Model
{
    /** @return array{clients:int,services:int,employees:int,upcoming:int,today:int,expected_revenue:mixed} */
    public function stats(): array
    {
        return (array) $this->db->row(
            "SELECT
                (SELECT COUNT(*) FROM clients) AS clients,
                (SELECT COUNT(*) FROM services) AS services,
                (SELECT COUNT(*) FROM employees) AS employees,
                (SELECT COUNT(*) FROM appointments WHERE canceled = 0 AND start_time >= NOW()) AS upcoming,
                (SELECT COUNT(*) FROM appointments WHERE canceled = 0 AND DATE(start_time) = CURDATE()) AS today,
                (SELECT COALESCE(SUM(sb.price), 0) FROM services_booked sb JOIN appointments a ON a.appointment_id = sb.appointment_id
                    WHERE a.canceled = 0 AND a.start_time >= NOW()) AS expected_revenue"
        );
    }
}
