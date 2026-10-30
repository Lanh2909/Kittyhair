<?php

declare(strict_types=1);

/**
 * Khởi tạo chung: cấu hình, session, kết nối CSDL, nạp các module.
 * Mọi trang (client + admin) chỉ cần require file này.
 */

require_once __DIR__ . '/../config/config.php';

date_default_timezone_set(APP_TIMEZONE);
mb_internal_encoding('UTF-8');
error_reporting(E_ALL);
ini_set('display_errors', APP_DEBUG ? '1' : '0');

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax']);
    session_start();
}

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/settings.php';
require_once __DIR__ . '/validation.php';
require_once __DIR__ . '/pagination.php';
require_once __DIR__ . '/activity.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/uploads.php';
require_once __DIR__ . '/booking.php';
require_once __DIR__ . '/schedule.php';
