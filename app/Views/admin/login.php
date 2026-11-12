<?php

declare(strict_types=1);

use App\Core\Csrf;

/**
 * Trang đăng nhập quản trị – trang độc lập, không dùng layout (TV3). Biến: $error, $username.
 */
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Đăng nhập quản trị | <?= e($settings->get('salon_name')) ?></title>
    <link rel="icon" href="<?= e(url('favicon.ico')) ?>" sizes="any">
    <link rel="stylesheet" href="<?= e(CDN_FONTAWESOME_CSS) ?>">
    <link href="https://fonts.googleapis.com/css2?family=Be+Vietnam+Pro:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= e(url('admin/assets/css/sb-admin-2.min.css')) ?>">
    <link rel="stylesheet" href="<?= e(url('admin/assets/css/kitty-admin.css')) ?>">
</head>
<body>
<div class="login-page">
    <form class="login-card" method="post" action="<?= e(url('admin/login.php')) ?>" novalidate>
        <?= Csrf::field() ?>
        <img class="logo" src="<?= e(url('assets/img/logo/logo-stacked.svg')) ?>" alt="<?= e($settings->get('salon_name')) ?>">
        <h1>Đăng nhập quản trị</h1>
        <p class="subtitle">Dành cho quản trị viên <?= e($settings->get('salon_name')) ?></p>

        <?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>

        <div class="form-group">
            <label for="username">Tên đăng nhập</label>
            <input type="text" id="username" name="username" class="form-control" value="<?= e($username) ?>" autocomplete="username" required autofocus>
        </div>
        <div class="form-group">
            <label for="password">Mật khẩu</label>
            <input type="password" id="password" name="password" class="form-control" autocomplete="current-password" required>
        </div>
        <button type="submit" class="btn btn-primary btn-block py-2">Đăng nhập</button>
        <p class="text-center small mt-3 mb-0"><a href="<?= e(url('index.php')) ?>">&larr; Về trang khách hàng</a></p>
    </form>
</div>
</body>
</html>
