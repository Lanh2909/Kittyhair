<?php

declare(strict_types=1);

/**
 * Tính thông tin phân trang.
 * @return array{total:int,per_page:int,pages:int,page:int,offset:int}
 */
function paginate(int $total, int $perPage, ?int $page = null): array
{
    $page  = $page ?? max(1, (int) ($_GET['page'] ?? 1));
    $pages = max(1, (int) ceil($total / $perPage));
    $page  = min(max(1, $page), $pages);

    return [
        'total'    => $total,
        'per_page' => $perPage,
        'pages'    => $pages,
        'page'     => $page,
        'offset'   => ($page - 1) * $perPage,
    ];
}

/** Escape ký tự đại diện của LIKE rồi bọc %...%. */
function like_param(string $keyword): string
{
    return '%' . addcslashes($keyword, '\\%_') . '%';
}

/** Dựng query string giữ nguyên bộ lọc hiện tại, thay đổi page. */
function page_url(int $page, array $keep = []): string
{
    $query = array_filter($keep, static function ($v) {
        return $v !== '' && $v !== null;
    });
    $query['page'] = $page;
    return '?' . http_build_query($query);
}

/** Dòng tóm tắt "Hiển thị 1–10 trên 35 bản ghi". */
function render_pager_summary(array $pager): string
{
    if ($pager['total'] === 0) {
        return '';
    }
    $from = $pager['offset'] + 1;
    $to   = min($pager['offset'] + $pager['per_page'], $pager['total']);
    return '<div class="pager-summary text-muted small">Hiển thị ' . $from . '–' . $to . ' trên ' . $pager['total'] . ' bản ghi</div>';
}

/** Thanh phân trang Bootstrap 4 (có cửa sổ trang, dấu …). */
function render_pagination(array $pager, array $keep = []): string
{
    if ($pager['pages'] <= 1) {
        return '';
    }
    $current = $pager['page'];
    $last    = $pager['pages'];

    $numbers = [1, $last];
    for ($i = $current - 2; $i <= $current + 2; $i++) {
        if ($i > 1 && $i < $last) {
            $numbers[] = $i;
        }
    }
    $numbers = array_unique($numbers);
    sort($numbers);

    $item = static function (string $label, int $page, bool $active = false, bool $disabled = false) use ($keep): string {
        $class = 'page-item' . ($active ? ' active' : '') . ($disabled ? ' disabled' : '');
        $href  = $disabled || $active ? '#' : e(page_url($page, $keep));
        return '<li class="' . $class . '"><a class="page-link" href="' . $href . '">' . $label . '</a></li>';
    };

    $html = '<nav aria-label="Phân trang"><ul class="pagination pagination-sm justify-content-center mb-0">';
    $html .= $item('&laquo;', $current - 1, false, $current === 1);
    $prev = 0;
    foreach ($numbers as $n) {
        if ($prev && $n - $prev > 1) {
            $html .= '<li class="page-item disabled"><span class="page-link">…</span></li>';
        }
        $html .= $item((string) $n, $n, $n === $current);
        $prev = $n;
    }
    $html .= $item('&raquo;', $current + 1, false, $current === $last);
    return $html . '</ul></nav>';
}

/** Ô tìm kiếm GET dùng chung. $hidden: các tham số cần giữ (vd: status). */
function render_search_form(string $keyword, string $placeholder, array $hidden = []): string
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
