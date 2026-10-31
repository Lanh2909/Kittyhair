<?php

declare(strict_types=1);

/**
 * Nghiệp vụ đặt lịch.
 *
 * Nguyên tắc chống trùng lịch:
 *  - Một khoảng [start, end) trùng với lịch khác khi: start < end_khác AND end > start_khác
 *    (lịch nối tiếp nhau, kết thúc đúng lúc bắt đầu, KHÔNG bị coi là trùng).
 *  - Nhân viên không thể nhận 2 lịch cùng lúc; một khách (theo email) không thể có 2 lịch cùng lúc.
 *  - Khi tạo lịch, khóa dòng nhân viên/khách (SELECT ... FOR UPDATE) trong transaction rồi
 *    kiểm tra lại toàn bộ, nên 2 người đặt cùng lúc cũng không thể trùng nhau.
 */

const BOOKING_MAX_SERVICES = 8;

/** Lấy danh sách dịch vụ hợp lệ theo id (bỏ id sai/trùng), giữ nguyên thứ tự. */
function booking_services_by_ids(array $ids): array
{
    $ids = array_values(array_unique(array_filter(array_map('intval', $ids), static function (int $id): bool {
        return $id > 0;
    })));
    if (!$ids || count($ids) > BOOKING_MAX_SERVICES) {
        return [];
    }
    $marks = implode(',', array_fill(0, count($ids), '?'));
    $rows  = db_all("SELECT * FROM services WHERE service_id IN ($marks) ORDER BY service_id", $ids);
    return count($rows) === count($ids) ? $rows : [];
}

function booking_total_duration(array $services): int
{
    return (int) array_sum(array_column($services, 'service_duration'));
}

function booking_total_price(array $services): float
{
    return (float) array_sum(array_column($services, 'service_price'));
}

/** "HH:MM[:SS]" -> số phút kể từ 00:00. */
function time_to_minutes(string $time): int
{
    return (int) substr($time, 0, 2) * 60 + (int) substr($time, 3, 2);
}

function minutes_to_time(int $minutes): string
{
    return sprintf('%02d:%02d', intdiv($minutes, 60), $minutes % 60);
}

/** Các khoảng bận (phút trong ngày) của nhân viên hoặc khách trong 1 ngày. */
function booking_busy_intervals(string $column, int $id, string $date): array
{
    if (!in_array($column, ['employee_id', 'client_id'], true)) {
        throw new InvalidArgumentException('Cột không hợp lệ.');
    }
    $rows = db_all(
        "SELECT start_time, end_time_expected FROM appointments
         WHERE $column = ? AND canceled = 0 AND DATE(start_time) = ?",
        [$id, $date]
    );
    $out = [];
    foreach ($rows as $row) {
        $out[] = [time_to_minutes(substr($row['start_time'], 11)), time_to_minutes(substr($row['end_time_expected'], 11))];
    }
    return $out;
}

function booking_overlaps(int $start, int $end, array $intervals): bool
{
    foreach ($intervals as [$busyStart, $busyEnd]) {
        if ($start < $busyEnd && $end > $busyStart) {
            return true;
        }
    }
    return false;
}

/**
 * Sinh các khung giờ còn trống của 1 nhân viên trong 1 ngày.
 * @return array<int,array{start:string,end:string}>
 */
function booking_generate_slots(int $employeeId, string $date, int $duration, ?int $clientId = null): array
{
    if ($duration <= 0 || !is_valid_date($date)) {
        return [];
    }
    $today = date('Y-m-d');
    $maxDay = date('Y-m-d', strtotime('+' . (max(1, (int) setting('booking_days_ahead')) - 1) . ' days'));
    if ($date < $today || $date > $maxDay) {
        return [];
    }

    $schedule = db_row(
        'SELECT from_hour, to_hour FROM employees_schedule WHERE employee_id = ? AND day_id = ?',
        [$employeeId, (int) date('N', strtotime($date))]
    );
    if ($schedule === null) {
        return [];
    }

    $open  = max(time_to_minutes($schedule['from_hour']), time_to_minutes(setting('open_time')));
    $close = min(time_to_minutes($schedule['to_hour']), time_to_minutes(setting('close_time')));
    $step  = max(5, (int) setting('slot_step'));

    $earliest = 0;
    if ($date === $today) {
        $earliest = time_to_minutes(date('H:i')) + max(0, (int) setting('min_notice_minutes'));
    }

    $employeeBusy = booking_busy_intervals('employee_id', $employeeId, $date);
    $clientBusy   = $clientId ? booking_busy_intervals('client_id', $clientId, $date) : [];

    $slots = [];
    for ($start = $open; $start + $duration <= $close; $start += $step) {
        $end = $start + $duration;
        if ($start < $earliest || booking_overlaps($start, $end, $employeeBusy) || booking_overlaps($start, $end, $clientBusy)) {
            continue;
        }
        $slots[] = ['start' => minutes_to_time($start), 'end' => minutes_to_time($end)];
    }
    return $slots;
}

