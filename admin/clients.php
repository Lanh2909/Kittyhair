<?php

declare(strict_types=1);

/**
 * Danh sách khách hàng.
 * File này chỉ là "cửa vào" : mọi xử lý nằm trong App\Controllers\Admin\ClientController::handle().
 */

require_once __DIR__ . '/../app/bootstrap.php';

(new \App\Controllers\Admin\ClientController())->handle();
