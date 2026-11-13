<?php

declare(strict_types=1);

/**
 * Cài đặt hệ thống.
 * File này chỉ là "cửa vào" : mọi xử lý nằm trong App\Controllers\Admin\SettingController::handle().
 */

require_once __DIR__ . '/../app/bootstrap.php';

(new \App\Controllers\Admin\SettingController())->handle();
