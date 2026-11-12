<?php

declare(strict_types=1);

use App\Core\Csrf;
use App\Helpers\Form;

/**
 * Form thêm/sửa dịch vụ (TV2). Biến: $pageTitle, $editing, $form, $errors, $categories, $imageUrl.
 */
?>
    <h1 class="h3 mb-3 text-gray-800"><?= e($pageTitle) ?></h1>
    <div class="card shadow mb-4"><div class="card-body">
        <form method="post" enctype="multipart/form-data" novalidate>
            <?= Csrf::field() ?><input type="hidden" name="action" value="save"><input type="hidden" name="service_id" value="<?= (int) ($editing['service_id'] ?? 0) ?>">
            <div class="form-row">
                <div class="form-group col-md-8">
                    <label for="service_name">Tên dịch vụ</label>
                    <input type="text" id="service_name" name="service_name" class="form-control<?= Form::invalidClass($errors, 'service_name') ?>" value="<?= e($form['service_name']) ?>" maxlength="100" required>
                    <?= Form::error($errors, 'service_name') ?>
                </div>
                <div class="form-group col-md-4">
                    <label for="category_id">Danh mục</label>
                    <select id="category_id" name="category_id" class="form-control<?= Form::invalidClass($errors, 'category_id') ?>">
                        <?php foreach ($categories as $category): ?>
                            <option value="<?= (int) $category['category_id'] ?>" <?= (int) $form['category_id'] === (int) $category['category_id'] ? 'selected' : '' ?>><?= e($category['category_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?= Form::error($errors, 'category_id') ?>
                </div>
            </div>
            <div class="form-group">
                <label for="service_description">Mô tả</label>
                <textarea id="service_description" name="service_description" rows="3" class="form-control<?= Form::invalidClass($errors, 'service_description') ?>" maxlength="255" required><?= e($form['service_description']) ?></textarea>
                <?= Form::error($errors, 'service_description') ?>
            </div>
            <div class="form-row">
                <div class="form-group col-md-6">
                    <label for="service_price">Giá (VNĐ)</label>
                    <input type="text" inputmode="numeric" id="service_price" name="service_price" class="form-control<?= Form::invalidClass($errors, 'service_price') ?>" value="<?= e($form['service_price']) ?>" placeholder="350000" required>
                    <?= Form::error($errors, 'service_price') ?>
                </div>
                <div class="form-group col-md-6">
                    <label for="service_duration">Thời lượng (phút)</label>
                    <input type="number" id="service_duration" name="service_duration" class="form-control<?= Form::invalidClass($errors, 'service_duration') ?>" value="<?= e((string) $form['service_duration']) ?>" min="5" max="480" step="5" required>
                    <?= Form::error($errors, 'service_duration') ?>
                </div>
            </div>
            <div class="form-group">
                <label for="image">Ảnh dịch vụ <span class="text-muted">(không bắt buộc – JPG/PNG/WEBP, tối đa 2 MB)</span></label>
                <?php if ($imageUrl): ?>
                    <div class="mb-2">
                        <img class="thumb-md" src="<?= e($imageUrl) ?>" alt="Ảnh dịch vụ hiện tại">
                        <div class="mt-2">
                            <a class="btn btn-sm btn-outline-primary" href="<?= e($imageUrl) ?>" target="_blank" rel="noopener"><i class="far fa-eye"></i> Xem</a>
                            <a class="btn btn-sm btn-outline-primary" href="<?= e($imageUrl) ?>" download><i class="fas fa-download"></i> Tải xuống</a>
                            <label class="ml-2 mb-0"><input type="checkbox" name="remove_image" value="1"> Xóa ảnh hiện tại</label>
                        </div>
                    </div>
                <?php endif; ?>
                <input type="file" id="image" name="image" class="form-control-file" accept="image/jpeg,image/png,image/webp" data-preview="#imagePreview">
                <img id="imagePreview" class="thumb-md mt-2" alt="Xem trước" hidden>
                <?= Form::error($errors, 'image') ?>
            </div>
            <button type="submit" class="btn btn-primary">Lưu dịch vụ</button>
            <a href="<?= e(url('admin/services.php')) ?>" class="btn btn-secondary">Quay lại</a>
        </form>
    </div></div>
