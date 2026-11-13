<?php

declare(strict_types=1);

use App\Helpers\Format;
use App\Helpers\SearchForm;

/**
 * Danh sách khách hàng (TV4). Biến: $rows, $keyword, $pager.
 */
?>
<h1 class="h3 mb-3 text-gray-800">Khách hàng</h1>
<div class="card shadow mb-4">
    <div class="card-body">
        <?= SearchForm::render($keyword, 'Tìm theo tên, số điện thoại hoặc email…') ?>
        <div class="table-responsive">
            <table class="table table-hover">
                <thead><tr><th></th><th>Họ và tên</th><th>Số điện thoại</th><th>Email</th><th>Ngày đăng ký</th><th>Số lịch hẹn</th><th></th></tr></thead>
                <tbody>
                <?php if (!$rows): ?>
                    <tr><td colspan="7" class="text-center text-muted py-4"><?= $keyword !== '' ? 'Không tìm thấy khách hàng phù hợp.' : 'Chưa có khách hàng nào đăng ký.' ?></td></tr>
                <?php endif; ?>
                <?php foreach ($rows as $row): ?>
                    <tr>
                        <td><?= $uploads->avatar(null, null, $row['full_name']) ?></td>
                        <td><strong><?= e($row['full_name']) ?></strong></td>
                        <td class="text-nowrap"><?= e(Format::phone($row['phone_number'])) ?></td>
                        <td><?= e($row['email']) ?></td>
                        <td><?= e(Format::date($row['created_at'])) ?></td>
                        <td><?= (int) $row['appointment_count'] ?></td>
                        <td><a class="btn btn-sm btn-outline-primary" href="<?= e(url('admin/appointments.php?status=all&client_id=' . (int) $row['client_id'])) ?>"><i class="far fa-calendar-alt"></i> Lịch hẹn</a></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="d-flex justify-content-between align-items-center flex-wrap">
            <?= $pager->summary() ?>
            <?= $pager->links(['q' => $keyword]) ?>
        </div>
    </div>
</div>
