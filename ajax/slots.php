<?php

declare(strict_types=1);

/**
 * API JSON: GET ?employee_id=1&services[]=2 -> các ngày và khung giờ còn trống.
 * File này chỉ là "cửa vào" : mọi xử lý nằm trong App\Controllers\SlotController::index().
 */

require_once __DIR__ . '/../app/bootstrap.php';

(new \App\Controllers\SlotController())->index();
