<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Paginator;
use App\Core\QueryFilter;
use InvalidArgumentException;

/**
 * Bảng appointments + services_booked (lịch hẹn và các dịch vụ trong lịch hẹn).
 */
final class AppointmentModel extends Model
{
    /** Câu FROM chung cho các danh sách quản trị (kèm tên khách, stylist). */
    private const ADMIN_FROM = 'FROM appointments a JOIN clients c ON c.client_id = a.client_id JOIN employees e ON e.employee_id = a.employee_id';

    public function find(int $appointmentId): ?array
    {
        return $this->db->row('SELECT * FROM appointments WHERE appointment_id = ?', [$appointmentId]);
    }

    /** Lịch hẹn kèm thông tin khách và stylist (trang xác nhận đặt lịch). */
    public function findWithDetails(int $appointmentId): ?array
    {
        return $this->db->row(
            'SELECT a.*, c.full_name AS client_name, c.phone_number, c.email AS client_email, e.full_name AS employee_name
             FROM appointments a
             JOIN clients c ON c.client_id = a.client_id
             JOIN employees e ON e.employee_id = a.employee_id
             WHERE a.appointment_id = ?',
            [$appointmentId]
        );
    }

    /**
     * Giờ bắt đầu/kết thúc các lịch CHƯA HỦY của 1 nhân viên hoặc 1 khách trong 1 ngày.
     * @param string $column employee_id | client_id (chỉ nhận 2 giá trị này vì tên cột không thể dùng dấu ?)
     */
    public function activeTimesOnDate(string $column, int $id, string $date): array
    {
        if (!in_array($column, ['employee_id', 'client_id'], true)) {
            throw new InvalidArgumentException('Cột không hợp lệ.');
        }
        return $this->db->all(
            "SELECT start_time, end_time_expected FROM appointments
             WHERE $column = ? AND canceled = 0 AND DATE(start_time) = ?",
            [$id, $date]
        );
    }

    public function create(int $clientId, int $employeeId, string $start, string $end, ?string $note, ?string $referenceImage): int
    {
        $this->db->query(
            'INSERT INTO appointments (client_id, employee_id, start_time, end_time_expected, note, reference_image)
             VALUES (?, ?, ?, ?, ?, ?)',
            [$clientId, $employeeId, $start, $end, $note, $referenceImage]
        );
        return $this->db->lastInsertId();
    }

    /** Lưu BẢN CHỤP tên/giá/thời lượng dịch vụ tại thời điểm đặt (sửa dịch vụ sau này không làm sai lịch cũ). */
    public function addBookedService(int $appointmentId, array $service): void
    {
        $this->db->query(
            'INSERT INTO services_booked (appointment_id, service_id, service_name, price, duration) VALUES (?, ?, ?, ?, ?)',
            [$appointmentId, $service['service_id'], $service['service_name'], $service['service_price'], $service['service_duration']]
        );
    }

    /** Dịch vụ đã đặt của nhiều lịch hẹn, gom theo appointment_id. */
    public function bookedServicesFor(array $appointmentIds): array
    {
        if (!$appointmentIds) {
            return [];
        }
        $byAppointment = [];
        $rows = $this->db->all(
            'SELECT * FROM services_booked WHERE appointment_id IN (' . self::placeholders(count($appointmentIds)) . ') ORDER BY id',
            array_values($appointmentIds)
        );
        foreach ($rows as $s) {
            $byAppointment[$s['appointment_id']][] = $s;
        }
        return $byAppointment;
    }

    /** Đánh dấu đã hủy (điều kiện canceled = 0 để không hủy 2 lần). */
    public function markCanceled(int $appointmentId, string $requestedBy, ?string $reason): void
    {
        $this->db->query(
            'UPDATE appointments SET canceled = 1, canceled_by = ?, cancellation_reason = ? WHERE appointment_id = ? AND canceled = 0',
            [$requestedBy, $reason, $appointmentId]
        );
    }

    public function clearReferenceImage(int $appointmentId): void
    {
        $this->db->query('UPDATE appointments SET reference_image = NULL WHERE appointment_id = ?', [$appointmentId]);
    }

    /* ----------------------- Dùng cho nhân viên / giờ làm ----------------------- */

    /** Các lịch sắp tới chưa hủy của 1 nhân viên. */
    public function upcomingForEmployee(int $employeeId): array
    {
        return $this->db->all(
            'SELECT appointment_id, start_time, end_time_expected FROM appointments
             WHERE employee_id = ? AND canceled = 0 AND start_time >= NOW()',
            [$employeeId]
        );
    }

    public function countUpcomingForEmployee(int $employeeId): int
    {
        return (int) $this->db->value('SELECT COUNT(*) FROM appointments WHERE employee_id = ? AND canceled = 0 AND start_time >= NOW()', [$employeeId]);
    }

    public function countForEmployee(int $employeeId): int
    {
        return (int) $this->db->value('SELECT COUNT(*) FROM appointments WHERE employee_id = ?', [$employeeId]);
    }

    /* ------------------------------ Trang quản trị ------------------------------ */

    /** Lịch hẹn sắp tới gần nhất (bảng điều khiển). */
    public function upcoming(int $limit): array
    {
        return $this->db->all(
            'SELECT a.*, c.full_name AS client_name, c.phone_number, e.full_name AS employee_name
             ' . self::ADMIN_FROM . '
             WHERE a.canceled = 0 AND a.start_time >= NOW()
             ORDER BY a.start_time LIMIT ' . $limit
        );
    }

    public function countForAdmin(QueryFilter $filter): int
    {
        return (int) $this->db->value('SELECT COUNT(*) ' . self::ADMIN_FROM . ' WHERE ' . $filter->sql(), $filter->params());
    }

    /** @param string $order ASC | DESC theo giờ bắt đầu */
    public function pageForAdmin(QueryFilter $filter, Paginator $pager, string $order): array
    {
        $order = $order === 'ASC' ? 'ASC' : 'DESC';
        return $this->db->all(
            'SELECT a.*, c.full_name AS client_name, c.phone_number, c.email AS client_email, e.full_name AS employee_name
             ' . self::ADMIN_FROM . ' WHERE ' . $filter->sql() . ' ORDER BY a.start_time ' . $order . ' ' . $pager->limitSql(),
            $filter->params()
        );
    }
}
