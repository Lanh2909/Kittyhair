<?php

declare(strict_types=1);

/**
 * Tin nhắn liên hệ.
 * File này chỉ là "cửa vào" : mọi xử lý nằm trong App\Controllers\Admin\MessageController::handle().
 */

require_once __DIR__ . '/../app/bootstrap.php';

(new \App\Controllers\Admin\MessageController())->handle();
