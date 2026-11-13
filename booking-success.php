<?php

declare(strict_types=1);

/**
 * Trang xác nhận sau khi đặt lịch.
 * File này chỉ là "cửa vào" : mọi xử lý nằm trong App\Controllers\BookingController::success().
 */

require_once __DIR__ . '/app/bootstrap.php';

(new \App\Controllers\BookingController())->success();
