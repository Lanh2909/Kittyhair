<?php

declare(strict_types=1);

/**
 * Đầu trang + thanh điều hướng của site khách hàng.
 */

$salonName     = setting('salon_name');
$pageTitle     = isset($pageTitle) ? $pageTitle . ' | ' . $salonName : $salonName . ' – ' . setting('salon_slogan');
$pageDescription = $pageDescription ?? ($salonName . ' – đặt lịch làm tóc nữ trực tuyến: cắt, nhuộm, uốn, duỗi, phục hồi tóc cùng đội ngũ stylist chuyên nghiệp.');
$isHome = basename($_SERVER['SCRIPT_NAME']) === 'index.php';
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="<?= e($pageDescription) ?>">
    <title><?= e($pageTitle) ?></title>

    <link rel="icon" href="<?= e(url('favicon.ico')) ?>" sizes="any">
    <link rel="icon" type="image/png" href="<?= e(url('assets/img/logo/favicon-32.png')) ?>">
    <link rel="apple-touch-icon" href="<?= e(url('assets/img/logo/apple-touch-icon.png')) ?>">

    <link rel="stylesheet" href="<?= e(CDN_BOOTSTRAP_CSS) ?>">
    <link rel="stylesheet" href="<?= e(CDN_FONTAWESOME_CSS) ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Be+Vietnam+Pro:wght@400;500;600;700&family=Playfair+Display:wght@600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= e(url('assets/css/kitty.css')) ?>">
</head>
<body>

<header>
    <nav class="navbar navbar-expand-lg site-nav sticky-top">
        <div class="container">
            <a class="navbar-brand" href="<?= e(url('index.php')) ?>">
                <img src="<?= e(url('assets/img/logo/logo-horizontal.svg')) ?>" alt="<?= e($salonName) ?>">
            </a>
            <button class="navbar-toggler border-0" type="button" data-toggle="collapse" data-target="#mainNav" aria-controls="mainNav" aria-expanded="false" aria-label="Mở menu">
                <i class="fas fa-bars text-primary"></i>
            </button>

            <div class="collapse navbar-collapse" id="mainNav">
                <ul class="navbar-nav mx-auto">
                    <li class="nav-item"><a class="nav-link" href="<?= e(url('index.php#home-section')) ?>">Trang chủ</a></li>
                    <li class="nav-item"><a class="nav-link" href="<?= e(url('index.php#about')) ?>">Giới thiệu</a></li>
                    <li class="nav-item"><a class="nav-link" href="<?= e(url('index.php#services')) ?>">Dịch vụ</a></li>
                    <li class="nav-item"><a class="nav-link" href="<?= e(url('services.php')) ?>">Bảng giá</a></li>
                    <li class="nav-item"><a class="nav-link" href="<?= e(url('index.php#gallery')) ?>">Thư viện</a></li>
                    <li class="nav-item"><a class="nav-link" href="<?= e(url('index.php#team')) ?>">Đội ngũ</a></li>
                    <li class="nav-item"><a class="nav-link" href="<?= e(url('index.php#contact-us')) ?>">Liên hệ</a></li>
                </ul>

                <div class="d-flex align-items-center flex-wrap">
                    <a href="<?= e(url('booking.php')) ?>" class="btn btn-primary btn-sm my-1"><i class="far fa-calendar-check"></i> Đặt lịch</a>
                </div>
            </div>
        </div>
    </nav>
</header>

<?php if ($flashHtml = render_flash()): ?>
    <div class="container flash-wrap pt-3"><?= $flashHtml ?></div>
<?php endif; ?>

<main>
