<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Paginator;
use App\Core\QueryFilter;

/**
 * Bảng services (dịch vụ: tên, mô tả, giá, thời lượng, ảnh).
 */
final class ServiceModel extends Model
{
    /** Tất cả dịch vụ. @param string $orderBy service_price | service_name */
    public function all(string $orderBy = 'service_name'): array
    {
        $orderBy = $orderBy === 'service_price' ? 'service_price' : 'service_name';
        return $this->db->all('SELECT * FROM services ORDER BY ' . $orderBy);
    }

    /** Dịch vụ gom theo category_id: [category_id => [dịch vụ, ...]]. */
    public function groupedByCategory(string $orderBy = 'service_name'): array
    {
        $grouped = [];
        foreach ($this->all($orderBy) as $service) {
            $grouped[$service['category_id']][] = $service;
        }
        return $grouped;
    }

    /** @param int[] $ids */
    public function findMany(array $ids): array
    {
        if (!$ids) {
            return [];
        }
        return $this->db->all('SELECT * FROM services WHERE service_id IN (' . self::placeholders(count($ids)) . ') ORDER BY service_id', $ids);
    }

    public function find(int $serviceId): ?array
    {
        return $this->db->row('SELECT * FROM services WHERE service_id = ?', [$serviceId]);
    }

    public function nameTaken(string $name, int $exceptId): bool
    {
        return $this->db->value('SELECT COUNT(*) FROM services WHERE service_name = ? AND service_id <> ?', [$name, $exceptId]) > 0;
    }

    /** @param array $data category_id, service_name, service_description, service_price, service_duration */
    public function create(array $data, ?string $image): void
    {
        $this->db->query(
            'INSERT INTO services (category_id, service_name, service_description, service_price, service_duration, image) VALUES (?, ?, ?, ?, ?, ?)',
            [$data['category_id'], $data['service_name'], $data['service_description'], $data['service_price'], $data['service_duration'], $image]
        );
    }

    public function update(int $serviceId, array $data, ?string $image): void
    {
        $this->db->query(
            'UPDATE services SET category_id = ?, service_name = ?, service_description = ?, service_price = ?, service_duration = ?, image = ? WHERE service_id = ?',
            [$data['category_id'], $data['service_name'], $data['service_description'], $data['service_price'], $data['service_duration'], $image, $serviceId]
        );
    }

    public function delete(int $serviceId): void
    {
        $this->db->query('DELETE FROM services WHERE service_id = ?', [$serviceId]);
    }

    /** Chuyển toàn bộ dịch vụ của 1 danh mục sang danh mục khác; trả về số dịch vụ đã chuyển. */
    public function moveCategory(int $fromCategoryId, int $toCategoryId): int
    {
        return $this->db->query('UPDATE services SET category_id = ? WHERE category_id = ?', [$toCategoryId, $fromCategoryId])->rowCount();
    }

    public function count(QueryFilter $filter): int
    {
        return (int) $this->db->value('SELECT COUNT(*) FROM services s WHERE ' . $filter->sql(), $filter->params());
    }

    /**
     * 1 trang dịch vụ kèm tên danh mục.
     * @param string $thenBy cột sắp xếp sau danh mục: service_price (trang khách) | service_name (trang quản trị)
     */
    public function page(QueryFilter $filter, Paginator $pager, string $thenBy = 'service_name'): array
    {
        $thenBy = $thenBy === 'service_price' ? 's.service_price' : 's.service_name';
        return $this->db->all(
            'SELECT s.*, c.category_name FROM services s JOIN service_categories c ON c.category_id = s.category_id
             WHERE ' . $filter->sql() . ' ORDER BY c.is_default, c.category_name, ' . $thenBy . ' ' . $pager->limitSql(),
            $filter->params()
        );
    }
}
