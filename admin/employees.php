<?php

declare(strict_types=1);

/**
 * Quản lý nhân viên kèm giờ làm 7 ngày.
 * File này chỉ là "cửa vào" : mọi xử lý nằm trong App\Controllers\Admin\EmployeeController::handle().
 */

require_once __DIR__ . '/../app/bootstrap.php';

(new \App\Controllers\Admin\EmployeeController())->handle();
