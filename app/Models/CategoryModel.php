<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Paginator;
use App\Core\QueryFilter;

/**
 * Bảng service_categories (danh mục dịch vụ; danh mục is_default = 1 "Chưa phân loại" không được sửa/xóa).
 */
final class CategoryModel extends Model
{
    /** Danh mục có ít nhất 1 dịch vụ (dùng cho trang khách). */
    public function withServices(): array
    {
        return $this->db->all(
            'SELECT c.category_id, c.category_name FROM service_categories c
             WHERE EXISTS (SELECT 1 FROM services s WHERE s.category_id = c.category_id)
             ORDER BY c.is_default, c.category_id'
        );
    }

    /** Mọi danh mục cho ô chọn trong form dịch vụ (mặc định đứng đầu). */
    public function options(): array
    {
        return $this->db->all('SELECT category_id, category_name FROM service_categories ORDER BY is_default DESC, category_name');
    }

    public function find(int $categoryId): ?array
    {
        return $this->db->row('SELECT * FROM service_categories WHERE category_id = ?', [$categoryId]);
    }

    /** Mã danh mục mặc định ("Chưa phân loại"), 0 nếu chưa có. */
    public function defaultId(): int
    {
        return (int) $this->db->value('SELECT category_id FROM service_categories WHERE is_default = 1 LIMIT 1');
    }

    public function nameTaken(string $name, int $exceptId): bool
    {
        return $this->db->value('SELECT COUNT(*) FROM service_categories WHERE category_name = ? AND category_id <> ?', [$name, $exceptId]) > 0;
    }

    public function create(string $name): void
    {
        $this->db->query('INSERT INTO service_categories (category_name) VALUES (?)', [$name]);
    }

    public function rename(int $categoryId, string $name): void
    {
        $this->db->query('UPDATE service_categories SET category_name = ? WHERE category_id = ?', [$name, $categoryId]);
    }

    public function delete(int $categoryId): void
    {
        $this->db->query('DELETE FROM service_categories WHERE category_id = ?', [$categoryId]);
    }

    public function count(QueryFilter $filter): int
    {
        return (int) $this->db->value('SELECT COUNT(*) FROM service_categories c WHERE ' . $filter->sql(), $filter->params());
    }

    /** 1 trang danh mục kèm số dịch vụ. */
    public function page(QueryFilter $filter, Paginator $pager): array
    {
        return $this->db->all(
            'SELECT c.*, (SELECT COUNT(*) FROM services s WHERE s.category_id = c.category_id) AS service_count
             FROM service_categories c WHERE ' . $filter->sql() . ' ORDER BY c.is_default DESC, c.category_name ' . $pager->limitSql(),
            $filter->params()
        );
    }
}
