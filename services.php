<?php

declare(strict_types=1);

/**
 * Bảng giá dịch vụ (công khai): tìm kiếm, lọc theo danh mục, phân trang.
 * File này chỉ là "cửa vào" : mọi xử lý nằm trong App\Controllers\PricingController::index().
 */

require_once __DIR__ . '/app/bootstrap.php';

(new \App\Controllers\PricingController())->index();
