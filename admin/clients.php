<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/init.php';
require_permission('clients');

$keyword = mb_substr(get('q'), 0, 100);
$where   = ['1 = 1'];
$params  = [];
if ($keyword !== '') {
    $digits  = preg_replace('/\D/', '', $keyword);
    $where[] = '(c.full_name LIKE ? OR c.email LIKE ? OR c.phone_number LIKE ?)';
    array_push($params, like_param($keyword), like_param($keyword), like_param($digits !== '' ? $digits : $keyword));
}
$whereSql = implode(' AND ', $where);

$total = (int) db_value("SELECT COUNT(*) FROM clients c WHERE $whereSql", $params);
$pager = paginate($total, PER_PAGE_ADMIN);
$rows  = db_all(
    "SELECT c.*, (SELECT COUNT(*) FROM appointments a WHERE a.client_id = c.client_id) AS appointment_count,
            (SELECT MAX(a.start_time) FROM appointments a WHERE a.client_id = c.client_id AND a.canceled = 0) AS last_visit
     FROM clients c WHERE $whereSql ORDER BY c.created_at DESC, c.client_id DESC
     LIMIT {$pager['per_page']} OFFSET {$pager['offset']}",
    $params
);

$pageTitle  = 'Khách hàng';
$activeMenu = 'clients';
require __DIR__ . '/templates/header.php';
?>
<h1 class="h3 mb-3 text-gray-800">Khách hàng</h1>
<div class="card shadow mb-4">
    <div class="card-body">
        <?= render_search_form($keyword, 'Tìm theo tên, số điện thoại hoặc email…') ?>
        <div class="table-responsive">
            <table class="table table-hover">
                <thead><tr><th></th><th>Họ và tên</th><th>Số điện thoại</th><th>Email</th><th>Ngày đăng ký</th><th>Số lịch hẹn</th><th></th></tr></thead>
                <tbody>
                <?php if (!$rows): ?>
                    <tr><td colspan="7" class="text-center text-muted py-4"><?= $keyword !== '' ? 'Không tìm thấy khách hàng phù hợp.' : 'Chưa có khách hàng nào đăng ký.' ?></td></tr>
                <?php endif; ?>
                <?php foreach ($rows as $row): ?>
                    <tr>
                        <td><?= render_avatar(null, null, $row['full_name']) ?></td>
                        <td><strong><?= e($row['full_name']) ?></strong></td>
                        <td class="text-nowrap"><?= e(format_phone($row['phone_number'])) ?></td>
                        <td><?= e($row['email']) ?></td>
                        <td><?= e(format_date($row['created_at'])) ?></td>
                        <td><?= (int) $row['appointment_count'] ?></td>
                        <td><a class="btn btn-sm btn-outline-primary" href="<?= e(url('admin/appointments.php?status=all&client_id=' . (int) $row['client_id'])) ?>"><i class="far fa-calendar-alt"></i> Lịch hẹn</a></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="d-flex justify-content-between align-items-center flex-wrap">
            <?= render_pager_summary($pager) ?>
            <?= render_pagination($pager, ['q' => $keyword]) ?>
        </div>
    </div>
</div>
<?php require __DIR__ . '/templates/footer.php'; ?>
