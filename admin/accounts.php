<?php

declare(strict_types=1);

/**
 * Tài khoản quản trị.
 * File này chỉ là "cửa vào": mọi xử lý nằm trong App\Controllers\Admin\AccountController::handle().
 */

require_once __DIR__ . '/../app/bootstrap.php';

(new \App\Controllers\Admin\AccountController())->handle();
