<?php

declare(strict_types=1);

/**
 * Nhật ký hoạt động.
 * File này chỉ là "cửa vào" : mọi xử lý nằm trong App\Controllers\Admin\ActivityLogController::handle().
 */

require_once __DIR__ . '/../app/bootstrap.php';

(new \App\Controllers\Admin\ActivityLogController())->handle();
