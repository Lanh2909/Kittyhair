<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Paginator;
use App\Core\QueryFilter;

/**
 * Bảng activity_logs (nhật ký hoạt động).
 */
final class ActivityLogModel extends Model
{
    public function insert(string $actorType, ?int $actorId, string $actorName, string $action, string $description, string $ip): void
    {
        $this->db->query(
            'INSERT INTO activity_logs (actor_type, actor_id, actor_name, action, description, ip_address)
             VALUES (?, ?, ?, ?, ?, ?)',
            [$actorType, $actorId, $actorName, $action, $description, $ip]
        );
    }

    public function count(QueryFilter $filter): int
    {
        return (int) $this->db->value('SELECT COUNT(*) FROM activity_logs WHERE ' . $filter->sql(), $filter->params());
    }

    public function page(QueryFilter $filter, Paginator $pager): array
    {
        return $this->db->all('SELECT * FROM activity_logs WHERE ' . $filter->sql() . ' ORDER BY log_id DESC ' . $pager->limitSql(), $filter->params());
    }

    public function countByAdmin(int $adminId): int
    {
        return (int) $this->db->value("SELECT COUNT(*) FROM activity_logs WHERE actor_type = 'admin' AND actor_id = ?", [$adminId]);
    }

    public function recentByAdmin(int $adminId, int $limit): array
    {
        return $this->db->all("SELECT * FROM activity_logs WHERE actor_type = 'admin' AND actor_id = ? ORDER BY log_id DESC LIMIT " . $limit, [$adminId]);
    }
}
