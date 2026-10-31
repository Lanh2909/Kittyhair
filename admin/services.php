<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/init.php';
require_permission('catalog');

$categories = db_all('SELECT category_id, category_name FROM service_categories ORDER BY is_default DESC, category_name');
$action     = get('action');
$errors     = [];

/* ------------------------------ Xử lý POST ------------------------------ */
if (is_post()) {
    verify_csrf();
    $postAction = post('action');
    $id         = (int) post('service_id');

    if ($postAction === 'delete') {
        $service = db_row('SELECT * FROM services WHERE service_id = ?', [$id]);
        if ($service === null) {
            flash('danger', 'Không tìm thấy dịch vụ.');
        } else {
            db_query('DELETE FROM services WHERE service_id = ?', [$id]);
            delete_uploaded_file('services', $service['image']);
            log_activity('service_delete', 'Xóa dịch vụ: ' . $service['service_name']);
            flash('success', 'Đã xóa dịch vụ. Lịch hẹn cũ vẫn giữ nguyên tên, giá và thời lượng đã đặt.');
        }
        redirect('admin/services.php');
    }

    if ($postAction === 'save') {
        $existing = $id > 0 ? db_row('SELECT * FROM services WHERE service_id = ?', [$id]) : null;
        $data = [
            'service_name'        => post('service_name'),
            'category_id'         => (int) post('category_id'),
            'service_description' => post('service_description'),
            'service_price'       => preg_replace('/\D/', '', post('service_price')),
            'service_duration'    => (int) post('service_duration'),
        ];

        if (mb_strlen($data['service_name']) < 2 || mb_strlen($data['service_name']) > 100) {
            $errors['service_name'] = 'Tên dịch vụ phải từ 2 đến 100 ký tự.';
        }
        if (mb_strlen($data['service_description']) < 5 || mb_strlen($data['service_description']) > 255) {
            $errors['service_description'] = 'Mô tả phải từ 5 đến 255 ký tự.';
        }
        if (!in_array($data['category_id'], array_map('intval', array_column($categories, 'category_id')), true)) {
            $errors['category_id'] = 'Vui lòng chọn danh mục.';
        }
        if ($data['service_price'] === '' || (int) $data['service_price'] < 1000 || (int) $data['service_price'] > 99999999) {
            $errors['service_price'] = 'Giá phải từ 1.000đ đến 99.999.999đ.';
        }
        if ($data['service_duration'] < 5 || $data['service_duration'] > 480 || $data['service_duration'] % 5 !== 0) {
            $errors['service_duration'] = 'Thời lượng từ 5 đến 480 phút, là bội số của 5.';
        }
        if (!isset($errors['service_name']) && db_value('SELECT COUNT(*) FROM services WHERE service_name = ? AND service_id <> ?', [$data['service_name'], $id]) > 0) {
            $errors['service_name'] = 'Đã có dịch vụ trùng tên.';
        }

        $newImage = null;
        if (!$errors) {
            $upload = save_uploaded_image('image', 'services', UPLOAD_MAX_AVATAR);
            if ($upload['error'] !== null) {
                $errors['image'] = $upload['error'];
            }
            $newImage = $upload['file'];
        }

        if (!$errors) {
            $image = $existing['image'] ?? null;
            if ($newImage !== null) {
                delete_uploaded_file('services', $image);
                $image = $newImage;
            } elseif ($existing && post('remove_image') === '1') {
                delete_uploaded_file('services', $image);
                $image = null;
            }

            if ($existing) {
                db_query(
                    'UPDATE services SET category_id = ?, service_name = ?, service_description = ?, service_price = ?, service_duration = ?, image = ? WHERE service_id = ?',
                    [$data['category_id'], $data['service_name'], $data['service_description'], $data['service_price'], $data['service_duration'], $image, $id]
                );
                log_activity('service_update', 'Cập nhật dịch vụ: ' . $data['service_name']);
                flash('success', 'Đã cập nhật dịch vụ.');
            } else {
                db_query(
                    'INSERT INTO services (category_id, service_name, service_description, service_price, service_duration, image) VALUES (?, ?, ?, ?, ?, ?)',
                    [$data['category_id'], $data['service_name'], $data['service_description'], $data['service_price'], $data['service_duration'], $image]
                );
                log_activity('service_create', 'Thêm dịch vụ: ' . $data['service_name']);
                flash('success', 'Đã thêm dịch vụ mới.');
            }
            redirect('admin/services.php');
        }
        $action = $existing ? 'edit' : 'create';
        $_GET['id'] = (string) $id;
        $formData = $data + ['image' => $existing['image'] ?? null];
    }
}

