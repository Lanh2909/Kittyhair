<?php

declare(strict_types=1);

/**
 * Cấu hình chung của Kitty Hair.
 * Có thể ghi đè bằng biến môi trường (KH_DB_HOST, KH_DB_NAME, KH_DB_USER, KH_DB_PASS, KH_BASE_URL).
 */

define('APP_ROOT', dirname(__DIR__));
define('APP_NAME', 'Kitty Hair');
define('APP_TIMEZONE', 'Asia/Ho_Chi_Minh');
define('APP_DEBUG', getenv('KH_DEBUG') === '1');

define('DB_HOST', getenv('KH_DB_HOST') ?: 'localhost');
define('DB_NAME', getenv('KH_DB_NAME') ?: 'kitty_hair');
define('DB_USER', getenv('KH_DB_USER') ?: 'root');
define('DB_PASS', getenv('KH_DB_PASS') !== false ? (string) getenv('KH_DB_PASS') : '');

define('UPLOAD_DIR', APP_ROOT . '/uploads');
define('UPLOAD_MAX_AVATAR', 2 * 1024 * 1024);      // 2 MB
define('UPLOAD_MAX_REFERENCE', 5 * 1024 * 1024);   // 5 MB
define('UPLOAD_ALLOWED_MIME', ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp']);

define('PASSWORD_MIN_LENGTH', 8);
define('LOGIN_MAX_ATTEMPTS', 5);
define('LOGIN_LOCK_MINUTES', 10);

define('PER_PAGE_ADMIN', 10);
define('PER_PAGE_CLIENT', 6);

/**
 * Thư viện bên thứ ba (Bootstrap, jQuery, Font Awesome) được tải từ CDN, không lưu trong dự án.
 * Muốn đổi phiên bản hoặc nguồn tải, chỉ cần sửa tại đây.
 */
define('CDN_BOOTSTRAP_CSS', 'https://cdnjs.cloudflare.com/ajax/libs/twitter-bootstrap/4.5.0/css/bootstrap.min.css');
define('CDN_BOOTSTRAP_JS', 'https://cdnjs.cloudflare.com/ajax/libs/twitter-bootstrap/4.5.0/js/bootstrap.bundle.min.js');
define('CDN_JQUERY_JS', 'https://cdnjs.cloudflare.com/ajax/libs/jquery/3.5.1/jquery.min.js');
define('CDN_FONTAWESOME_CSS', 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.13.0/css/all.min.css');
