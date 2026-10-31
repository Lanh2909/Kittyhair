<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

$categories = db_all(
    'SELECT c.category_id, c.category_name FROM service_categories c
     WHERE EXISTS (SELECT 1 FROM services s WHERE s.category_id = c.category_id)
     ORDER BY c.is_default, c.category_id'
);
$servicesByCategory = [];
foreach (db_all('SELECT * FROM services ORDER BY service_price') as $service) {
    $servicesByCategory[$service['category_id']][] = $service;
}
$employees = db_all('SELECT * FROM employees ORDER BY employee_id');

$heroSlides = [
    ['img' => 'hero-1.jpg', 'eyebrow' => 'Salon tóc nữ chuyên nghiệp', 'title' => 'Mái tóc đẹp – Tự tin tỏa sáng mỗi ngày', 'text' => 'Đội ngũ stylist tận tâm cùng sản phẩm chăm sóc tóc cao cấp, thiết kế kiểu tóc riêng cho từng khuôn mặt.'],
    ['img' => 'hero-2.jpg', 'eyebrow' => 'Nhuộm màu thời thượng', 'title' => 'Sắc màu cá tính, bền màu và an toàn', 'text' => 'Từ tông nâu tự nhiên đến balayage, highlight – nhuộm với công nghệ bảo vệ liên kết tóc.'],
    ['img' => 'hero-3.jpg', 'eyebrow' => 'Tóc cô dâu & dự tiệc', 'title' => 'Rạng rỡ trong những khoảnh khắc đáng nhớ', 'text' => 'Búi, tết, uốn lọn tạo kiểu cho tiệc cưới, sự kiện và chụp ảnh – đặt lịch trước để được ưu tiên.'],
];
$featured = [
    ['fa-cut', 'Cắt và Tạo kiểu', 'Tư vấn kiểu tóc hợp khuôn mặt, cắt layer, tỉa mái mềm mại và hiện đại.'],
    ['fa-palette', 'Nhuộm tóc', 'Nhuộm thời trang, balayage, highlight với thuốc nhuộm dịu nhẹ, lên màu chuẩn.'],
    ['fa-magic', 'Uốn và Duỗi', 'Uốn gợn sóng Hàn Quốc, duỗi Keratin cho mái tóc bồng bềnh hoặc suôn mượt.'],
    ['fa-spa', 'Phục hồi và Spa', 'Hấp collagen, ủ Keratin, gội đầu dưỡng sinh giúp tóc khỏe và óng ả.'],
];
$reviews = [
    ['review-1.jpg', 'Nguyễn Thu Hà', 'Stylist tư vấn rất kỹ, kiểu layer mới hợp với mình hơn mình tưởng. Đặt lịch online tiện, đến là được phục vụ ngay.'],
    ['review-2.jpg', 'Trần Bảo Ngọc', 'Nhuộm màu lên chuẩn, tóc vẫn mềm mượt. Không gian salon sạch sẽ, nhân viên nhẹ nhàng và chuyên nghiệp.'],
    ['review-3.jpg', 'Lê Diệu Linh', 'Làm tóc dự tiệc cưới bạn thân xinh hơn mong đợi. Chắc chắn sẽ quay lại làm liệu trình phục hồi.'],
];

require __DIR__ . '/templates/header.php';
?>

