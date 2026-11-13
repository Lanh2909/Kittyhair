<?php

declare(strict_types=1);

/**
 * Danh mục dịch vụ.
 * File này chỉ là "cửa vào" : mọi xử lý nằm trong App\Controllers\Admin\CategoryController::handle().
 */

require_once __DIR__ . '/../app/bootstrap.php';

(new \App\Controllers\Admin\CategoryController())->handle();
