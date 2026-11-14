<?php

declare(strict_types=1);

use App\Core\Flash;
use App\Helpers\Format;

/**
 * Layout chung của site khách hàng: head, thanh điều hướng, flash, <main>, chân trang, nạp JavaScript.
 */

$salonName       = $settings->get('salon_name');
$pageTitle       = isset($pageTitle) ? $pageTitle . ' | ' . $salonName : $salonName . ' – ' . $settings->get('salon_slogan');
$pageDescription = $pageDescription ?? ($salonName . ' – đặt lịch làm tóc nữ trực tuyến: cắt, nhuộm, uốn, duỗi, phục hồi tóc cùng đội ngũ stylist chuyên nghiệp.');

$tiktokIcon = '<svg viewBox="0 0 24 24" width="16" height="16" fill="currentColor" aria-hidden="true" focusable="false"><path d="M12.525.02c1.31-.02 2.61-.01 3.91-.02.08 1.53.63 3.09 1.75 4.17 1.12 1.11 2.7 1.62 4.24 1.79v4.03c-1.44-.05-2.89-.35-4.2-.97-.57-.26-1.1-.59-1.62-.93-.01 2.92.01 5.84-.02 8.75-.08 1.4-.54 2.79-1.35 3.94-1.31 1.92-3.58 3.17-5.91 3.21-1.43.08-2.86-.31-4.08-1.03-2.02-1.19-3.44-3.37-3.65-5.71-.02-.5-.03-1-.01-1.49.18-1.9 1.12-3.72 2.58-4.96 1.66-1.44 3.98-2.13 6.15-1.72.02 1.48-.04 2.96-.04 4.44-.99-.32-2.15-.23-3.02.37-.63.41-1.11 1.04-1.36 1.75-.21.51-.15 1.07-.14 1.61.24 1.64 1.82 3.02 3.5 2.87 1.12-.01 2.19-.66 2.77-1.61.19-.33.4-.67.41-1.06.1-1.79.06-3.57.07-5.36.01-4.03-.01-8.05.02-12.07z"/></svg>';
$socials = [
    'facebook_url'  => ['<i class="fab fa-facebook-f"></i>', 'Facebook'],
    'instagram_url' => ['<i class="fab fa-instagram"></i>', 'Instagram'],
    'tiktok_url'    => [$tiktokIcon, 'TikTok'],
];
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

<?php if ($flashHtml = Flash::render()): ?>
    <div class="container flash-wrap pt-3"><?= $flashHtml ?></div>
<?php endif; ?>

<main>
<?= $content ?>
</main>

<footer class="site-footer">
    <div class="container">
        <div class="row">
            <div class="col-lg-4 col-md-6 mb-4">
                <img class="footer-logo" src="<?= e(url('assets/img/logo/logo-horizontal-light.svg')) ?>" alt="<?= e($settings->get('salon_name')) ?>">
                <p><?= e($settings->get('salon_slogan')) ?>. Chúng tôi mang đến trải nghiệm làm tóc chuyên nghiệp, an toàn và đầy cảm hứng dành riêng cho phái đẹp.</p>
                <div class="social-links">
                    <?php foreach ($socials as $key => [$icon, $label]): ?>
                        <?php if ($settings->get($key) !== ''): ?>
                            <a href="<?= e($settings->get($key)) ?>" target="_blank" rel="noopener" aria-label="<?= e($label) ?>" title="<?= e($label) ?>"><?= $icon ?></a>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="col-lg-3 col-md-6 mb-4">
                <h4>Liên hệ</h4>
                <ul>
                    <?php if ($settings->get('salon_address') !== ''): ?><li><i class="fas fa-map-marker-alt fa-fw mr-2"></i><?= e($settings->get('salon_address')) ?></li><?php endif; ?>
                    <?php if ($settings->get('salon_phone') !== ''): ?><li><i class="fas fa-phone-alt fa-fw mr-2"></i><a href="tel:<?= e($settings->get('salon_phone')) ?>"><?= e(Format::phone($settings->get('salon_phone'))) ?></a></li><?php endif; ?>
                    <?php if ($settings->get('salon_email') !== ''): ?><li><i class="far fa-envelope fa-fw mr-2"></i><a href="mailto:<?= e($settings->get('salon_email')) ?>"><?= e($settings->get('salon_email')) ?></a></li><?php endif; ?>
                </ul>
            </div>
            <div class="col-lg-2 col-md-6 mb-4">
                <h4>Giờ mở cửa</h4>
                <ul>
                    <li>Thứ Hai – Chủ Nhật</li>
                    <li><?= e($settings->get('open_time')) ?> – <?= e($settings->get('close_time')) ?></li>
                </ul>
            </div>
            <div class="col-lg-3 col-md-6 mb-4">
                <h4>Liên kết nhanh</h4>
                <ul>
                    <li><a href="<?= e(url('booking.php')) ?>">Đặt lịch làm tóc</a></li>
                    <li><a href="<?= e(url('services.php')) ?>">Bảng giá dịch vụ</a></li>
                    <li><a href="<?= e(url('index.php#contact-us')) ?>">Gửi tin nhắn cho salon</a></li>
                </ul>
            </div>
        </div>
    </div>
    <div class="footer-bottom">
        &copy; <?= date('Y') ?> <?= e($settings->get('salon_name')) ?>. Đã đăng ký bản quyền.
    </div>
</footer>

<script>window.KH_BASE = <?= json_encode(url('/')) ?>;</script>
<script src="<?= e(CDN_JQUERY_JS) ?>"></script>
<script src="<?= e(CDN_BOOTSTRAP_JS) ?>"></script>
<script src="<?= e(url('assets/js/kitty.js')) ?>"></script>
<?php foreach ($extraScripts ?? [] as $script): ?>
<script src="<?= e(url($script)) ?>"></script>
<?php endforeach; ?>
</body>
</html>
