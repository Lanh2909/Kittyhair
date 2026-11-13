<?php

declare(strict_types=1);

use App\Core\Csrf;
use App\Helpers\AppointmentHtml;
use App\Helpers\Format;
use App\Helpers\SearchForm;

/**
 * Quản lý lịch hẹn (TV4). Biến: $filters, $status, $keyword, $clientId, $rows, $pager, $keep.
 */
?>
<h1 class="h3 mb-3 text-gray-800">Quản lý lịch hẹn</h1>

<div class="card shadow mb-4">
    <div class="card-body">
        <div class="filter-pills">
            <?php foreach ($filters as $key => $label): ?>
                <a href="<?= e('?' . http_build_query(['status' => $key, 'q' => $keyword, 'client_id' => $clientId ?: ''])) ?>" class="<?= $status === $key ? 'active' : '' ?>"><?= e($label) ?></a>
            <?php endforeach; ?>
        </div>
        <?php if ($clientId > 0): ?>
            <div class="alert alert-info py-2">Đang lọc theo một khách hàng. <a href="<?= e('?status=' . $status) ?>">Bỏ lọc</a></div>
        <?php endif; ?>
        <?= SearchForm::render($keyword, 'Tìm theo khách hàng, SĐT, email, stylist, dịch vụ hoặc mã lịch hẹn…', ['status' => $status, 'client_id' => $clientId ?: '']) ?>

        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr><th>Mã</th><th>Thời gian</th><th>Khách hàng</th><th>Dịch vụ</th><th>Stylist</th><th>Tạm tính</th><th>Trạng thái</th><th>Ảnh</th><th></th></tr>
                </thead>
                <tbody>
                <?php if (!$rows): ?>
                    <tr><td colspan="9" class="text-center text-muted py-4"><?= $keyword !== '' ? 'Không tìm thấy lịch hẹn phù hợp.' : 'Chưa có lịch hẹn trong mục này.' ?></td></tr>
                <?php endif; ?>
                <?php foreach ($rows as $row):
                    $canCancel = (int) $row['canceled'] === 0 && strtotime($row['start_time']) > time();
                    ?>
                    <tr>
                        <td>#<?= (int) $row['appointment_id'] ?></td>
                        <td class="text-nowrap"><strong><?= e(date('H:i', strtotime($row['start_time']))) ?></strong> – <?= e(date('H:i', strtotime($row['end_time_expected']))) ?><br><small class="text-muted"><?= e(Format::date($row['start_time'])) ?></small></td>
                        <td><?= e($row['client_name']) ?><br><small class="text-muted"><?= e(Format::phone($row['phone_number'])) ?> · <?= e($row['client_email']) ?></small></td>
                        <td>
                            <?= AppointmentHtml::servicesText($row) ?>
                            <?php if ($row['note']): ?><br><small class="text-muted"><i class="far fa-sticky-note"></i> <?= e($row['note']) ?></small><?php endif; ?>
                            <?php if ((int) $row['canceled'] === 1): ?><br><small class="text-danger"><?= $row['canceled_by'] === 'admin' ? 'Salon hủy' : 'Khách yêu cầu hủy' ?><?= $row['cancellation_reason'] ? ': ' . e($row['cancellation_reason']) : '' ?></small><?php endif; ?>
                        </td>
                        <td><?= e($row['employee_name']) ?></td>
                        <td class="text-nowrap"><?= e(Format::money($row['total'])) ?></td>
                        <td><?= AppointmentHtml::statusBadge($row) ?></td>
                        <td class="text-nowrap">
                            <?php if ($row['reference_image']): ?>
                                <a class="btn btn-sm btn-outline-primary" target="_blank" rel="noopener" title="Xem ảnh" href="<?= e(url('file.php?id=' . (int) $row['appointment_id'])) ?>"><i class="far fa-eye"></i></a>
                                <a class="btn btn-sm btn-outline-primary" title="Tải xuống" href="<?= e(url('file.php?id=' . (int) $row['appointment_id'] . '&download=1')) ?>"><i class="fas fa-download"></i></a>
                                <form method="post" class="d-inline" data-confirm="Xóa ảnh tham khảo của lịch hẹn #<?= (int) $row['appointment_id'] ?>?">
                                    <?= Csrf::field() ?><input type="hidden" name="action" value="delete_reference"><input type="hidden" name="appointment_id" value="<?= (int) $row['appointment_id'] ?>">
                                    <button class="btn btn-sm btn-outline-primary" title="Xóa ảnh" type="submit"><i class="far fa-trash-alt"></i></button>
                                </form>
                            <?php else: ?><span class="text-muted">–</span><?php endif; ?>
                        </td>
                        <td class="text-nowrap">
                            <?php if ($canCancel): ?>
                                <button type="button" class="btn btn-sm btn-danger" data-toggle="modal" data-target="#cancelModal" data-id="<?= (int) $row['appointment_id'] ?>" title="Hủy lịch"><i class="fas fa-calendar-times"></i></button>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="d-flex justify-content-between align-items-center flex-wrap">
            <?= $pager->summary() ?>
            <?= $pager->links($keep) ?>
        </div>
    </div>
</div>

<div class="modal fade" id="cancelModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <form method="post" class="modal-content">
            <?= Csrf::field() ?>
            <input type="hidden" name="action" value="cancel">
            <input type="hidden" name="appointment_id" id="cancelAppointmentId">
            <div class="modal-header">
                <h5 class="modal-title">Hủy lịch hẹn</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Đóng"><span aria-hidden="true">&times;</span></button>
            </div>
            <div class="modal-body">
                <p>Bạn chắc chắn muốn hủy lịch hẹn này? Khung giờ sẽ được mở lại cho khách khác.</p>
                <div class="form-group">
                    <label for="cancelRequestedBy">Người yêu cầu hủy</label>
                    <select id="cancelRequestedBy" name="requested_by" class="custom-select">
                        <option value="client">Khách hàng (gọi điện / nhắn tin)</option>
                        <option value="admin">Salon chủ động hủy</option>
                    </select>
                </div>
                <div class="form-group mb-0">
                    <label for="cancelReason">Lý do hủy</label>
                    <textarea id="cancelReason" name="reason" class="form-control" rows="3" maxlength="500"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Không hủy</button>
                <button type="submit" class="btn btn-danger">Xác nhận hủy</button>
            </div>
        </form>
    </div>
</div>