/**
 * Các ngày trong khung đặt lịch kèm khung giờ trống.
 * @return array<int,array{date:string,weekday:string,label:string,slots:array}>
 */
function booking_available_days(int $employeeId, int $duration, ?int $clientId = null): array
{
    $days = [];
    $total = max(1, (int) setting('booking_days_ahead'));
    for ($i = 0; $i < $total; $i++) {
        $ts   = strtotime("+$i days");
        $date = date('Y-m-d', $ts);
        $days[] = [
            'date'    => $date,
            'weekday' => $i === 0 ? 'Hôm nay' : short_weekday_name((int) date('N', $ts)),
            'label'   => date('d/m', $ts),
            'slots'   => booking_generate_slots($employeeId, $date, $duration, $clientId),
        ];
    }
    return $days;
}

/**
 * Kiểm tra thông tin liên hệ khách nhập khi đặt lịch.
 * @return array{data:array,errors:array<string,string>} data đã chuẩn hóa (full_name, phone, email)
 */
function booking_validate_customer(string $name, string $phoneInput, string $email): array
{
    $errors = [];
    $phone  = normalize_phone($phoneInput);
    $email  = mb_strtolower(trim($email));

    if (!is_valid_full_name($name)) {
        $errors['full_name'] = 'Họ tên không hợp lệ (2–100 ký tự, chỉ gồm chữ cái).';
    }
    if ($phone === null) {
        $errors['phone'] = phone_error_message();
    }
    if (!is_valid_email($email)) {
        $errors['email'] = 'Email không hợp lệ.';
    }
    return ['data' => ['full_name' => $name, 'phone' => (string) $phone, 'email' => $email], 'errors' => $errors];
}

/**
 * Tìm khách theo email (đã có thì cập nhật họ tên/SĐT mới nhất, chưa có thì tạo mới) và KHÓA dòng đó.
 * Phải gọi trong transaction. Xử lý cả trường hợp 2 yêu cầu cùng tạo 1 email mới.
 */
function booking_upsert_client(array $customer): int
{
    $find = static function () use ($customer): ?array {
        return db_row('SELECT client_id FROM clients WHERE email = ? FOR UPDATE', [$customer['email']]);
    };

    $row = $find();
    if ($row === null) {
        try {
            db_query('INSERT INTO clients (full_name, phone_number, email) VALUES (?, ?, ?)', [$customer['full_name'], $customer['phone'], $customer['email']]);
            return (int) db()->lastInsertId();
        } catch (PDOException $e) {
            if ($e->getCode() !== '23000') {
                throw $e;
            }
            $row = $find();   // yêu cầu khác vừa tạo khách này
            if ($row === null) {
                throw $e;
            }
        }
    }
    db_query('UPDATE clients SET full_name = ?, phone_number = ? WHERE client_id = ?', [$customer['full_name'], $customer['phone'], $row['client_id']]);
    return (int) $row['client_id'];
}

/**
 * Tạo lịch hẹn cho khách vãng lai.
 * @param array $customer ['full_name','phone','email'] đã qua booking_validate_customer()
 * @return array{ok:bool,error:?string,appointment_id:?int}
 */
