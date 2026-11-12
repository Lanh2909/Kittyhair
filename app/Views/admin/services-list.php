<?php

declare(strict_types=1);

use App\Core\Csrf;
use App\Helpers\Format;

/**
 * Danh sách dịch vụ (TV2). Biến: $rows, $categories, $keyword, $categoryId, $pager.
 */
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h3 mb-0 text-gray-800">Dịch vụ</h1>
    <a class="btn btn-primary" href="<?= e(url('admin/services.php?action=create')) ?>"><i class="fas fa-plus"></i> Thêm dịch vụ</a>
</div>
<div class="card shadow mb-4">
    <div class="card-body">
        <form method="get" class="form-inline mb-3">
            <div class="input-group search-box mr-2 mb-2">
                <input type="search" name="q" class="form-control" value="<?= e($keyword) ?>" placeholder="Tìm theo tên hoặc mô tả…" maxlength="100">
                <div class="input-group-append"><button class="btn btn-primary" type="submit"><i class="fas fa-search"></i> Tìm</button></div>
            </div>
            <select name="category" class="custom-select mr-2 mb-2" onchange="this.form.submit()">
                <option value="0">Tất cả danh mục</option>
                <?php foreach ($categories as $category): ?>
                    <option value="<?= (int) $category['category_id'] ?>" <?= $categoryId === (int) $category['category_id'] ? 'selected' : '' ?>><?= e($category['category_name']) ?></option>
                <?php endforeach; ?>
            </select>
            <?php if ($keyword !== '' || $categoryId > 0): ?><a class="btn btn-outline-secondary mb-2" href="?">Xóa lọc</a><?php endif; ?>
        </form>
        <div class="table-responsive">
            <table class="table table-hover">
                <thead><tr><th>Ảnh</th><th>Dịch vụ</th><th>Danh mục</th><th>Giá</th><th>Thời lượng</th><th class="text-right">Thao tác</th></tr></thead>
                <tbody>
                <?php if (!$rows): ?><tr><td colspan="6" class="text-center text-muted py-4">Không tìm thấy dịch vụ.</td></tr><?php endif; ?>
                <?php foreach ($rows as $row): ?>
                    <tr>
                        <td><?php if ($thumb = $uploads->url('services', $row['image'])): ?><img class="thumb-sm" src="<?= e($thumb) ?>" alt=""><?php else: ?><span class="text-muted">–</span><?php endif; ?></td>
                        <td><strong><?= e($row['service_name']) ?></strong><br><small class="text-muted"><?= e($row['service_description']) ?></small></td>
                        <td><?= e($row['category_name']) ?></td>
                        <td class="text-nowrap"><?= e(Format::money($row['service_price'])) ?></td>
                        <td class="text-nowrap"><?= e(Format::duration((int) $row['service_duration'])) ?></td>
                        <td class="text-right text-nowrap">
                            <a class="btn btn-sm btn-outline-primary" href="<?= e(url('admin/services.php?action=edit&id=' . (int) $row['service_id'])) ?>"><i class="fas fa-edit"></i> Sửa</a>
                            <form method="post" class="d-inline" data-confirm="Xóa dịch vụ &quot;<?= e($row['service_name']) ?>&quot;?">
                                <?= Csrf::field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="service_id" value="<?= (int) $row['service_id'] ?>">
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
            <?= $pager->links(['q' => $keyword, 'category' => $categoryId ?: '']) ?>
        </div>
    </div>
</div>
