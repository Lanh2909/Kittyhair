<?php

declare(strict_types=1);

/**
 * Đăng nhập quản trị.
 * File này chỉ là "cửa vào" : mọi xử lý nằm trong App\Controllers\Admin\AuthController::login().
 */

require_once __DIR__ . '/../app/bootstrap.php';

(new \App\Controllers\Admin\AuthController())->login();
