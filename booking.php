<?php

declare(strict_types=1);

/**
 * Đặt lịch 4 bước: dịch vụ -> stylist -> ngày & giờ -> xác nhận.
 * File này chỉ là "cửa vào" : mọi xử lý nằm trong App\Controllers\BookingController::handle().
 */

require_once __DIR__ . '/app/bootstrap.php';

(new \App\Controllers\BookingController())->handle();
