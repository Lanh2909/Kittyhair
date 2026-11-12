<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Paginator;
use App\Core\QueryFilter;

/**
 * Bảng contact_messages (tin nhắn khách gửi từ form Liên hệ).
 */
final class ContactMessageModel extends Model
{
    public function create(string $name, string $email, string $subject, string $message): void
    {
        $this->db->query(
            'INSERT INTO contact_messages (name, email, subject, message) VALUES (?, ?, ?, ?)',
            [$name, $email, $subject, $message]
        );
    }

    /** Đảo trạng thái đã đọc / chưa đọc. */
    public function toggleRead(int $messageId): void
    {
        $this->db->query('UPDATE contact_messages SET is_read = 1 - is_read WHERE message_id = ?', [$messageId]);
    }

    public function delete(int $messageId): void
    {
        $this->db->query('DELETE FROM contact_messages WHERE message_id = ?', [$messageId]);
    }

    /** Số tin chưa đọc (badge trên menu quản trị). */
    public function countUnread(): int
    {
        return (int) $this->db->value('SELECT COUNT(*) FROM contact_messages WHERE is_read = 0');
    }

    public function count(QueryFilter $filter): int
    {
        return (int) $this->db->value('SELECT COUNT(*) FROM contact_messages WHERE ' . $filter->sql(), $filter->params());
    }

    public function page(QueryFilter $filter, Paginator $pager): array
    {
        return $this->db->all(
            'SELECT * FROM contact_messages WHERE ' . $filter->sql() . ' ORDER BY created_at DESC, message_id DESC ' . $pager->limitSql(),
            $filter->params()
        );
    }
}
