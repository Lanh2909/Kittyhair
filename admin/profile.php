<?php

declare(strict_types=1);

/**
 * Hồ sơ cá nhân.
 * File này chỉ là "cửa vào" : mọi xử lý nằm trong App\Controllers\Admin\ProfileController::handle().
 */

require_once __DIR__ . '/../app/bootstrap.php';

(new \App\Controllers\Admin\ProfileController())->handle();
