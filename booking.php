<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/templates/form_helpers.php';

$categories = db_all(
    'SELECT c.category_id, c.category_name FROM service_categories c
     WHERE EXISTS (SELECT 1 FROM services s WHERE s.category_id = c.category_id)
     ORDER BY c.is_default, c.category_id'
);
$servicesByCategory = [];
foreach (db_all('SELECT * FROM services ORDER BY service_name') as $service) {
    $servicesByCategory[$service['category_id']][] = $service;
}
$employees = db_all('SELECT * FROM employees ORDER BY employee_id');

$old = [
    'services' => [], 'employee_id' => 0, 'date' => '', 'time' => '', 'note' => '',
    'full_name' => '', 'phone' => '', 'email' => '', 'step' => 0,
];
$errors = [];

// Từ trang Bảng giá: /booking.php?service=ID chọn sẵn 1 dịch vụ
if (!is_post() && ($preselect = (int) get('service', '0')) > 0) {
    $old['services'] = [$preselect];
}

if (is_post()) {
    verify_csrf();
    $old = [
        'services'    => array_map('intval', (array) ($_POST['services'] ?? [])),
        'employee_id' => (int) post('employee_id'),
        'date'        => post('date'),
        'time'        => post('time'),
        'note'        => post('note'),
        'full_name'   => post('full_name'),
        'phone'       => post('phone'),
        'email'       => post('email'),
        'step'        => 4,
    ];

    if (post('website') !== '') {
        // Ô ẩn (honeypot) chỉ bot mới điền
        flash('danger', 'Yêu cầu không hợp lệ.');
    } elseif (rate_limit_exceeded('booking', 5, 60)) {
        flash('danger', 'Bạn đã đặt lịch quá nhiều lần trong thời gian ngắn. Vui lòng thử lại sau hoặc gọi ' . format_phone(setting('salon_phone')) . '.');
    } else {
        $check  = booking_validate_customer($old['full_name'], $old['phone'], $old['email']);
        $errors = $check['errors'];

        if (!$errors) {
            $upload = save_uploaded_image('reference_image', 'references', UPLOAD_MAX_REFERENCE);
            if ($upload['error'] !== null) {
                $errors['reference_image'] = $upload['error'];
            } else {
                $result = booking_create($check['data'], $old['employee_id'], $old['services'], $old['date'], $old['time'], $old['note'], $upload['file']);
                if ($result['ok']) {
                    rate_limit_hit('booking');
                    $_SESSION['last_booking_id'] = (int) $result['appointment_id'];
                    redirect('booking-success.php');
                }
                delete_uploaded_file('references', $upload['file']);
                flash('danger', (string) $result['error']);
                $old['step'] = 3;
            }
        }
    }
}

