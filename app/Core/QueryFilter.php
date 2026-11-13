<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Bộ dựng điều kiện WHERE + tham số cho các trang danh sách có tìm kiếm / lọc.
 *
 *   $filter = (new QueryFilter())
 *       ->whereLikeAny(['c.full_name', 'c.email'], $keyword)
 *       ->where('a.client_id = ?', [$clientId]);
 *   "... WHERE " . $filter->sql()   // 1 = 1 AND (c.full_name LIKE ? OR c.email LIKE ?) AND a.client_id = ?
 *   $filter->params()                // ['%từ khóa%', '%từ khóa%', 5]
 *
 * Giá trị người dùng nhập KHÔNG bao giờ được nối vào SQL, luôn đi qua dấu ? (prepared statement).
 */
final class QueryFilter
{
    /** @var string[] */
    private array $conditions = ['1 = 1'];
    private array $params = [];

    /** Thêm 1 điều kiện (nối với các điều kiện khác bằng AND). */
    public function where(string $condition, array $params = []): self
    {
        $this->conditions[] = $condition;
        foreach ($params as $param) {
            $this->params[] = $param;
        }
        return $this;
    }

    /** (cột1 LIKE ? OR cột2 LIKE ? ...) với cùng một từ khóa; bỏ qua nếu từ khóa rỗng. */
    public function whereLikeAny(array $columns, string $keyword): self
    {
        if ($keyword === '') {
            return $this;
        }
        $parts = array_map(static function (string $column): string {
            return $column . ' LIKE ?';
        }, $columns);
        return $this->where('(' . implode(' OR ', $parts) . ')', array_fill(0, count($columns), self::like($keyword)));
    }

    public function sql(): string
    {
        return implode(' AND ', $this->conditions);
    }

    public function params(): array
    {
        return $this->params;
    }

    /**
     * Escape ký tự đại diện của LIKE (\ % _) rồi bọc %...%.
     * Không escape thì từ khóa "50%" sẽ khớp mọi chuỗi bắt đầu bằng "50", "_" khớp 1 ký tự bất kỳ.
     */
    public static function like(string $keyword): string
    {
        return '%' . addcslashes($keyword, '\\%_') . '%';
    }
}
