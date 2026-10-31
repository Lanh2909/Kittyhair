<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/init.php';
require_permission('catalog');

if (is_post()) {
    verify_csrf();
    $action = post('action');
    $id     = (int) post('category_id');
    $name   = post('category_name');

    if ($action === 'save') {
        $existing = $id > 0 ? db_row('SELECT * FROM service_categories WHERE category_id = ?', [$id]) : null;
        if ($id > 0 && $existing === null) {
            flash('danger', 'Không tìm thấy danh mục.');
        } elseif ($existing && (int) $existing['is_default'] === 1) {
            flash('danger', 'Không thể sửa danh mục mặc định.');
        } elseif (mb_strlen($name) < 2 || mb_strlen($name) > 100) {
            flash('danger', 'Tên danh mục phải từ 2 đến 100 ký tự.');
        } elseif (db_value('SELECT COUNT(*) FROM service_categories WHERE category_name = ? AND category_id <> ?', [$name, $id]) > 0) {
            flash('danger', 'Tên danh mục đã tồn tại.');
        } elseif ($existing) {
            db_query('UPDATE service_categories SET category_name = ? WHERE category_id = ?', [$name, $id]);
            log_activity('category_update', 'Đổi tên danh mục: ' . $existing['category_name'] . ' → ' . $name);
            flash('success', 'Đã cập nhật danh mục.');
        } else {
            db_query('INSERT INTO service_categories (category_name) VALUES (?)', [$name]);
            log_activity('category_create', 'Thêm danh mục: ' . $name);
            flash('success', 'Đã thêm danh mục mới.');
        }
    } elseif ($action === 'delete') {
        $category = db_row('SELECT * FROM service_categories WHERE category_id = ?', [$id]);
        $default  = (int) db_value('SELECT category_id FROM service_categories WHERE is_default = 1 LIMIT 1');
        if ($category === null) {
            flash('danger', 'Không tìm thấy danh mục.');
        } elseif ((int) $category['is_default'] === 1 || $default === 0) {
            flash('danger', 'Không thể xóa danh mục mặc định.');
        } else {
            // Chuyển dịch vụ sang danh mục mặc định rồi xóa (trong 1 transaction)
            $pdo = db();
            $pdo->beginTransaction();
            $moved = db_query('UPDATE services SET category_id = ? WHERE category_id = ?', [$default, $id])->rowCount();
            db_query('DELETE FROM service_categories WHERE category_id = ?', [$id]);
            $pdo->commit();
            log_activity('category_delete', 'Xóa danh mục: ' . $category['category_name'] . " ($moved dịch vụ chuyển sang danh mục mặc định)");
            flash('success', 'Đã xóa danh mục' . ($moved ? " và chuyển $moved dịch vụ sang \"Chưa phân loại\"" : '') . '.');
        }
    }
    redirect('admin/service-categories.php' . (empty($_SERVER['QUERY_STRING']) ? '' : '?' . $_SERVER['QUERY_STRING']));
}

$keyword = mb_substr(get('q'), 0, 100);
$where   = $keyword !== '' ? 'WHERE c.category_name LIKE ?' : '';
$params  = $keyword !== '' ? [like_param($keyword)] : [];

$total = (int) db_value("SELECT COUNT(*) FROM service_categories c $where", $params);
$pager = paginate($total, PER_PAGE_ADMIN);
$rows  = db_all(
    "SELECT c.*, (SELECT COUNT(*) FROM services s WHERE s.category_id = c.category_id) AS service_count
     FROM service_categories c $where ORDER BY c.is_default DESC, c.category_name
     LIMIT {$pager['per_page']} OFFSET {$pager['offset']}",
    $params
);

$pageTitle  = 'Danh mục dịch vụ';
$activeMenu = 'categories';
require __DIR__ . '/templates/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h3 mb-0 text-gray-800">Danh mục dịch vụ</h1>
    <button class="btn btn-primary" data-toggle="modal" data-target="#categoryModal" data-id="" data-name=""><i class="fas fa-plus"></i> Thêm danh mục</button>
</div>
<div class="card shadow mb-4">
    <div class="card-body">
        <?= render_search_form($keyword, 'Tìm danh mục…') ?>
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
                                    <?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="category_id" value="<?= (int) $row['category_id'] ?>">
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
            <?= render_pager_summary($pager) ?>
            <?= render_pagination($pager, ['q' => $keyword]) ?>
        </div>
    </div>
</div>

<div class="modal fade" id="categoryModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <form method="post" class="modal-content">
            <?= csrf_field() ?>
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
<?php require __DIR__ . '/templates/footer.php'; ?>
