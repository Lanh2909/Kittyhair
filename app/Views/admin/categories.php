<?php

declare(strict_types=1);

use App\Core\Csrf;
use App\Helpers\SearchForm;

/**
 * Danh mục dịch vụ (TV2). Biến: $rows, $keyword, $pager.
 */
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h3 mb-0 text-gray-800">Danh mục dịch vụ</h1>
    <button class="btn btn-primary" data-toggle="modal" data-target="#categoryModal" data-id="" data-name=""><i class="fas fa-plus"></i> Thêm danh mục</button>
</div>
<div class="card shadow mb-4">
    <div class="card-body">
        <?= SearchForm::render($keyword, 'Tìm danh mục…') ?>
        <div class="table-responsive">
            <table class="table table-hover">
                <thead><tr><th>Tên danh mục</th><th>Số dịch vụ</th><th class="text-right">Thao tác</th></tr></thead>
                <tbody>
                <?php if (!$rows): ?><tr><td colspan="3" class="text-center text-muted py-4">Không có danh mục nào.</td></tr><?php endif; ?>
                <?php foreach ($rows as $row): ?>
                    <tr>
                        <td><strong><?= e($row['category_name']) ?></strong> <?= (int) $row['is_default'] ? '<span class="badge badge-kitty">Mặc định</span>' : '' ?></td>
                        <td><?= (int) $row['service_count'] ?></td>
                        <td class="text-right text-nowrap">
                            <?php if (!(int) $row['is_default']): ?>
                                <button class="btn btn-sm btn-outline-primary" data-toggle="modal" data-target="#categoryModal" data-id="<?= (int) $row['category_id'] ?>" data-name="<?= e($row['category_name']) ?>"><i class="fas fa-edit"></i> Sửa</button>
                                <form method="post" class="d-inline" data-confirm="Xóa danh mục &quot;<?= e($row['category_name']) ?>&quot;? Các dịch vụ sẽ chuyển sang &quot;Chưa phân loại&quot;.">
                                    <?= Csrf::field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="category_id" value="<?= (int) $row['category_id'] ?>">
                                    <button class="btn btn-sm btn-danger" type="submit"><i class="far fa-trash-alt"></i> Xóa</button>
                                </form>
                            <?php else: ?><span class="text-muted small">Không thể sửa/xóa</span><?php endif; ?>
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

<div class="modal fade" id="categoryModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <form method="post" class="modal-content">
            <?= Csrf::field() ?>
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="category_id" id="categoryId">
            <div class="modal-header">
                <h5 class="modal-title" id="categoryModalTitle">Danh mục</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Đóng"><span aria-hidden="true">&times;</span></button>
            </div>
            <div class="modal-body">
                <label for="categoryName">Tên danh mục</label>
                <input type="text" id="categoryName" name="category_name" class="form-control" maxlength="100" required>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Đóng</button>
                <button type="submit" class="btn btn-primary">Lưu</button>
            </div>
        </form>
    </div>
</div>