$pageTitle = 'Đặt lịch làm tóc';
$extraScripts = ['assets/js/booking.js'];
require __DIR__ . '/templates/header.php';
?>
<section class="page-wrap">
    <div class="container" style="max-width: 860px;">
        <h1 class="page-title">Đặt lịch làm tóc</h1>
        <p class="text-muted mb-4">Không cần tạo tài khoản – chọn dịch vụ, stylist, khung giờ và để lại thông tin liên hệ là xong.</p>

        <ol class="stepper" id="stepper">
            <li class="active"><div class="dot">1</div><span class="label">Dịch vụ</span></li>
            <li><div class="dot">2</div><span class="label">Stylist</span></li>
            <li><div class="dot">3</div><span class="label">Ngày & giờ</span></li>
            <li><div class="dot">4</div><span class="label">Xác nhận</span></li>
        </ol>

        <form method="post" action="<?= e(url('booking.php')) ?>" enctype="multipart/form-data" id="bookingForm" novalidate>
            <?= csrf_field() ?>
            <input type="hidden" name="date" id="inputDate" value="">
            <input type="hidden" name="time" id="inputTime" value="">

            <div class="alert alert-danger d-none" id="stepError" role="alert"></div>

            <!-- BƯỚC 1: DỊCH VỤ -->
            <div class="booking-step active" data-step="1">
                <h2 class="h4 mb-2">1. Chọn dịch vụ</h2>
                <p class="text-muted">Bạn có thể chọn nhiều dịch vụ trong cùng một lần hẹn.</p>
                <?php foreach ($categories as $category): ?>
                    <h3 class="category-title"><?= e($category['category_name']) ?></h3>
                    <?php foreach ($servicesByCategory[$category['category_id']] ?? [] as $service): ?>
                        <label class="choice-card">
                            <input type="checkbox" name="services[]" value="<?= (int) $service['service_id'] ?>"
                                   data-name="<?= e($service['service_name']) ?>" data-price="<?= (int) $service['service_price'] ?>" data-duration="<?= (int) $service['service_duration'] ?>">
                            <span class="check"><i class="fas fa-check"></i></span>
                            <?php if ($thumb = upload_url('services', $service['image'])): ?>
                                <img class="thumb" src="<?= e($thumb) ?>" alt="">
                            <?php endif; ?>
                            <span class="grow">
                                <p class="title"><?= e($service['service_name']) ?></p>
                                <p class="sub"><?= e($service['service_description']) ?></p>
                            </span>
                            <span class="price">
                                <?= e(format_money($service['service_price'])) ?>
                                <small class="d-block text-muted font-weight-normal"><i class="far fa-clock"></i> <?= e(format_duration((int) $service['service_duration'])) ?></small>
                            </span>
                        </label>
                    <?php endforeach; ?>
                <?php endforeach; ?>
            </div>

            <!-- BƯỚC 2: STYLIST -->
            <div class="booking-step" data-step="2">
                <h2 class="h4 mb-3">2. Chọn stylist</h2>
                <div class="stylist-grid">
                    <?php foreach ($employees as $employee): ?>
                        <label class="stylist-card">
                            <input type="radio" name="employee_id" value="<?= (int) $employee['employee_id'] ?>" data-name="<?= e($employee['full_name']) ?>">
                            <?= render_avatar('avatars', $employee['avatar'], $employee['full_name']) ?>
                            <p class="title"><?= e($employee['full_name']) ?></p>
                            <p class="sub"><?= e($employee['position']) ?></p>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- BƯỚC 3: NGÀY GIỜ -->
            <div class="booking-step" data-step="3">
                <h2 class="h4 mb-2">3. Chọn ngày & giờ</h2>
                <p class="text-muted">Chỉ hiển thị các khung giờ còn trống, phù hợp với tổng thời lượng <strong id="durationLabel"></strong>.</p>
                <div id="slotsLoading" class="loading-note d-none"><img src="<?= e(url('assets/img/misc/loader.gif')) ?>" alt="" width="40"><br>Đang kiểm tra lịch trống…</div>
                <div id="slotsError" class="alert alert-danger d-none"></div>
                <div id="dayStrip" class="day-strip"></div>
                <div id="slotGrid" class="slot-grid"></div>
                <p id="slotsEmpty" class="text-muted d-none">Ngày này stylist không còn khung giờ trống. Vui lòng chọn ngày khác hoặc stylist khác.</p>
            </div>

            <!-- BƯỚC 4: XÁC NHẬN -->
            <div class="booking-step" data-step="4">
                <h2 class="h4 mb-3">4. Xác nhận thông tin</h2>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <div class="panel h-100">
                            <h3 class="h6 text-primary">Chi tiết lịch hẹn</h3>
                            <ul class="summary-list" id="summaryList"></ul>
                        </div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <div class="panel h-100">
                            <h3 class="h6 text-primary">Thông tin liên hệ</h3>
                            <div class="form-group">
                                <label for="full_name">Họ và tên</label>
                                <input type="text" id="full_name" name="full_name" class="form-control<?= invalid_class($errors, 'full_name') ?>" value="<?= e($old['full_name']) ?>" placeholder="Nguyễn Thu Hà" maxlength="100" autocomplete="name" required>
                                <?= field_error($errors, 'full_name') ?>
                            </div>
                            <div class="form-group">
                                <label for="phone">Số điện thoại</label>
                                <input type="tel" id="phone" name="phone" class="form-control<?= invalid_class($errors, 'phone') ?>" value="<?= e($old['phone']) ?>" placeholder="0912 345 678" maxlength="20" autocomplete="tel" inputmode="tel" required>
                                <span class="field-hint">Số di động Việt Nam. <?= e(PHONE_FORMAT_HINT) ?></span>
                                <?= field_error($errors, 'phone') ?>
                            </div>
                            <div class="form-group mb-0">
                                <label for="email">Email</label>
                                <input type="email" id="email" name="email" class="form-control<?= invalid_class($errors, 'email') ?>" value="<?= e($old['email']) ?>" placeholder="ban@example.com" maxlength="100" autocomplete="email" required>
                                <?= field_error($errors, 'email') ?>
                            </div>
                            <div class="hp-field" aria-hidden="true"><label>Website <input type="text" name="website" tabindex="-1" autocomplete="off" value=""></label></div>
                        </div>
                    </div>
                </div>
                <div class="panel mt-2">
                    <div class="form-group">
                        <label for="note">Ghi chú cho stylist <span class="text-muted">(không bắt buộc)</span></label>
                        <textarea id="note" name="note" rows="3" class="form-control" maxlength="500" placeholder="Ví dụ: tóc đã nhuộm 2 tháng trước, muốn giữ độ dài ngang vai…"><?= e($old['note']) ?></textarea>
                    </div>
                    <div class="form-group mb-0">
                        <label for="reference_image">Ảnh kiểu tóc tham khảo <span class="text-muted">(không bắt buộc, JPG/PNG/WEBP, tối đa 5 MB)</span></label>
                        <input type="file" id="reference_image" name="reference_image" class="form-control-file" accept="image/jpeg,image/png,image/webp" data-preview="#referencePreview">
                        <img id="referencePreview" class="ref-thumb mt-2" alt="Xem trước ảnh tham khảo" hidden style="max-height:160px;border-radius:12px;">
                        <?= field_error($errors, 'reference_image') ?>
                        <small class="field-hint d-block">Ảnh chỉ hiển thị với salon, không công khai.</small>
                    </div>
                </div>
            </div>

            <!-- THANH ĐIỀU HƯỚNG -->
            <div class="booking-bar">
                <div class="totals" id="totalsBar">Chưa chọn dịch vụ</div>
                <div>
                    <button type="button" class="btn btn-outline-primary mr-1" id="btnPrev" hidden>Quay lại</button>
                    <button type="button" class="btn btn-primary" id="btnNext">Tiếp tục</button>
                    <button type="submit" class="btn btn-primary" id="btnSubmit" hidden><i class="far fa-calendar-check"></i> Xác nhận đặt lịch</button>
                </div>
            </div>
        </form>
    </div>
</section>
<script>window.BOOKING_OLD = <?= json_encode($old, JSON_UNESCAPED_UNICODE) ?>;</script>
<?php require __DIR__ . '/templates/footer.php'; ?>
