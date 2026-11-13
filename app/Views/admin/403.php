<?php

declare(strict_types=1);

use App\Services\Permission;

/**
 * Trang báo lỗi 403 khi vai trò hiện tại không có quyền (TV3). Biến: $currentAdmin.
 */
?>
<div class="text-center py-5"><div class="display-4 text-gray-400 mb-3"><i class="fas fa-lock"></i></div><h1 class="h3 text-gray-800">Bạn không có quyền truy cập chức năng này</h1><p class="text-muted">Tài khoản vai trò <strong><?= e(Permission::roleLabel((string) $currentAdmin['role'])) ?></strong> không được sử dụng mục này. Vui lòng liên hệ Quản trị viên.</p><a class="btn btn-primary" href="<?= e(url('admin/index.php')) ?>">Về bảng điều khiển</a></div>
