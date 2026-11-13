<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\App;
use App\Core\Paginator;
use App\Core\QueryFilter;
use App\Helpers\IpAddress;
use App\Models\ActivityLogModel;

/**
 * Nhật ký hoạt động (admin/activity-log.php): lọc theo người thực hiện, tìm theo nội dung / hành động / IP.
 */
final class ActivityLogController extends AdminController
{
    protected ?string $permission = 'activity';

    private const ACTORS = ['all' => 'Tất cả', 'admin' => 'Quản trị viên', 'guest' => 'Khách đặt lịch'];
    private const ACTOR_BADGES = ['admin' => 'badge-primary', 'guest' => 'badge-kitty'];
    private const PER_PAGE = 15;

    public function handle(): void
    {
        $logs    = new ActivityLogModel(App::db());
        $actor   = array_key_exists($this->request->get('actor'), self::ACTORS) ? $this->request->get('actor') : 'all';
        $keyword = $this->keyword();

        $filter = new QueryFilter();
        if ($actor !== 'all') {
            $filter->where('actor_type = ?', [$actor]);
        }
        if ($keyword !== '') {
            $this->addKeywordCondition($filter, $keyword);
        }
        $pager = new Paginator($logs->count($filter), self::PER_PAGE);

        $this->renderAdmin('admin/activity-log', 'Nhật ký hoạt động', 'activity', [
            'actors'     => self::ACTORS,
            'actor'      => $actor,
            'keyword'    => $keyword,
            'rows'       => $logs->page($filter, $pager),
            'pager'      => $pager,
            'actorBadge' => self::ACTOR_BADGES,
        ]);
    }

    /**
     * IP được chuẩn hóa khi lưu, nhưng bản ghi cũ có thể còn dạng "::1" hoặc "::ffff:a.b.c.d".
     * Tìm theo cả dạng người dùng gõ lẫn dạng đã chuẩn hóa; "localhost"/"::1" khớp mọi IP cục bộ.
     */
    private function addKeywordCondition(QueryFilter $filter, string $keyword): void
    {
        $isLocalKeyword = in_array(mb_strtolower($keyword), ['::1', 'localhost'], true);
        $ipNormalized   = $isLocalKeyword ? '127.0.0.1' : (IpAddress::normalize($keyword) ?: $keyword);
        $ipClauses      = ['ip_address LIKE ?', 'ip_address LIKE ?'];
        if ($isLocalKeyword) {
            $ipClauses[] = "ip_address = '::1'";
        }
        $like = QueryFilter::like($keyword);
        $filter->where(
            '(description LIKE ? OR actor_name LIKE ? OR action LIKE ? OR ' . implode(' OR ', $ipClauses) . ')',
            [$like, $like, $like, $like, QueryFilter::like($ipNormalized)]
        );
    }
}
