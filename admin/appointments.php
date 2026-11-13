<?php

declare(strict_types=1);

/**
 * Quản lý lịch hẹn.
 * File này chỉ là "cửa vào" : mọi xử lý nằm trong App\Controllers\Admin\AppointmentController::handle().
 */

require_once __DIR__ . '/../app/bootstrap.php';

(new \App\Controllers\Admin\AppointmentController())->handle();
