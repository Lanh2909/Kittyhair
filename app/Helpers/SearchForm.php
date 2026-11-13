<?php

declare(strict_types=1);

namespace App\Helpers;

/**
 * Ô tìm kiếm GET dùng chung cho các trang danh sách (thay cho render_search_form()).
 */
final class SearchForm
{
    /** @param array $hidden các tham số lọc cần giữ khi tìm (vd: ['status' => 'upcoming']) */
    public static function render(string $keyword, string $placeholder, array $hidden = []): string
    {
        $html = '<form method="get" class="search-form form-inline mb-3" role="search">';
        foreach ($hidden as $name => $value) {
            if ($value !== '') {
                $html .= '<input type="hidden" name="' . e($name) . '" value="' . e($value) . '">';
            }
        }
        $html .= '<div class="input-group search-box">'
            . '<input type="search" name="q" class="form-control" value="' . e($keyword) . '" placeholder="' . e($placeholder) . '" maxlength="100">'
            . '<div class="input-group-append"><button class="btn btn-primary" type="submit"><i class="fas fa-search"></i> Tìm</button>';
        if ($keyword !== '') {
            $reset = $hidden ? '?' . http_build_query(array_filter($hidden)) : '?';
            $html .= '<a class="btn btn-outline-secondary" href="' . e($reset) . '">Xóa lọc</a>';
        }
        return $html . '</div></div></form>';
    }
}
