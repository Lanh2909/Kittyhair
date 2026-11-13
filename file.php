<?php

declare(strict_types=1);

/**
 * Xem / tải ảnh tham khảo riêng tư của lịch hẹn (chỉ quản trị viên). GET ?id=<mã lịch hẹn>[&download=1]
 * File này chỉ là "cửa vào" : mọi xử lý nằm trong App\Controllers\FileController::show().
 */

require_once __DIR__ . '/app/bootstrap.php';

(new \App\Controllers\FileController())->show();
