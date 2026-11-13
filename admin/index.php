<?php

declare(strict_types=1);

/**
 * Bảng điều khiển.
 * File này chỉ là "cửa vào" : mọi xử lý nằm trong App\Controllers\Admin\DashboardController::handle().
 */

require_once __DIR__ . '/../app/bootstrap.php';

(new \App\Controllers\Admin\DashboardController())->handle();
