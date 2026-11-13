<?php

declare(strict_types=1);

namespace App\Services;

use App\Helpers\Format;
use App\Helpers\Time;
use App\Models\AppointmentModel;
use App\Models\ScheduleModel;

/**
 * Giờ làm việc hằng tuần của nhân viên (quản lý ngay trong form Thêm/Sửa nhân viên).
 * Dạng dữ liệu dùng chung: [day => ['enabled' => true, 'from' => 'HH:MM', 'to' => 'HH:MM']], day 1..7 (ISO-8601).
 */
final class ScheduleService
{
    private ScheduleModel $schedules;
    private AppointmentModel $appointments;

    public function __construct(ScheduleModel $schedules, AppointmentModel $appointments)
    {
        $this->schedules    = $schedules;
        $this->appointments = $appointments;
    }

    /** Lịch hiện tại của nhân viên. */
    public function load(int $employeeId): array
    {
        $out = [];
        foreach ($this->schedules->forEmployee($employeeId) as $row) {
            $out[(int) $row['day_id']] = ['enabled' => true, 'from' => substr($row['from_hour'], 0, 5), 'to' => substr($row['to_hour'], 0, 5)];
        }
        return $out;
    }

    /** Lịch gợi ý khi thêm nhân viên mới: Thứ Hai – Thứ Bảy, 09:00–18:00. */
    public function defaults(): array
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
    public function parse(array $days): array
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
            if (!Validator::isValidTime($from) || !Validator::isValidTime($to)) {
                $errors[$day] = 'Giờ không hợp lệ.';
            } elseif (Time::toMinutes($from) >= Time::toMinutes($to)) {
                $errors[$day] = 'Giờ kết thúc phải sau giờ bắt đầu.';
            } else {
                $rows[$day] = [$from, $to];
            }
        }
        return ['rows' => $rows, 'input' => $input, 'errors' => $errors];
    }

    /**
     * Các lịch hẹn sắp tới sẽ nằm NGOÀI giờ làm nếu áp dụng lịch mới (không cho thu hẹp giờ làm khi đã có khách đặt).
     * @return string[] mô tả từng lịch hẹn xung đột, vd "#12 (10:00 20/11/2026)"
     */
    public function conflicts(int $employeeId, array $rows): array
    {
        $conflicts = [];
        foreach ($this->appointments->upcomingForEmployee($employeeId) as $appt) {
            $day   = (int) date('N', strtotime($appt['start_time']));
            $start = Time::toMinutes(substr($appt['start_time'], 11));
            $end   = Time::toMinutes(substr($appt['end_time_expected'], 11));
            if (!isset($rows[$day]) || $start < Time::toMinutes($rows[$day][0]) || $end > Time::toMinutes($rows[$day][1])) {
                $conflicts[] = '#' . $appt['appointment_id'] . ' (' . Format::datetime($appt['start_time']) . ')';
            }
        }
        return $conflicts;
    }

    /** Ghi lịch mới (xóa lịch cũ rồi thêm lại). PHẢI gọi trong transaction của nơi gọi. */
    public function save(int $employeeId, array $rows): void
    {
        $this->schedules->deleteForEmployee($employeeId);
        foreach ($rows as $day => [$from, $to]) {
            $this->schedules->insert($employeeId, (int) $day, $from, $to);
        }
    }

    /** Tóm tắt "T2–T6 09:00–18:00 · T7 09:00–20:00" (gộp các ngày liền nhau cùng giờ). */
    public function summary(array $loaded): string
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
            $label   = $end > $day ? Format::shortWeekday($day) . '–' . Format::shortWeekday($end) : Format::shortWeekday($day);
            $parts[] = $label . ' ' . $key;
            $day = $end + 1;
        }
        return implode(' · ', $parts);
    }
}
