<?php

declare(strict_types=1);

/**
 * Bảng giá dịch vụ: tìm kiếm, lọc theo danh mục, phân trang.
 * File này là cửa vào : mọi xử lý nằm trong App\Controllers\PricingController::index().
 */

require_once __DIR__ . '/app/bootstrap.php';

(new \App\Controllers\PricingController())->index();
