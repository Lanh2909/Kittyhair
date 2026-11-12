<?php

declare(strict_types=1);

/**
 * API JSON: POST form liên hệ.
 * File này chỉ là "cửa vào" : mọi xử lý nằm trong App\Controllers\ContactController::send().
 */

require_once __DIR__ . '/../app/bootstrap.php';

(new \App\Controllers\ContactController())->send();
