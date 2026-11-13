<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Paginator;
use App\Core\QueryFilter;

/**
 * Bảng clients (khách không có tài khoản, nhận diện theo email).
 */
final class ClientModel extends Model
{
    /** Tìm khách theo email và KHÓA dòng (SELECT ... FOR UPDATE) – chỉ gọi trong transaction đặt lịch. */
    public function findIdByEmailForUpdate(string $email): ?int
    {
        $row = $this->db->row('SELECT client_id FROM clients WHERE email = ? FOR UPDATE', [$email]);
        return $row === null ? null : (int) $row['client_id'];
    }

    public function create(string $fullName, string $phone, string $email): int
    {
        $this->db->query('INSERT INTO clients (full_name, phone_number, email) VALUES (?, ?, ?)', [$fullName, $phone, $email]);
        return $this->db->lastInsertId();
    }

    /** Cập nhật họ tên / SĐT mới nhất khách vừa nhập. */
    public function updateContact(int $clientId, string $fullName, string $phone): void
    {
        $this->db->query('UPDATE clients SET full_name = ?, phone_number = ? WHERE client_id = ?', [$fullName, $phone, $clientId]);
    }

    public function count(QueryFilter $filter): int
    {
        return (int) $this->db->value('SELECT COUNT(*) FROM clients c WHERE ' . $filter->sql(), $filter->params());
    }

    /** 1 trang danh sách khách kèm số lịch hẹn và lần đến gần nhất. */
    public function page(QueryFilter $filter, Paginator $pager): array
    {
        return $this->db->all(
            'SELECT c.*, (SELECT COUNT(*) FROM appointments a WHERE a.client_id = c.client_id) AS appointment_count,
                    (SELECT MAX(a.start_time) FROM appointments a WHERE a.client_id = c.client_id AND a.canceled = 0) AS last_visit
             FROM clients c WHERE ' . $filter->sql() . ' ORDER BY c.created_at DESC, c.client_id DESC ' . $pager->limitSql(),
            $filter->params()
        );
    }
}