/* ------------------------------ Form thêm/sửa ------------------------------ */
if ($action === 'create' || $action === 'edit') {
    $editing = $action === 'edit' ? db_row('SELECT * FROM services WHERE service_id = ?', [(int) get('id', '0')]) : null;
    if ($action === 'edit' && $editing === null) {
        flash('danger', 'Không tìm thấy dịch vụ.');
        redirect('admin/services.php');
    }
    $form = $formData ?? $editing ?? ['service_name' => '', 'category_id' => $categories[0]['category_id'] ?? 0, 'service_description' => '', 'service_price' => '', 'service_duration' => 30, 'image' => null];

    $pageTitle  = $editing ? 'Sửa dịch vụ' : 'Thêm dịch vụ';
    $activeMenu = 'services';
    require __DIR__ . '/templates/header.php';
    $imageUrl = upload_url('services', $editing['image'] ?? ($form['image'] ?? null));
    ?>
    <h1 class="h3 mb-3 text-gray-800"><?= e($pageTitle) ?></h1>
    <div class="card shadow mb-4"><div class="card-body">
        <form method="post" enctype="multipart/form-data" novalidate>
            <?= csrf_field() ?><input type="hidden" name="action" value="save"><input type="hidden" name="service_id" value="<?= (int) ($editing['service_id'] ?? 0) ?>">
            <div class="form-row">
                <div class="form-group col-md-8">
                    <label for="service_name">Tên dịch vụ</label>
                    <input type="text" id="service_name" name="service_name" class="form-control<?= invalid_class($errors, 'service_name') ?>" value="<?= e($form['service_name']) ?>" maxlength="100" required>
                    <?= field_error($errors, 'service_name') ?>
                </div>
                <div class="form-group col-md-4">
                    <label for="category_id">Danh mục</label>
                    <select id="category_id" name="category_id" class="form-control<?= invalid_class($errors, 'category_id') ?>">
                        <?php foreach ($categories as $category): ?>
                            <option value="<?= (int) $category['category_id'] ?>" <?= (int) $form['category_id'] === (int) $category['category_id'] ? 'selected' : '' ?>><?= e($category['category_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?= field_error($errors, 'category_id') ?>
                </div>
            </div>
            <div class="form-group">
                <label for="service_description">Mô tả</label>
                <textarea id="service_description" name="service_description" rows="3" class="form-control<?= invalid_class($errors, 'service_description') ?>" maxlength="255" required><?= e($form['service_description']) ?></textarea>
                <?= field_error($errors, 'service_description') ?>
            </div>
            <div class="form-row">
                <div class="form-group col-md-6">
                    <label for="service_price">Giá (VNĐ)</label>
                    <input type="text" inputmode="numeric" id="service_price" name="service_price" class="form-control<?= invalid_class($errors, 'service_price') ?>" value="<?= e($form['service_price']) ?>" placeholder="350000" required>
                    <?= field_error($errors, 'service_price') ?>
                </div>
                <div class="form-group col-md-6">
                    <label for="service_duration">Thời lượng (phút)</label>
                    <input type="number" id="service_duration" name="service_duration" class="form-control<?= invalid_class($errors, 'service_duration') ?>" value="<?= e((string) $form['service_duration']) ?>" min="5" max="480" step="5" required>
                    <?= field_error($errors, 'service_duration') ?>
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
                <?= field_error($errors, 'image') ?>
            </div>
            <button type="submit" class="btn btn-primary">Lưu dịch vụ</button>
            <a href="<?= e(url('admin/services.php')) ?>" class="btn btn-secondary">Quay lại</a>
        </form>
    </div></div>
    <?php
    require __DIR__ . '/templates/footer.php';
    exit;
}

/* ------------------------------ Danh sách ------------------------------ */
$keyword    = mb_substr(get('q'), 0, 100);
$categoryId = (int) get('category', '0');
$where  = ['1 = 1'];
$params = [];
if ($keyword !== '') {
    $where[] = '(s.service_name LIKE ? OR s.service_description LIKE ?)';
    array_push($params, like_param($keyword), like_param($keyword));
}
if ($categoryId > 0) {
    $where[]  = 's.category_id = ?';
    $params[] = $categoryId;
}
$whereSql = implode(' AND ', $where);

$total = (int) db_value("SELECT COUNT(*) FROM services s WHERE $whereSql", $params);
$pager = paginate($total, PER_PAGE_ADMIN);
$rows  = db_all(
    "SELECT s.*, c.category_name FROM services s JOIN service_categories c ON c.category_id = s.category_id
     WHERE $whereSql ORDER BY c.is_default, c.category_name, s.service_name
     LIMIT {$pager['per_page']} OFFSET {$pager['offset']}",
    $params
);

$pageTitle  = 'Dịch vụ';
$activeMenu = 'services';
require __DIR__ . '/templates/header.php';
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
                        <td><?php if ($thumb = upload_url('services', $row['image'])): ?><img class="thumb-sm" src="<?= e($thumb) ?>" alt=""><?php else: ?><span class="text-muted">–</span><?php endif; ?></td>
                        <td><strong><?= e($row['service_name']) ?></strong><br><small class="text-muted"><?= e($row['service_description']) ?></small></td>
                        <td><?= e($row['category_name']) ?></td>
                        <td class="text-nowrap"><?= e(format_money($row['service_price'])) ?></td>
                        <td class="text-nowrap"><?= e(format_duration((int) $row['service_duration'])) ?></td>
                        <td class="text-right text-nowrap">
                            <a class="btn btn-sm btn-outline-primary" href="<?= e(url('admin/services.php?action=edit&id=' . (int) $row['service_id'])) ?>"><i class="fas fa-edit"></i> Sửa</a>
                            <form method="post" class="d-inline" data-confirm="Xóa dịch vụ &quot;<?= e($row['service_name']) ?>&quot;?">
                                <?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="service_id" value="<?= (int) $row['service_id'] ?>">
                                <button class="btn btn-sm btn-danger" type="submit"><i class="far fa-trash-alt"></i> Xóa</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="d-flex justify-content-between align-items-center flex-wrap">
            <?= render_pager_summary($pager) ?>
            <?= render_pagination($pager, ['q' => $keyword, 'category' => $categoryId ?: '']) ?>
        </div>
    </div>
</div>
<?php require __DIR__ . '/templates/footer.php'; ?>
