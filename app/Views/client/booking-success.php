<?php

declare(strict_types=1);

use App\Helpers\Format;

/**
 * Trang xác nhận đặt lịch (TV1). Biến: $appt (đã gắn services, total).
 */
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
            <div class="when"><i class="far fa-clock"></i> <?= e(date('H:i', strtotime($appt['start_time']))) ?> – <?= e(date('H:i', strtotime($appt['end_time_expected']))) ?>, <?= e(Format::weekday((int) date('N', strtotime($appt['start_time'])))) ?> <?= e(Format::date($appt['start_time'])) ?></div>
            <div class="meta mb-2">Stylist: <strong><?= e($appt['employee_name']) ?></strong></div>
            <ul class="mb-2 pl-3">
                <?php foreach ($appt['services'] as $service): ?>
                    <li><?= e($service['service_name']) ?> <span class="text-muted">– <?= e(Format::money($service['price'])) ?></span></li>
                <?php endforeach; ?>
            </ul>
            <div><strong>Tạm tính: <span class="text-primary"><?= e(Format::money($appt['total'])) ?></span></strong></div>
            <div class="meta mt-2"><i class="fas fa-phone-alt"></i> <?= e(Format::phone($appt['phone_number'])) ?> · <i class="far fa-envelope"></i> <?= e($appt['client_email']) ?></div>
            <?php if ($appt['note']): ?><div class="meta mt-1"><i class="far fa-sticky-note"></i> <?= e($appt['note']) ?></div><?php endif; ?>
            <?php if ($appt['reference_image']): ?><div class="meta mt-1"><i class="fas fa-image"></i> Đã đính kèm ảnh kiểu tóc tham khảo.</div><?php endif; ?>
        </article>

        <div class="alert alert-info mt-3">
            Vui lòng đến đúng giờ. Nếu cần <strong>đổi hoặc hủy lịch</strong>, hãy gọi
            <a href="tel:<?= e($settings->get('salon_phone')) ?>"><strong><?= e(Format::phone($settings->get('salon_phone'))) ?></strong></a>
            và cung cấp mã lịch hẹn <strong>#<?= (int) $appt['appointment_id'] ?></strong> cùng số điện thoại đã đặt.
        </div>

        <div class="text-center mt-3">
            <a href="<?= e(url('index.php')) ?>" class="btn btn-outline-primary mr-2">Về trang chủ</a>
            <a href="<?= e(url('booking.php')) ?>" class="btn btn-primary">Đặt thêm lịch</a>
        </div>
    </div>
</section>
