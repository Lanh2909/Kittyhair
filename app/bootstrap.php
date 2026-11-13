<?php

declare(strict_types=1);

/**
 * Điểm khởi động duy nhất của ứng dụng.
 * Mọi file trang (index.php, booking.php, admin/*.php, ajax/*.php) chỉ cần require file này, sau đó
 * tạo đối tượng Controller tương ứng và gọi 1 phương thức.
 *
 * Thứ tự: nạp cấu hình -> đăng ký autoload (tự nạp class theo namespace App\...) -> hàm tắt e()/url()
 * -> App::boot() (múi giờ, mã hóa, hiển thị lỗi, session).
 */

require_once __DIR__ . '/../config/config.php';

/*
 * Autoload theo chuẩn PSR-4: class App\Services\BookingService nằm ở app/Services/BookingService.php.
 */
spl_autoload_register(static function (string $class): void {
    $prefix = 'App\\';
    if (strncmp($class, $prefix, strlen($prefix)) !== 0) {
        return;
    }
    $file = __DIR__ . '/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
    if (is_file($file)) {
        require $file;
    }
});

require_once __DIR__ . '/helpers.php';

\App\Core\App::boot();