<!-- TRANG CHỦ / BANNER -->
<section class="hero" id="home-section">
    <div id="heroCarousel" class="carousel slide carousel-fade" data-ride="carousel" data-interval="6000">
        <ol class="carousel-indicators">
            <?php foreach ($heroSlides as $i => $slide): ?>
                <li data-target="#heroCarousel" data-slide-to="<?= $i ?>" class="<?= $i === 0 ? 'active' : '' ?>"></li>
            <?php endforeach; ?>
        </ol>
        <div class="carousel-inner">
            <?php foreach ($heroSlides as $i => $slide): ?>
                <div class="carousel-item <?= $i === 0 ? 'active' : '' ?>">
                    <img src="<?= e(url('assets/img/hero/' . $slide['img'])) ?>" alt="<?= e($slide['title']) ?>">
                    <div class="hero-caption">
                        <div class="container">
                            <span class="eyebrow"><?= e($slide['eyebrow']) ?></span>
                            <h1><?= e($slide['title']) ?></h1>
                            <p><?= e($slide['text']) ?></p>
                            <a href="<?= e(url('booking.php')) ?>" class="btn btn-primary btn-lg">Đặt lịch ngay</a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- GIỚI THIỆU -->
<section id="about" class="section-pad">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-lg-6">
                <div class="about-collage">
                    <img class="a1" src="<?= e(url('assets/img/about/about-1.jpg')) ?>" alt="Stylist đang tạo kiểu tóc cho khách">
                    <img class="a2" src="<?= e(url('assets/img/about/about-2.jpg')) ?>" alt="Dụng cụ cắt tóc chuyên nghiệp">
                    <img class="a3" src="<?= e(url('assets/img/about/about-3.jpg')) ?>" alt="Máy sấy và sản phẩm chăm sóc tóc">
                </div>
            </div>
            <div class="col-lg-6">
                <div class="section-heading text-left mb-3">
                    <span class="eyebrow">Về chúng tôi</span>
                    <h2><?= e(setting('salon_name')) ?> – nơi mái tóc được yêu thương</h2>
                </div>
                <p>Với hơn 10 năm kinh nghiệm trong lĩnh vực làm đẹp, <?= e(setting('salon_name')) ?> là salon tóc dành riêng cho phái đẹp. Chúng tôi tin rằng mỗi mái tóc đều có một câu chuyện, và nhiệm vụ của stylist là giúp bạn kể câu chuyện đó thật đẹp.</p>
                <ul class="about-points">
                    <li>Stylist được đào tạo bài bản, cập nhật xu hướng quốc tế</li>
                    <li>Sản phẩm chính hãng, an toàn cho tóc và da đầu</li>
                    <li>Đặt lịch online, không phải chờ đợi</li>
                    <li>Không gian sạch sẽ, riêng tư và thư giãn</li>
                </ul>
                <a href="<?= e(url('booking.php')) ?>" class="btn btn-primary mt-3">Đặt lịch hẹn</a>
            </div>
        </div>
    </div>
</section>

<!-- DỊCH VỤ NỔI BẬT -->
<section id="services" class="section-pad services-section">
    <div class="container">
        <div class="section-heading">
            <span class="eyebrow">Salon tóc nữ</span>
            <h2>Dịch vụ của chúng tôi</h2>
            <div class="divider"></div>
        </div>
        <div class="row">
            <?php foreach ($featured as [$icon, $title, $desc]): ?>
                <div class="col-lg-3 col-md-6 mb-4">
                    <div class="service-box">
                        <div class="icon"><i class="fas <?= e($icon) ?>"></i></div>
                        <h3><?= e($title) ?></h3>
                        <p><?= e($desc) ?></p>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- BẢNG GIÁ -->
