<?php

declare(strict_types=1);

/**
 * Giờ làm việc hằng tuần của nhân viên (quản lý ngay trong form Thêm/Sửa nhân viên).
 * day_id theo ISO-8601: 1 = Thứ Hai ... 7 = Chủ Nhật.
 */

/** Lịch hiện tại của nhân viên dạng [day => ['enabled' => true, 'from' => 'HH:MM', 'to' => 'HH:MM']]. */
function schedule_load(int $employeeId): array
{
    $out = [];
    foreach (db_all('SELECT day_id, from_hour, to_hour FROM employees_schedule WHERE employee_id = ?', [$employeeId]) as $row) {
        $out[(int) $row['day_id']] = ['enabled' => true, 'from' => substr($row['from_hour'], 0, 5), 'to' => substr($row['to_hour'], 0, 5)];
    }
    return $out;
}

/** Lịch gợi ý khi thêm nhân viên mới: Thứ Hai – Thứ Bảy, 09:00–18:00. */
function schedule_default(): array
{
    $out = [];
    for ($day = 1; $day <= 6; $day++) {
        $out[$day] = ['enabled' => true, 'from' => '09:00', 'to' => '18:00'];
    }
    return $out;
}

/**
 * Đọc và kiểm tra dữ liệu lịch từ form ($_POST['days']).
 * @return array{rows:array<int,array{0:string,1:string}>,input:array,errors:array<int,string>}
 */
function schedule_parse(array $days): array
{
    $rows = $input = $errors = [];
    for ($day = 1; $day <= 7; $day++) {
        $enabled = isset($days[$day]['enabled']);
        $from    = trim((string) ($days[$day]['from'] ?? ''));
        $to      = trim((string) ($days[$day]['to'] ?? ''));
        $input[$day] = ['enabled' => $enabled, 'from' => $from, 'to' => $to];
        if (!$enabled) {
            continue;
        }
        if (!is_valid_time($from) || !is_valid_time($to)) {
            $errors[$day] = 'Giờ không hợp lệ.';
        } elseif (time_to_minutes($from) >= time_to_minutes($to)) {
            $errors[$day] = 'Giờ kết thúc phải sau giờ bắt đầu.';
        } else {
            $rows[$day] = [$from, $to];
        }
    }
    return ['rows' => $rows, 'input' => $input, 'errors' => $errors];
}

/**
 * Các lịch hẹn sắp tới sẽ nằm ngoài giờ làm nếu áp dụng lịch mới.
 * @return string[] mô tả từng lịch hẹn xung đột
 */
function schedule_conflicts(int $employeeId, array $rows): array
{
    $conflicts = [];
    $upcoming  = db_all(
        'SELECT appointment_id, start_time, end_time_expected FROM appointments
         WHERE employee_id = ? AND canceled = 0 AND start_time >= NOW()',
        [$employeeId]
    );
    foreach ($upcoming as $appt) {
        $day   = (int) date('N', strtotime($appt['start_time']));
        $start = time_to_minutes(substr($appt['start_time'], 11));
        $end   = time_to_minutes(substr($appt['end_time_expected'], 11));
        if (!isset($rows[$day]) || $start < time_to_minutes($rows[$day][0]) || $end > time_to_minutes($rows[$day][1])) {
            $conflicts[] = '#' . $appt['appointment_id'] . ' (' . format_datetime($appt['start_time']) . ')';
        }
    }
    return $conflicts;
}

/** Ghi lịch mới (xóa lịch cũ). Phải gọi trong transaction của nơi gọi. */
function schedule_save(int $employeeId, array $rows): void
{
    db_query('DELETE FROM employees_schedule WHERE employee_id = ?', [$employeeId]);
    foreach ($rows as $day => [$from, $to]) {
        db_query('INSERT INTO employees_schedule (employee_id, day_id, from_hour, to_hour) VALUES (?, ?, ?, ?)', [$employeeId, $day, $from, $to]);
    }
}

/**
 * Tóm tắt lịch dạng "T2–T6 09:00–18:00 · T7 09:00–20:00" (gộp các ngày liền nhau cùng giờ).
 * @param array $loaded kết quả schedule_load()
 */
function schedule_summary(array $loaded): string
{
    $parts = [];
    $day = 1;
    while ($day <= 7) {
        if (!isset($loaded[$day])) {
            $day++;
            continue;
        }
        $key = $loaded[$day]['from'] . '–' . $loaded[$day]['to'];
        $end = $day;
        while ($end < 7 && isset($loaded[$end + 1]) && $loaded[$end + 1]['from'] . '–' . $loaded[$end + 1]['to'] === $key) {
            $end++;
        }
        $label   = $end > $day ? short_weekday_name($day) . '–' . short_weekday_name($end) : short_weekday_name($day);
        $parts[] = $label . ' ' . $key;
        $day = $end + 1;
    }
    return implode(' · ', $parts);
}
