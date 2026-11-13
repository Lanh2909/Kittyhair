<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Helpers\Format;
use App\Helpers\Time;
use App\Models\AppointmentModel;
use App\Models\ClientModel;
use App\Models\EmployeeModel;
use App\Models\ScheduleModel;
use App\Models\ServiceModel;
use PDOException;
use Throwable;

/**
 * Lõi nghiệp vụ đặt lịch: sinh khung giờ trống, chống trùng lịch, tạo lịch, hủy lịch.
 *
 * Nguyên tắc chống trùng lịch:
 *  - Khoảng [start, end) trùng với lịch khác khi: start < end_khác AND end > start_khác
 *    (lịch nối tiếp nhau, kết thúc đúng lúc bắt đầu, KHÔNG bị coi là trùng).
 *  - Một stylist không nhận 2 lịch cùng lúc; một khách (theo email) không có 2 lịch cùng lúc.
 *  - Khi tạo lịch: transaction + khóa dòng stylist rồi khách (SELECT ... FOR UPDATE) và kiểm tra lại
 *    bằng CHÍNH hàm sinh khung giờ -> 2 người bấm đặt cùng lúc thì chỉ 1 người thành công.
 */
final class BookingService
{
    public const MAX_SERVICES = 8;

    private Database $db;
    private AppointmentModel $appointments;
    private ClientModel $clients;
    private EmployeeModel $employees;
    private ServiceModel $services;
    private ScheduleModel $schedules;
    private SettingService $settings;
    private ActivityLogger $logger;

    public function __construct(
        Database $db,
        AppointmentModel $appointments,
        ClientModel $clients,
        EmployeeModel $employees,
        ServiceModel $services,
        ScheduleModel $schedules,
        SettingService $settings,
        ActivityLogger $logger
    ) {
        $this->db           = $db;
        $this->appointments = $appointments;
        $this->clients      = $clients;
        $this->employees    = $employees;
        $this->services     = $services;
        $this->schedules    = $schedules;
        $this->settings     = $settings;
        $this->logger       = $logger;
    }

    /* ------------------------------ Dịch vụ đã chọn ------------------------------ */

