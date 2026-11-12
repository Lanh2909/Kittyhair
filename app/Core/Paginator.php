<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Phân trang dùng chung (thay cho paginate(), render_pagination(), render_pager_summary(), page_url()).
 *
 * Công thức: pages = ceil(total / perPage); offset = (page - 1) * perPage
 * -> câu SQL lấy trang hiện tại: ... LIMIT perPage OFFSET offset
 */
final class Paginator
{
    public int $total;
    public int $perPage;
    public int $pages;
    public int $page;
    public int $offset;

    /** $page = null: lấy từ tham số ?page= trên URL. Số trang ngoài khoảng [1, pages] được kéo về biên. */
    public function __construct(int $total, int $perPage, ?int $page = null)
    {
        $page = $page ?? max(1, (int) ($_GET['page'] ?? 1));

        $this->total   = $total;
        $this->perPage = $perPage;
        $this->pages   = max(1, (int) ceil($total / $perPage));
        $this->page    = min(max(1, $page), $this->pages);
        $this->offset  = ($this->page - 1) * $perPage;
    }

    /** Mệnh đề SQL cho trang hiện tại (2 số nguyên đã được ép kiểu nên an toàn khi nối chuỗi). */
    public function limitSql(): string
    {
        return 'LIMIT ' . $this->perPage . ' OFFSET ' . $this->offset;
    }

    /** Dòng tóm tắt "Hiển thị 1–10 trên 35 bản ghi". */
    public function summary(): string
    {
        if ($this->total === 0) {
            return '';
        }
        $from = $this->offset + 1;
        $to   = min($this->offset + $this->perPage, $this->total);
        return '<div class="pager-summary text-muted small">Hiển thị ' . $from . '–' . $to . ' trên ' . $this->total . ' bản ghi</div>';
    }

    /**
     * Thanh phân trang Bootstrap 4 (trang đầu, cuối, ±2 trang quanh trang hiện tại, dấu …).
     * @param array $keep các tham số lọc cần giữ trên link (vd: ['q' => 'nhuộm', 'status' => 'all'])
     */
    public function links(array $keep = []): string
    {
        if ($this->pages <= 1) {
            return '';
        }
        $current = $this->page;
        $last    = $this->pages;

        $numbers = [1, $last];
        for ($i = $current - 2; $i <= $current + 2; $i++) {
            if ($i > 1 && $i < $last) {
                $numbers[] = $i;
            }
        }
        $numbers = array_unique($numbers);
        sort($numbers);

        $html = '<nav aria-label="Phân trang"><ul class="pagination pagination-sm justify-content-center mb-0">';
        $html .= $this->item('&laquo;', $current - 1, $keep, false, $current === 1);
        $prev = 0;
        foreach ($numbers as $n) {
            if ($prev && $n - $prev > 1) {
                $html .= '<li class="page-item disabled"><span class="page-link">…</span></li>';
            }
            $html .= $this->item((string) $n, $n, $keep, $n === $current);
            $prev = $n;
        }
        $html .= $this->item('&raquo;', $current + 1, $keep, false, $current === $last);
        return $html . '</ul></nav>';
    }

    private function item(string $label, int $page, array $keep, bool $active = false, bool $disabled = false): string
    {
        $class = 'page-item' . ($active ? ' active' : '') . ($disabled ? ' disabled' : '');
        $href  = $disabled || $active ? '#' : e(self::pageUrl($page, $keep));
        return '<li class="' . $class . '"><a class="page-link" href="' . $href . '">' . $label . '</a></li>';
    }

    /** Query string giữ nguyên bộ lọc hiện tại, chỉ đổi page. */
    public static function pageUrl(int $page, array $keep = []): string
    {
        $query = array_filter($keep, static function ($v) {
            return $v !== '' && $v !== null;
        });
        $query['page'] = $page;
        return '?' . http_build_query($query);
    }
}
