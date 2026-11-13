<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/**
 * Lớp cha của mọi Model. Mỗi Model phụ trách câu lệnh SQL của 1 bảng (hoặc 1 nhóm bảng liên quan),
 * nhận đối tượng Database qua constructor. Controller / Service không viết SQL trực tiếp.
 *
 * Đây là ví dụ về kế thừa (inheritance): các Model con dùng lại thuộc tính $db mà không phải khai báo lại.
 */
abstract class Model
{
    protected Database $db;

    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    /** Tạo chuỗi "?, ?, ?" cho câu IN (...) với $count tham số. */
    protected static function placeholders(int $count): string
    {
        return implode(',', array_fill(0, $count, '?'));
    }
}
