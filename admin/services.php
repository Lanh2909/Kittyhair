<?php

declare(strict_types=1);

/**
 * Quản lý dịch vụ (kèm ảnh).
 * File này chỉ là "cửa vào" : mọi xử lý nằm trong App\Controllers\Admin\ServiceController::handle().
 */

require_once __DIR__ . '/../app/bootstrap.php';

(new \App\Controllers\Admin\ServiceController())->handle();