function booking_create(array $customer, int $employeeId, array $serviceIds, string $date, string $time, string $note, ?string $referenceImage): array
{
    $fail = static function (string $msg): array {
        return ['ok' => false, 'error' => $msg, 'appointment_id' => null];
    };

    $services = booking_services_by_ids($serviceIds);
    if (!$services) {
        return $fail('Vui lòng chọn ít nhất một dịch vụ hợp lệ.');
    }
    if (!is_valid_time($time) || !is_valid_date($date)) {
        return $fail('Ngày hoặc giờ không hợp lệ.');
    }
    if (mb_strlen($note) > 500) {
        return $fail('Ghi chú tối đa 500 ký tự.');
    }

    $duration = booking_total_duration($services);
    $pdo = db();

    try {
        $pdo->beginTransaction();

        // Thứ tự khóa cố định (nhân viên -> khách) để tránh deadlock; tuần tự hóa các lượt đặt cùng lúc.
        $employee = db_row('SELECT employee_id, full_name FROM employees WHERE employee_id = ? FOR UPDATE', [$employeeId]);
        if ($employee === null) {
            $pdo->rollBack();
            return $fail('Nhân viên không tồn tại.');
        }
        $clientId = booking_upsert_client($customer);

        // Một hàm duy nhất kiểm tra: giờ làm việc, giờ mở cửa, lịch của stylist và lịch của chính khách.
        $valid = false;
        foreach (booking_generate_slots($employeeId, $date, $duration, $clientId) as $slot) {
            if ($slot['start'] === $time) {
                $valid = true;
                break;
            }
        }
        if (!$valid) {
            $pdo->rollBack();
            return $fail('Khung giờ này không còn khả dụng (đã có người đặt, trùng lịch khác của bạn hoặc ngoài giờ làm việc). Vui lòng chọn khung giờ khác.');
        }

        $start = $date . ' ' . $time . ':00';
        $end   = date('Y-m-d H:i:s', strtotime($start) + $duration * 60);

        db_query(
            'INSERT INTO appointments (client_id, employee_id, start_time, end_time_expected, note, reference_image)
             VALUES (?, ?, ?, ?, ?, ?)',
            [$clientId, $employeeId, $start, $end, $note !== '' ? $note : null, $referenceImage]
        );
        $appointmentId = (int) $pdo->lastInsertId();

        foreach ($services as $service) {
            db_query(
                'INSERT INTO services_booked (appointment_id, service_id, service_name, price, duration) VALUES (?, ?, ?, ?, ?)',
                [$appointmentId, $service['service_id'], $service['service_name'], $service['service_price'], $service['service_duration']]
            );
        }

        $pdo->commit();
        log_activity(
            'booking_create',
            "Đặt lịch #$appointmentId lúc " . format_datetime($start) . ' với ' . $employee['full_name'] . '.',
            ['type' => 'guest', 'id' => $clientId, 'name' => $customer['full_name']]
        );
        return ['ok' => true, 'error' => null, 'appointment_id' => $appointmentId];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('booking_create failed: ' . $e->getMessage());
        return $fail('Không thể tạo lịch hẹn, vui lòng thử lại.');
    }
}

/** Danh sách dịch vụ của 1 lịch hẹn. */
function appointment_services(int $appointmentId): array
{
    return db_all('SELECT * FROM services_booked WHERE appointment_id = ? ORDER BY id', [$appointmentId]);
}

/** Gắn danh sách dịch vụ (và tổng tiền) vào các dòng lịch hẹn. */
function appointments_attach_services(array $rows): array
{
    if (!$rows) {
        return $rows;
    }
    $ids   = array_column($rows, 'appointment_id');
    $marks = implode(',', array_fill(0, count($ids), '?'));
    $byAppt = [];
    foreach (db_all("SELECT * FROM services_booked WHERE appointment_id IN ($marks) ORDER BY id", $ids) as $s) {
        $byAppt[$s['appointment_id']][] = $s;
    }
    foreach ($rows as &$row) {
        $row['services'] = $byAppt[$row['appointment_id']] ?? [];
        $row['total']    = array_sum(array_column($row['services'], 'price'));
    }
    return $rows;
}

/**
 * Hủy lịch hẹn (chỉ quản trị viên thực hiện – khách gọi điện nhờ salon hủy).
 * @param string $requestedBy 'client' = khách yêu cầu hủy, 'admin' = salon chủ động hủy
 * @return string|null thông báo lỗi, null nếu thành công
 */
function booking_cancel(int $appointmentId, string $requestedBy, string $reason): ?string
{
    $appt = db_row('SELECT * FROM appointments WHERE appointment_id = ?', [$appointmentId]);
    if ($appt === null) {
        return 'Không tìm thấy lịch hẹn.';
    }
    if ((int) $appt['canceled'] === 1) {
        return 'Lịch hẹn này đã được hủy trước đó.';
    }
    if (strtotime($appt['start_time']) <= time()) {
        return 'Không thể hủy lịch hẹn đã diễn ra.';
    }
    if (!in_array($requestedBy, ['client', 'admin'], true)) {
        return 'Người yêu cầu hủy không hợp lệ.';
    }
    if (mb_strlen($reason) > 500) {
        return 'Lý do hủy tối đa 500 ký tự.';
    }

    db_query(
        'UPDATE appointments SET canceled = 1, canceled_by = ?, cancellation_reason = ? WHERE appointment_id = ? AND canceled = 0',
        [$requestedBy, $reason !== '' ? $reason : null, $appointmentId]
    );
    log_activity('booking_cancel', "Hủy lịch hẹn #$appointmentId (" . ($requestedBy === 'client' ? 'theo yêu cầu của khách' : 'salon chủ động hủy') . ').');
    return null;
}
