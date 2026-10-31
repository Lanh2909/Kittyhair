<?php

declare(strict_types=1);

/**
 * Trang xác nhận sau khi đặt lịch. Chỉ hiển thị lịch hẹn vừa tạo trong phiên hiện tại
 * (id lưu trong session) – không phải chức năng tra cứu.
 */

require_once __DIR__ . '/includes/bootstrap.php';

$id   = (int) ($_SESSION['last_booking_id'] ?? 0);
$appt = $id > 0 ? db_row(
    'SELECT a.*, c.full_name AS client_name, c.phone_number, c.email AS client_email, e.full_name AS employee_name
     FROM appointments a
     JOIN clients c ON c.client_id = a.client_id
     JOIN employees e ON e.employee_id = a.employee_id
     WHERE a.appointment_id = ?',
    [$id]
) : null;

if ($appt === null) {
    redirect('booking.php');
}
$appt = appointments_attach_services([$appt])[0];

$pageTitle = 'Đặt lịch thành công';
require __DIR__ . '/templates/header.php';
?>
<section class="page-wrap">
    <div class="container" style="max-width: 720px;">
        <div class="text-center mb-4">
            <div class="mb-2" style="font-size:3rem;color:var(--kh-rose);"><i class="fas fa-check-circle"></i></div>
            <h1 class="page-title">Đặt lịch thành công!</h1>
            <p class="text-muted">Cảm ơn <strong><?= e($appt['client_name']) ?></strong>. Salon đã nhận lịch hẹn của bạn.</p>
        </div>

        <div class="code-box">
            <small>Mã lịch hẹn</small>
            <div class="code">#<?= (int) $appt['appointment_id'] ?></div>
        </div>

        <article class="appt-card">
            <div class="when"><i class="far fa-clock"></i> <?= e(date('H:i', strtotime($appt['start_time']))) ?> – <?= e(date('H:i', strtotime($appt['end_time_expected']))) ?>, <?= e(weekday_name((int) date('N', strtotime($appt['start_time'])))) ?> <?= e(format_date($appt['start_time'])) ?></div>
            <div class="meta mb-2">Stylist: <strong><?= e($appt['employee_name']) ?></strong></div>
            <ul class="mb-2 pl-3">
                <?php foreach ($appt['services'] as $service): ?>
                    <li><?= e($service['service_name']) ?> <span class="text-muted">– <?= e(format_money($service['price'])) ?></span></li>
                <?php endforeach; ?>
            </ul>
            <div><strong>Tạm tính: <span class="text-primary"><?= e(format_money($appt['total'])) ?></span></strong></div>
            <div class="meta mt-2"><i class="fas fa-phone-alt"></i> <?= e(format_phone($appt['phone_number'])) ?> · <i class="far fa-envelope"></i> <?= e($appt['client_email']) ?></div>
            <?php if ($appt['note']): ?><div class="meta mt-1"><i class="far fa-sticky-note"></i> <?= e($appt['note']) ?></div><?php endif; ?>
            <?php if ($appt['reference_image']): ?><div class="meta mt-1"><i class="fas fa-image"></i> Đã đính kèm ảnh kiểu tóc tham khảo.</div><?php endif; ?>
        </article>

        <div class="alert alert-info mt-3">
            Vui lòng đến đúng giờ. Nếu cần <strong>đổi hoặc hủy lịch</strong>, hãy gọi
            <a href="tel:<?= e(setting('salon_phone')) ?>"><strong><?= e(format_phone(setting('salon_phone'))) ?></strong></a>
            và cung cấp mã lịch hẹn <strong>#<?= (int) $appt['appointment_id'] ?></strong> cùng số điện thoại đã đặt.
        </div>

        <div class="text-center mt-3">
            <a href="<?= e(url('index.php')) ?>" class="btn btn-outline-primary mr-2">Về trang chủ</a>
            <a href="<?= e(url('booking.php')) ?>" class="btn btn-primary">Đặt thêm lịch</a>
        </div>
    </div>
</section>
<?php require __DIR__ . '/templates/footer.php'; ?>
