<?php

declare(strict_types=1);

/**
 * Trang chủ.
 * File này chỉ là "cửa vào" : mọi xử lý nằm trong App\Controllers\HomeController::index().
 */

require_once __DIR__ . '/app/bootstrap.php';

(new \App\Controllers\HomeController())->index();