<section id="pricing" class="section-pad">
    <div class="container">
        <div class="section-heading">
            <span class="eyebrow">Minh bạch – rõ ràng</span>
            <h2>Bảng giá dịch vụ</h2>
            <div class="divider"></div>
        </div>
        <div class="row">
            <?php foreach ($categories as $category): ?>
                <div class="col-lg-4 col-md-6 mb-4">
                    <div class="price-card">
                        <h3><?= e($category['category_name']) ?></h3>
                        <ul class="price-list">
                            <?php foreach (array_slice($servicesByCategory[$category['category_id']] ?? [], 0, 4) as $service): ?>
                                <li>
                                    <?php if ($thumb = upload_url('services', $service['image'])): ?>
                                        <img class="thumb" src="<?= e($thumb) ?>" alt="<?= e($service['service_name']) ?>">
                                    <?php endif; ?>
                                    <div>
                                        <p class="name"><?= e($service['service_name']) ?></p>
                                        <p class="desc"><?= e($service['service_description']) ?></p>
                                    </div>
                                    <div class="meta">
                                        <span class="price"><?= e(format_money($service['service_price'])) ?></span>
                                        <span class="duration"><i class="far fa-clock"></i> <?= e(format_duration((int) $service['service_duration'])) ?></span>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                        <?php if (count($servicesByCategory[$category['category_id']] ?? []) > 4): ?>
                            <a class="d-block text-center mt-3 font-weight-bold" href="<?= e(url('services.php?category=' . (int) $category['category_id'])) ?>">Xem tất cả (<?= count($servicesByCategory[$category['category_id']]) ?>) &rarr;</a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <div class="text-center mt-2"><a href="<?= e(url('services.php')) ?>" class="btn btn-outline-primary">Xem toàn bộ bảng giá</a></div>
    </div>
</section>

<!-- KÊU GỌI ĐẶT LỊCH -->
<section class="book-cta" id="booking">
    <div class="container">
        <div class="row">
            <div class="col-lg-6 offset-lg-6">
                <h2>Đặt lịch chỉ trong 1 phút</h2>
                <p>Chọn dịch vụ, chọn stylist yêu thích và khung giờ còn trống – hệ thống tự kiểm tra để không bao giờ trùng lịch.</p>
                <a href="<?= e(url('booking.php')) ?>" class="btn btn-light btn-lg text-primary mr-2 mb-2"><i class="far fa-calendar-check"></i> Đặt lịch ngay</a>
                <?php if (setting('salon_phone') !== ''): ?>
                    <a class="hotline mb-2 d-inline-block" href="tel:<?= e(setting('salon_phone')) ?>"><i class="fas fa-phone-alt"></i> <?= e(format_phone(setting('salon_phone'))) ?></a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>

<!-- THƯ VIỆN -->
<section id="gallery" class="section-pad">
    <div class="container">
        <div class="section-heading">
            <span class="eyebrow">Cảm hứng kiểu tóc</span>
            <h2>Thư viện kiểu tóc</h2>
            <div class="divider"></div>
        </div>
        <div class="row">
            <?php for ($i = 1; $i <= 8; $i++): ?>
                <div class="col-lg-3 col-md-4 col-6 mb-4">
                    <div class="gallery-item"><img src="<?= e(url("assets/img/gallery/gallery-$i.jpg")) ?>" alt="Kiểu tóc nữ <?= $i ?>" loading="lazy"></div>
                </div>
            <?php endfor; ?>
        </div>
    </div>
</section>

<!-- ĐỘI NGŨ -->
<section id="team" class="section-pad services-section">
    <div class="container">
        <div class="section-heading">
            <span class="eyebrow">Những bàn tay vàng</span>
            <h2>Đội ngũ stylist</h2>
            <div class="divider"></div>
        </div>
        <div class="row justify-content-center">
            <?php foreach ($employees as $employee): ?>
                <div class="col-lg-3 col-md-6 col-sm-6 mb-4">
                    <div class="team-card">
                        <div class="photo"><?= render_avatar('avatars', $employee['avatar'], $employee['full_name']) ?></div>
                        <div class="info">
                            <h3><?= e($employee['full_name']) ?></h3>
                            <div class="role"><?= e($employee['position']) ?></div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- ĐÁNH GIÁ -->
