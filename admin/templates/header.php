<?php

declare(strict_types=1);

/**
 * Khung trang quản trị: sidebar + topbar.
 * Biến trước khi include: $pageTitle (string), $activeMenu (string, khóa menu đang chọn).
 * Mỗi trang admin phải gọi require_admin() TRƯỚC khi include file này.
 */

$currentAdmin = admin_current();
$activeMenu   = $activeMenu ?? '';
$unreadMessages = (int) db_value('SELECT COUNT(*) FROM contact_messages WHERE is_read = 0');

// Menu chính: [khóa, đường dẫn, icon, nhãn, quyền cần có]. Hồ sơ / Cài đặt / Nhật ký nằm ở menu góc phải.
$menu = [
    'Tổng quan' => [
        ['dashboard', 'index.php', 'fa-tachometer-alt', 'Bảng điều khiển', 'dashboard'],
        ['appointments', 'appointments.php', 'fa-calendar-check', 'Lịch hẹn', 'appointments'],
    ],
    'Dịch vụ' => [
        ['categories', 'service-categories.php', 'fa-list', 'Danh mục dịch vụ', 'catalog'],
        ['services', 'services.php', 'fa-cut', 'Dịch vụ', 'catalog'],
    ],
    'Khách hàng & nhân sự' => [
        ['clients', 'clients.php', 'fa-users', 'Khách hàng', 'clients'],
        ['employees', 'employees.php', 'fa-user-tie', 'Nhân viên', 'employees'],
        ['messages', 'messages.php', 'fa-envelope', 'Tin nhắn liên hệ', 'messages'],
    ],
    'Hệ thống' => [
        ['accounts', 'accounts.php', 'fa-user-shield', 'Tài khoản quản trị', 'accounts'],
    ],
];
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <meta name="robots" content="noindex, nofollow">
    <title><?= e($pageTitle) ?> | Quản trị <?= e(setting('salon_name')) ?></title>

    <link rel="icon" href="<?= e(url('favicon.ico')) ?>" sizes="any">
    <link rel="stylesheet" href="<?= e(CDN_FONTAWESOME_CSS) ?>">
    <link href="https://fonts.googleapis.com/css2?family=Be+Vietnam+Pro:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= e(url('admin/assets/css/sb-admin-2.min.css')) ?>">
    <link rel="stylesheet" href="<?= e(url('admin/assets/css/kitty-admin.css')) ?>">
</head>
<body id="page-top">
<div id="wrapper">

    <ul class="navbar-nav sidebar sidebar-dark accordion" id="accordionSidebar">
        <a class="sidebar-brand d-flex align-items-center justify-content-center" href="<?= e(url('admin/index.php')) ?>">
            <img class="sidebar-logo" src="<?= e(url('assets/img/logo/logo-icon.svg')) ?>" alt="">
            <div class="sidebar-brand-text mx-2"><?= e(setting('salon_name')) ?></div>
        </a>
        <hr class="sidebar-divider my-0">

        <?php foreach ($menu as $heading => $items):
            $visible = array_filter($items, static function (array $item) use ($currentAdmin): bool {
                return admin_can($item[4], $currentAdmin);
            });
            if (!$visible) {
                continue;
            }
            ?>
            <hr class="sidebar-divider">
            <div class="sidebar-heading"><?= e($heading) ?></div>
            <?php foreach ($visible as [$key, $href, $icon, $label]): ?>
                <li class="nav-item <?= $activeMenu === $key ? 'active' : '' ?>">
                    <a class="nav-link" href="<?= e(url('admin/' . $href)) ?>">
                        <i class="fas fa-fw <?= e($icon) ?>"></i>
                        <span><?= e($label) ?></span>
                        <?php if ($key === 'messages' && $unreadMessages > 0): ?><span class="badge badge-light ml-1"><?= $unreadMessages ?></span><?php endif; ?>
                    </a>
                </li>
            <?php endforeach; ?>
        <?php endforeach; ?>

        <hr class="sidebar-divider d-none d-md-block">
        <div class="text-center d-none d-md-inline">
            <button class="rounded-circle border-0" id="sidebarToggle" aria-label="Thu gọn menu"></button>
        </div>
    </ul>

    <div id="content-wrapper" class="d-flex flex-column">
        <div id="content">
            <nav class="navbar navbar-expand navbar-light bg-white topbar mb-4 static-top shadow">
                <button id="sidebarToggleTop" class="btn btn-link d-md-none rounded-circle mr-3" aria-label="Mở menu"><i class="fa fa-bars"></i></button>

                <ul class="navbar-nav ml-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="<?= e(url('index.php')) ?>" target="_blank" rel="noopener"><i class="far fa-eye"></i><span class="ml-1 d-none d-sm-inline">Xem website</span></a>
                    </li>
                    <div class="topbar-divider d-none d-sm-block"></div>
                    <li class="nav-item dropdown no-arrow">
                        <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                            <span class="mr-2 d-none d-lg-inline text-gray-600 small"><?= e($currentAdmin['full_name']) ?> <span class="text-gray-500">· <?= e(admin_role_label($currentAdmin['role'])) ?></span></span>
                            <?= render_avatar('avatars', $currentAdmin['avatar'], $currentAdmin['full_name'], 'topbar-avatar') ?>
                        </a>
                        <div class="dropdown-menu dropdown-menu-right shadow animated--grow-in" aria-labelledby="userDropdown">
                            <a class="dropdown-item" href="<?= e(url('admin/profile.php')) ?>"><i class="fas fa-user fa-sm fa-fw mr-2 text-gray-400"></i>Hồ sơ cá nhân</a>
                            <?php if (admin_can('settings', $currentAdmin)): ?>
                                <a class="dropdown-item" href="<?= e(url('admin/settings.php')) ?>"><i class="fas fa-cogs fa-sm fa-fw mr-2 text-gray-400"></i>Cài đặt</a>
                            <?php endif; ?>
                            <?php if (admin_can('activity', $currentAdmin)): ?>
                                <a class="dropdown-item" href="<?= e(url('admin/activity-log.php')) ?>"><i class="fas fa-list fa-sm fa-fw mr-2 text-gray-400"></i>Nhật ký hoạt động</a>
                            <?php endif; ?>
                            <div class="dropdown-divider"></div>
                            <a class="dropdown-item" href="#" data-toggle="modal" data-target="#logoutModal"><i class="fas fa-sign-out-alt fa-sm fa-fw mr-2 text-gray-400"></i>Đăng xuất</a>
                        </div>
                    </li>
                </ul>
            </nav>

            <div class="container-fluid">
                <?= render_flash() ?>
