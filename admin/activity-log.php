<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/init.php';
require_permission('activity');

$actors  = ['all' => 'Tất cả', 'admin' => 'Quản trị viên', 'guest' => 'Khách đặt lịch'];
$actor   = array_key_exists(get('actor'), $actors) ? get('actor') : 'all';
$keyword = mb_substr(get('q'), 0, 100);

$where  = ['1 = 1'];
$params = [];
if ($actor !== 'all') {
    $where[]  = 'actor_type = ?';
    $params[] = $actor;
}
if ($keyword !== '') {
    // IP được chuẩn hóa khi lưu, nhưng bản ghi cũ có thể còn dạng "::1" hoặc "::ffff:a.b.c.d".
    // Vì vậy tìm theo cả dạng người dùng gõ lẫn dạng đã chuẩn hóa; "localhost"/"::1" khớp mọi IP cục bộ.
    $isLocalKeyword = in_array(mb_strtolower($keyword), ['::1', 'localhost'], true);
    $ipNormalized   = $isLocalKeyword ? '127.0.0.1' : (normalize_ip($keyword) ?: $keyword);
    $ipClauses      = ['ip_address LIKE ?', 'ip_address LIKE ?'];
    $ipParams       = [like_param($keyword), like_param($ipNormalized)];
    if ($isLocalKeyword) {
        $ipClauses[] = "ip_address = '::1'";
    }
    $where[] = '(description LIKE ? OR actor_name LIKE ? OR action LIKE ? OR ' . implode(' OR ', $ipClauses) . ')';
    array_push($params, like_param($keyword), like_param($keyword), like_param($keyword), ...$ipParams);
}
$whereSql = implode(' AND ', $where);

$total = (int) db_value("SELECT COUNT(*) FROM activity_logs WHERE $whereSql", $params);
$pager = paginate($total, 15);
$rows  = db_all("SELECT * FROM activity_logs WHERE $whereSql ORDER BY log_id DESC LIMIT {$pager['per_page']} OFFSET {$pager['offset']}", $params);

$actorBadge = ['admin' => 'badge-primary', 'guest' => 'badge-kitty'];

$pageTitle  = 'Nhật ký hoạt động';
$activeMenu = 'activity';
require __DIR__ . '/templates/header.php';
?>
<h1 class="h3 mb-3 text-gray-800">Nhật ký hoạt động</h1>
<div class="card shadow mb-4">
    <div class="card-body">
        <div class="filter-pills">
            <?php foreach ($actors as $key => $label): ?>
                <a href="<?= e('?' . http_build_query(['actor' => $key, 'q' => $keyword])) ?>" class="<?= $actor === $key ? 'active' : '' ?>"><?= e($label) ?></a>
            <?php endforeach; ?>
        </div>
        <?= render_search_form($keyword, 'Tìm theo nội dung, người thực hiện, hành động hoặc IP (vd: 127.0.0.1, localhost)…', ['actor' => $actor === 'all' ? '' : $actor]) ?>
        <div class="table-responsive">
            <table class="table table-hover">
                <thead><tr><th>Thời gian</th><th>Người thực hiện</th><th>Hành động</th><th>Nội dung</th><th>IP</th></tr></thead>
                <tbody>
                <?php if (!$rows): ?><tr><td colspan="5" class="text-center text-muted py-4">Chưa có hoạt động nào.</td></tr><?php endif; ?>
                <?php foreach ($rows as $row): ?>
                    <tr>
                        <td class="text-nowrap"><?= e(format_datetime($row['created_at'])) ?></td>
                        <td><span class="badge <?= e($actorBadge[$row['actor_type']] ?? 'badge-secondary') ?>"><?= e($actors[$row['actor_type']] ?? $row['actor_type']) ?></span> <?= e($row['actor_name']) ?></td>
                        <td><code><?= e($row['action']) ?></code></td>
                        <td><?= e($row['description']) ?></td>
                        <td class="small"><?= format_ip_html((string) $row['ip_address']) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="d-flex justify-content-between align-items-center flex-wrap">
            <?= render_pager_summary($pager) ?>
            <?= render_pagination($pager, ['q' => $keyword, 'actor' => $actor === 'all' ? '' : $actor]) ?>
        </div>
    </div>
</div>
<?php require __DIR__ . '/templates/footer.php'; ?>
