<?php

declare(strict_types=1);

use App\Core\Csrf;
use App\Helpers\Format;
use App\Helpers\SearchForm;

/**
 * Danh sách nhân viên (TV4). Biến: $rows (đã có schedule_summary), $keyword, $pager.
 */
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h3 mb-0 text-gray-800">Nhân viên</h1>
    <a class="btn btn-primary" href="<?= e(url('admin/employees.php?action=create')) ?>"><i class="fas fa-plus"></i> Thêm nhân viên</a>
</div>
<div class="card shadow mb-4">
    <div class="card-body">
        <?= SearchForm::render($keyword, 'Tìm theo tên, chức danh, số điện thoại hoặc email…') ?>
        <div class="table-responsive">
            <table class="table table-hover">
                <thead><tr><th></th><th>Họ và tên</th><th>Chức danh</th><th>Liên hệ</th><th>Giờ làm việc</th><th>Lịch sắp tới</th><th class="text-right">Thao tác</th></tr></thead>
                <tbody>
                <?php if (!$rows): ?><tr><td colspan="7" class="text-center text-muted py-4">Không tìm thấy nhân viên.</td></tr><?php endif; ?>
                <?php foreach ($rows as $row):
                    $summary = $row['schedule_summary'];
                    ?>
                    <tr>
                        <td><?= $uploads->avatar('avatars', $row['avatar'], $row['full_name']) ?></td>
                        <td><strong><?= e($row['full_name']) ?></strong></td>
                        <td><?= e($row['position']) ?></td>
                        <td class="text-nowrap"><?= e(Format::phone($row['phone_number'])) ?><br><small class="text-muted"><?= e($row['email']) ?></small></td>
                        <td><?= $summary !== '' ? e($summary) : '<span class="text-danger small">Chưa có lịch – không nhận đặt lịch</span>' ?></td>
                        <td><?= (int) $row['upcoming'] ?></td>
                        <td class="text-right text-nowrap">
                            <a class="btn btn-sm btn-outline-primary" href="<?= e(url('admin/employees.php?action=edit&id=' . (int) $row['employee_id'])) ?>"><i class="fas fa-edit"></i> Sửa</a>
                            <form method="post" class="d-inline" data-confirm="Xóa nhân viên &quot;<?= e($row['full_name']) ?>&quot;?">
                                <?= Csrf::field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="employee_id" value="<?= (int) $row['employee_id'] ?>">
                                <button class="btn btn-sm btn-danger" type="submit"><i class="far fa-trash-alt"></i> Xóa</button>
                            </form>
                        </td>
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