<section id="reviews" class="section-pad reviews-section">
    <div class="container">
        <div class="section-heading">
            <span class="eyebrow">Khách hàng nói gì</span>
            <h2>Niềm vui của khách hàng</h2>
            <div class="divider"></div>
        </div>
        <div id="reviewCarousel" class="carousel slide" data-ride="carousel">
            <ol class="carousel-indicators">
                <?php foreach ($reviews as $i => $review): ?>
                    <li data-target="#reviewCarousel" data-slide-to="<?= $i ?>" class="<?= $i === 0 ? 'active' : '' ?>"></li>
                <?php endforeach; ?>
            </ol>
            <div class="carousel-inner">
                <?php foreach ($reviews as $i => [$img, $name, $quote]): ?>
                    <div class="carousel-item <?= $i === 0 ? 'active' : '' ?>">
                        <div class="review-card">
                            <img src="<?= e(url('assets/img/misc/' . $img)) ?>" alt="<?= e($name) ?>">
                            <div class="stars mb-2"><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i></div>
                            <blockquote>“<?= e($quote) ?>”</blockquote>
                            <strong class="text-primary"><?= e($name) ?></strong>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            <a class="carousel-control-prev" href="#reviewCarousel" role="button" data-slide="prev"><span class="carousel-control-prev-icon" aria-hidden="true"></span><span class="sr-only">Trước</span></a>
            <a class="carousel-control-next" href="#reviewCarousel" role="button" data-slide="next"><span class="carousel-control-next-icon" aria-hidden="true"></span><span class="sr-only">Sau</span></a>
        </div>
    </div>
</section>

<!-- LIÊN HỆ -->
<section id="contact-us" class="section-pad contact-section">
    <div class="container">
        <div class="section-heading">
            <span class="eyebrow">Kết nối với chúng tôi</span>
            <h2>Liên hệ & Hỗ trợ</h2>
            <div class="divider"></div>
        </div>
        <div class="row">
            <div class="col-lg-5 mb-4">
                <div class="contact-info">
                    <p>Bạn cần tư vấn kiểu tóc hoặc liệu trình phù hợp? Hãy gửi tin nhắn, salon sẽ phản hồi sớm nhất.</p>
                    <ul>
                        <?php if (setting('salon_address') !== ''): ?><li><i class="fas fa-map-marker-alt"></i><div><strong>Địa chỉ</strong><br><?= e(setting('salon_address')) ?></div></li><?php endif; ?>
                        <?php if (setting('salon_phone') !== ''): ?><li><i class="fas fa-phone-alt"></i><div><strong>Hotline</strong><br><a href="tel:<?= e(setting('salon_phone')) ?>"><?= e(format_phone(setting('salon_phone'))) ?></a></div></li><?php endif; ?>
                        <?php if (setting('salon_email') !== ''): ?><li><i class="far fa-envelope"></i><div><strong>Email</strong><br><a href="mailto:<?= e(setting('salon_email')) ?>"><?= e(setting('salon_email')) ?></a></div></li><?php endif; ?>
                        <li><i class="far fa-clock"></i><div><strong>Giờ mở cửa</strong><br>Hằng ngày: <?= e(setting('open_time')) ?> – <?= e(setting('close_time')) ?></div></li>
                    </ul>
                </div>
            </div>
            <div class="col-lg-7">
                <form id="contactForm" class="contact-form" novalidate>
                    <?= csrf_field() ?>
                    <div class="form-row">
                        <div class="form-group col-md-6"><input type="text" name="name" class="form-control" placeholder="Họ và tên" maxlength="100" required></div>
                        <div class="form-group col-md-6"><input type="email" name="email" class="form-control" placeholder="Email" maxlength="100" required></div>
                    </div>
                    <div class="form-group"><input type="text" name="subject" class="form-control" placeholder="Tiêu đề" maxlength="150" required></div>
                    <div class="form-group"><textarea name="message" rows="5" class="form-control" placeholder="Nội dung tin nhắn" maxlength="2000" required></textarea></div>
                    <button type="submit" class="btn btn-primary">Gửi tin nhắn</button>
                    <div id="contactStatus" class="mt-3" role="status"></div>
                </form>
            </div>
        </div>
    </div>
</section>

<?php require __DIR__ . '/templates/footer.php'; ?>
