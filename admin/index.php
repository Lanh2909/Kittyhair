<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/init.php';
require_permission('dashboard');

$stats = db_row(
    "SELECT
        (SELECT COUNT(*) FROM clients) AS clients,
        (SELECT COUNT(*) FROM services) AS services,
        (SELECT COUNT(*) FROM employees) AS employees,
        (SELECT COUNT(*) FROM appointments WHERE canceled = 0 AND start_time >= NOW()) AS upcoming,
        (SELECT COUNT(*) FROM appointments WHERE canceled = 0 AND DATE(start_time) = CURDATE()) AS today,
        (SELECT COALESCE(SUM(sb.price), 0) FROM services_booked sb JOIN appointments a ON a.appointment_id = sb.appointment_id
            WHERE a.canceled = 0 AND a.start_time >= NOW()) AS expected_revenue"
);

$upcoming = appointments_attach_services(db_all(
    "SELECT a.*, c.full_name AS client_name, c.phone_number, e.full_name AS employee_name
     FROM appointments a
     JOIN clients c ON c.client_id = a.client_id
     JOIN employees e ON e.employee_id = a.employee_id
     WHERE a.canceled = 0 AND a.start_time >= NOW()
     ORDER BY a.start_time LIMIT 8"
));

$cards = [
    ['Lịch hẹn sắp tới', (int) $stats['upcoming'], 'fa-calendar-check', 'primary'],
    ['Lịch hẹn hôm nay', (int) $stats['today'], 'fa-calendar-day', 'success'],
    ['Khách hàng', (int) $stats['clients'], 'fa-users', 'info'],
    ['Doanh thu dự kiến', format_money($stats['expected_revenue']), 'fa-coins', 'warning'],
];

$pageTitle  = 'Bảng điều khiển';
$activeMenu = 'dashboard';
require __DIR__ . '/templates/header.php';
?>
<div class="d-sm-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 text-gray-800">Bảng điều khiển</h1>
    <span class="text-muted small"><?= (int) $stats['employees'] ?> stylist · <?= (int) $stats['services'] ?> dịch vụ</span>
</div>

<div class="row">
    <?php foreach ($cards as [$label, $value, $icon, $color]): ?>
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-<?= e($color) ?> shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1"><?= e($label) ?></div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?= e((string) $value) ?></div>
                        </div>
                        <div class="col-auto"><i class="fas <?= e($icon) ?> fa-2x text-gray-300"></i></div>
                    </div>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<div class="card shadow mb-4">
    <div class="card-header py-3 d-flex justify-content-between align-items-center">
        <h6 class="m-0 font-weight-bold text-primary">Lịch hẹn sắp tới</h6>
        <a href="<?= e(url('admin/appointments.php')) ?>" class="btn btn-sm btn-primary">Xem tất cả lịch hẹn</a>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr><th>Thời gian</th><th>Khách hàng</th><th>Dịch vụ</th><th>Stylist</th><th>Tạm tính</th></tr>
                </thead>
                <tbody>
                <?php if (!$upcoming): ?>
                    <tr><td colspan="5" class="text-center text-muted py-4">Chưa có lịch hẹn sắp tới.</td></tr>
                <?php endif; ?>
                <?php foreach ($upcoming as $row): ?>
                    <tr>
                        <td><strong><?= e(date('H:i', strtotime($row['start_time']))) ?></strong> – <?= e(date('H:i', strtotime($row['end_time_expected']))) ?><br><small class="text-muted"><?= e(format_date($row['start_time'])) ?></small></td>
                        <td><?= e($row['client_name']) ?><br><small class="text-muted"><?= e(format_phone($row['phone_number'])) ?></small></td>
                        <td><?= appointment_services_text($row) ?></td>
                        <td><?= e($row['employee_name']) ?></td>
                        <td><?= e(format_money($row['total'])) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php require __DIR__ . '/templates/footer.php'; ?>
