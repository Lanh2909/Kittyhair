<?php

declare(strict_types=1);

namespace App\Models;

/**
 * Bảng employees_schedule: giờ làm việc hằng tuần, day_id theo ISO-8601 (1 = Thứ Hai ... 7 = Chủ Nhật).
 */
final class ScheduleModel extends Model
{
    public function forEmployee(int $employeeId): array
    {
        return $this->db->all('SELECT day_id, from_hour, to_hour FROM employees_schedule WHERE employee_id = ?', [$employeeId]);
    }

    /** Giờ làm của nhân viên trong 1 thứ; null = ngày nghỉ. */
    public function forDay(int $employeeId, int $dayId): ?array
    {
        return $this->db->row('SELECT from_hour, to_hour FROM employees_schedule WHERE employee_id = ? AND day_id = ?', [$employeeId, $dayId]);
    }

    public function deleteForEmployee(int $employeeId): void
    {
        $this->db->query('DELETE FROM employees_schedule WHERE employee_id = ?', [$employeeId]);
    }

    public function insert(int $employeeId, int $dayId, string $from, string $to): void
    {
        $this->db->query('INSERT INTO employees_schedule (employee_id, day_id, from_hour, to_hour) VALUES (?, ?, ?, ?)', [$employeeId, $dayId, $from, $to]);
    }
}