    /** Dịch vụ hợp lệ theo id (bỏ id sai/trùng). Có id không tồn tại hoặc quá 8 dịch vụ -> []. */
    public function servicesByIds(array $ids): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), static function (int $id): bool {
            return $id > 0;
        })));
        if (!$ids || count($ids) > self::MAX_SERVICES) {
            return [];
        }
        $rows = $this->services->findMany($ids);
        return count($rows) === count($ids) ? $rows : [];
    }

    public static function totalDuration(array $services): int
    {
        return (int) array_sum(array_column($services, 'service_duration'));
    }

    public static function totalPrice(array $services): float
    {
        return (float) array_sum(array_column($services, 'service_price'));
    }

    /* ------------------------------ Khung giờ trống ------------------------------ */

    /** Các khoảng bận [phút bắt đầu, phút kết thúc] của nhân viên hoặc khách trong 1 ngày. */
    public function busyIntervals(string $column, int $id, string $date): array
    {
        $out = [];
        foreach ($this->appointments->activeTimesOnDate($column, $id, $date) as $row) {
            $out[] = [Time::toMinutes(substr($row['start_time'], 11)), Time::toMinutes(substr($row['end_time_expected'], 11))];
        }
        return $out;
    }

    /** Khoảng [start, end) có trùng khoảng bận nào không: start < bận_kết_thúc AND end > bận_bắt_đầu. */
    public static function overlaps(int $start, int $end, array $intervals): bool
    {
        foreach ($intervals as [$busyStart, $busyEnd]) {
            if ($start < $busyEnd && $end > $busyStart) {
                return true;
            }
        }
        return false;
    }

    /**
     * Sinh các khung giờ còn trống của 1 nhân viên trong 1 ngày – hàm DUY NHẤT dùng cho cả giao diện
     * (ajax/slots.php) và bước kiểm tra cuối cùng khi tạo lịch.
     * @return array<int,array{start:string,end:string}>
     */
    public function generateSlots(int $employeeId, string $date, int $duration, ?int $clientId = null): array
    {
        if ($duration <= 0 || !Validator::isValidDate($date)) {
            return [];
        }
        $today  = date('Y-m-d');
        $maxDay = date('Y-m-d', strtotime('+' . (max(1, (int) $this->settings->get('booking_days_ahead')) - 1) . ' days'));
        if ($date < $today || $date > $maxDay) {
            return [];
        }

        $schedule = $this->schedules->forDay($employeeId, (int) date('N', strtotime($date)));
        if ($schedule === null) {
            return [];
        }

        // Giao của giờ làm nhân viên và giờ mở cửa salon
        $open  = max(Time::toMinutes($schedule['from_hour']), Time::toMinutes($this->settings->get('open_time')));
        $close = min(Time::toMinutes($schedule['to_hour']), Time::toMinutes($this->settings->get('close_time')));
        $step  = max(5, (int) $this->settings->get('slot_step'));

        // Lịch trong ngày hôm nay phải đặt trước tối thiểu min_notice_minutes phút
        $earliest = 0;
        if ($date === $today) {
            $earliest = Time::toMinutes(date('H:i')) + max(0, (int) $this->settings->get('min_notice_minutes'));
        }

        $employeeBusy = $this->busyIntervals('employee_id', $employeeId, $date);
        $clientBusy   = $clientId ? $this->busyIntervals('client_id', $clientId, $date) : [];

        $slots = [];
        for ($start = $open; $start + $duration <= $close; $start += $step) {
            $end = $start + $duration;
            if ($start < $earliest || self::overlaps($start, $end, $employeeBusy) || self::overlaps($start, $end, $clientBusy)) {
                continue;
            }
            $slots[] = ['start' => Time::fromMinutes($start), 'end' => Time::fromMinutes($end)];
        }
        return $slots;
    }

    /**
     * Các ngày trong khung đặt lịch kèm khung giờ trống (dữ liệu JSON cho bước 3 của wizard).
     * @return array<int,array{date:string,weekday:string,label:string,slots:array}>
     */
    public function availableDays(int $employeeId, int $duration, ?int $clientId = null): array
    {
        $days  = [];
        $total = max(1, (int) $this->settings->get('booking_days_ahead'));
        for ($i = 0; $i < $total; $i++) {
            $ts   = strtotime("+$i days");
            $date = date('Y-m-d', $ts);
            $days[] = [
                'date'    => $date,
                'weekday' => $i === 0 ? 'Hôm nay' : Format::shortWeekday((int) date('N', $ts)),
                'label'   => date('d/m', $ts),
                'slots'   => $this->generateSlots($employeeId, $date, $duration, $clientId),
            ];
        }
        return $days;
    }

    /* ------------------------------ Tạo lịch hẹn ------------------------------ */

    /**
     * Kiểm tra thông tin liên hệ khách nhập.
     * @return array{data:array,errors:array<string,string>} data đã chuẩn hóa (full_name, phone, email)
     */
    public function validateCustomer(string $name, string $phoneInput, string $email): array
    {
        $errors = [];
        $phone  = Validator::normalizePhone($phoneInput);
        $email  = mb_strtolower(trim($email));

        if (!Validator::isValidFullName($name)) {
            $errors['full_name'] = 'Họ tên không hợp lệ (2–100 ký tự, chỉ gồm chữ cái).';
        }
        if ($phone === null) {
            $errors['phone'] = Validator::phoneErrorMessage();
        }
        if (!Validator::isValidEmail($email)) {
            $errors['email'] = 'Email không hợp lệ.';
        }
        return ['data' => ['full_name' => $name, 'phone' => (string) $phone, 'email' => $email], 'errors' => $errors];
    }

    /**
     * Tìm khách theo email (đã có -> cập nhật họ tên/SĐT mới nhất, chưa có -> tạo mới) và KHÓA dòng đó.
     * PHẢI gọi trong transaction. Xử lý cả trường hợp 2 yêu cầu cùng tạo 1 email mới (lỗi trùng khóa 23000).
     */
    public function upsertClient(array $customer): int
    {
        $clientId = $this->clients->findIdByEmailForUpdate($customer['email']);
        if ($clientId === null) {
            try {
                return $this->clients->create($customer['full_name'], $customer['phone'], $customer['email']);
            } catch (PDOException $e) {
                if ($e->getCode() !== '23000') {
                    throw $e;
                }
                $clientId = $this->clients->findIdByEmailForUpdate($customer['email']);   // yêu cầu khác vừa tạo khách này
                if ($clientId === null) {
                    throw $e;
                }
            }
        }
        $this->clients->updateContact($clientId, $customer['full_name'], $customer['phone']);
        return $clientId;
    }

    /**
     * Tạo lịch hẹn (an toàn khi nhiều người đặt cùng lúc).
     * @param array $customer ['full_name','phone','email'] đã qua validateCustomer()
     * @return array{ok:bool,error:?string,appointment_id:?int}
     */
    public function create(array $customer, int $employeeId, array $serviceIds, string $date, string $time, string $note, ?string $referenceImage): array
    {
        $services = $this->servicesByIds($serviceIds);
        if (!$services) {
            return self::failure('Vui lòng chọn ít nhất một dịch vụ hợp lệ.');
        }
        if (!Validator::isValidTime($time) || !Validator::isValidDate($date)) {
            return self::failure('Ngày hoặc giờ không hợp lệ.');
        }
        if (mb_strlen($note) > 500) {
            return self::failure('Ghi chú tối đa 500 ký tự.');
        }

        $duration = self::totalDuration($services);

        try {
            $this->db->beginTransaction();

            // Thứ tự khóa cố định (nhân viên -> khách) để tránh deadlock; tuần tự hóa các lượt đặt cùng lúc.
            $employee = $this->employees->findForUpdate($employeeId);
            if ($employee === null) {
                $this->db->rollBack();
                return self::failure('Nhân viên không tồn tại.');
            }
            $clientId = $this->upsertClient($customer);

            // Kiểm tra lại bằng chính hàm sinh khung giờ: giờ làm, giờ mở cửa, lịch của stylist và của chính khách.
            $valid = false;
            foreach ($this->generateSlots($employeeId, $date, $duration, $clientId) as $slot) {
                if ($slot['start'] === $time) {
                    $valid = true;
                    break;
                }
            }
            if (!$valid) {
                $this->db->rollBack();
                return self::failure('Khung giờ này không còn khả dụng (đã có người đặt, trùng lịch khác của bạn hoặc ngoài giờ làm việc). Vui lòng chọn khung giờ khác.');
            }

            $start = $date . ' ' . $time . ':00';
            $end   = date('Y-m-d H:i:s', strtotime($start) + $duration * 60);

            $appointmentId = $this->appointments->create($clientId, $employeeId, $start, $end, $note !== '' ? $note : null, $referenceImage);
            foreach ($services as $service) {
                $this->appointments->addBookedService($appointmentId, $service);
            }

            $this->db->commit();
            $this->logger->log(
                'booking_create',
                "Đặt lịch #$appointmentId lúc " . Format::datetime($start) . ' với ' . $employee['full_name'] . '.',
                ['type' => 'guest', 'id' => $clientId, 'name' => $customer['full_name']]
            );
            return ['ok' => true, 'error' => null, 'appointment_id' => $appointmentId];
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            error_log('booking_create failed: ' . $e->getMessage());
            return self::failure('Không thể tạo lịch hẹn, vui lòng thử lại.');
        }
    }

    private static function failure(string $message): array
    {
        return ['ok' => false, 'error' => $message, 'appointment_id' => null];
    }

    /* ------------------------------ Đọc / hủy lịch hẹn ------------------------------ */

    /** Lịch hẹn kèm khách, stylist, danh sách dịch vụ và tổng tiền (trang xác nhận). */
    public function findForConfirmation(int $appointmentId): ?array
    {
        $appt = $appointmentId > 0 ? $this->appointments->findWithDetails($appointmentId) : null;
        return $appt === null ? null : $this->attachServices([$appt])[0];
    }

    /** Gắn danh sách dịch vụ ('services') và tổng tiền ('total') vào các dòng lịch hẹn. */
    public function attachServices(array $rows): array
    {
        if (!$rows) {
            return $rows;
        }
        $byAppointment = $this->appointments->bookedServicesFor(array_column($rows, 'appointment_id'));
        foreach ($rows as &$row) {
            $row['services'] = $byAppointment[$row['appointment_id']] ?? [];
            $row['total']    = array_sum(array_column($row['services'], 'price'));
        }
        unset($row);
        return $rows;
    }

    /**
     * Hủy lịch hẹn (chỉ quản trị viên thực hiện – khách gọi điện nhờ salon hủy).
     * @param string $requestedBy 'client' = khách yêu cầu hủy, 'admin' = salon chủ động hủy
     * @return string|null thông báo lỗi, null nếu thành công
     */
    public function cancel(int $appointmentId, string $requestedBy, string $reason): ?string
    {
        $appt = $this->appointments->find($appointmentId);
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

        $this->appointments->markCanceled($appointmentId, $requestedBy, $reason !== '' ? $reason : null);
        $this->logger->log('booking_cancel', "Hủy lịch hẹn #$appointmentId (" . ($requestedBy === 'client' ? 'theo yêu cầu của khách' : 'salon chủ động hủy') . ').');
        return null;
    }
}
