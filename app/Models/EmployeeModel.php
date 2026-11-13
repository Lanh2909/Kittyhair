<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Paginator;
use App\Core\QueryFilter;

/**
 * Bảng employees (stylist).
 */
final class EmployeeModel extends Model
{
    public function all(): array
    {
        return $this->db->all('SELECT * FROM employees ORDER BY employee_id');
    }

    public function find(int $employeeId): ?array
    {
        return $this->db->row('SELECT * FROM employees WHERE employee_id = ?', [$employeeId]);
    }

    public function exists(int $employeeId): bool
    {
        return $this->db->value('SELECT COUNT(*) FROM employees WHERE employee_id = ?', [$employeeId]) >= 1;
    }

    /** Khóa dòng nhân viên trong transaction đặt lịch -> 2 lượt đặt cùng stylist phải xếp hàng. */
    public function findForUpdate(int $employeeId): ?array
    {
        return $this->db->row('SELECT employee_id, full_name FROM employees WHERE employee_id = ? FOR UPDATE', [$employeeId]);
    }

    public function phoneTaken(string $phone, int $exceptId): bool
    {
        return $this->db->value('SELECT COUNT(*) FROM employees WHERE phone_number = ? AND employee_id <> ?', [$phone, $exceptId]) > 0;
    }

    public function emailTaken(string $email, int $exceptId): bool
    {
        return $this->db->value('SELECT COUNT(*) FROM employees WHERE email = ? AND employee_id <> ?', [$email, $exceptId]) > 0;
    }

    public function create(string $fullName, string $position, string $phone, string $email, ?string $avatar): int
    {
        $this->db->query(
            'INSERT INTO employees (full_name, position, phone_number, email, avatar) VALUES (?, ?, ?, ?, ?)',
            [$fullName, $position, $phone, $email, $avatar]
        );
        return $this->db->lastInsertId();
    }

    public function update(int $employeeId, string $fullName, string $position, string $phone, string $email, ?string $avatar): void
    {
        $this->db->query(
            'UPDATE employees SET full_name = ?, position = ?, phone_number = ?, email = ?, avatar = ? WHERE employee_id = ?',
            [$fullName, $position, $phone, $email, $avatar, $employeeId]
        );
    }

    public function delete(int $employeeId): void
    {
        $this->db->query('DELETE FROM employees WHERE employee_id = ?', [$employeeId]);
    }

    public function count(QueryFilter $filter): int
    {
        return (int) $this->db->value('SELECT COUNT(*) FROM employees e WHERE ' . $filter->sql(), $filter->params());
    }

    /** 1 trang danh sách nhân viên kèm số lịch hẹn sắp tới. */
    public function page(QueryFilter $filter, Paginator $pager): array
    {
        return $this->db->all(
            'SELECT e.*, (SELECT COUNT(*) FROM appointments a WHERE a.employee_id = e.employee_id AND a.canceled = 0 AND a.start_time >= NOW()) AS upcoming
             FROM employees e WHERE ' . $filter->sql() . ' ORDER BY e.full_name ' . $pager->limitSql(),
            $filter->params()
        );
    }
}
