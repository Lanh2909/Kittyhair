<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

if (is_post()) {
    verify_csrf();
    if (admin_current() !== null) {
        admin_logout();
    }
}
redirect('admin/login.php');
