<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\App;
use App\Core\Session;
use App\Helpers\Format;
use App\Models\CategoryModel;
use App\Models\EmployeeModel;
use App\Models\ServiceModel;

/**
 * Đặt lịch: hiển thị wizard 4 bước (booking.php), nhận form xác nhận, trang đặt lịch thành công (booking-success.php).
 */
final class BookingController extends Controller
{
    /** Tối đa 5 lượt đặt lịch thành công / 60 phút cho mỗi IP. */
    private const RATE_MAX = 5;
    private const RATE_MINUTES = 60;

    /** booking.php: GET hiển thị form, POST tạo lịch hẹn. */
    public function handle(): void
    {
        $old = [
            'services' => [], 'employee_id' => 0, 'date' => '', 'time' => '', 'note' => '',
            'full_name' => '', 'phone' => '', 'email' => '', 'step' => 0,
        ];
        $errors = [];

        // Từ trang Bảng giá: /booking.php?service=ID chọn sẵn 1 dịch vụ
        if (!$this->request->isPost() && ($preselect = (int) $this->request->get('service', '0')) > 0) {
            $old['services'] = [$preselect];
        }

        if ($this->request->isPost()) {
            $this->verifyCsrf();
            $old    = $this->oldInput();
            $errors = $this->submit($old);   // thành công thì đã chuyển hướng, không quay lại đây
        }

        $db = App::db();
        $this->render('client/booking', [
            'pageTitle'          => 'Đặt lịch làm tóc',
            'extraScripts'       => ['assets/js/booking.js'],
            'categories'         => (new CategoryModel($db))->withServices(),
            'servicesByCategory' => (new ServiceModel($db))->groupedByCategory('service_name'),
            'employees'          => (new EmployeeModel($db))->all(),
            'old'                => $old,
            'errors'             => $errors,
        ]);
    }

    /** Dữ liệu khách vừa gửi, dùng để điền lại form nếu có lỗi. */
    private function oldInput(): array
    {
        return [
            'services'    => array_map('intval', $this->request->postArray('services')),
            'employee_id' => (int) $this->request->post('employee_id'),
            'date'        => $this->request->post('date'),
            'time'        => $this->request->post('time'),
            'note'        => $this->request->post('note'),
            'full_name'   => $this->request->post('full_name'),
            'phone'       => $this->request->post('phone'),
            'email'       => $this->request->post('email'),
            'step'        => 4,
        ];
    }

    /**
     * Xử lý form xác nhận: chống bot -> giới hạn tần suất -> kiểm tra thông tin -> lưu ảnh -> tạo lịch.
     * @param array $old dữ liệu form (bước hiển thị lại được chỉnh nếu khung giờ bị người khác đặt mất)
     * @return array<string,string> lỗi theo từng trường
     */
    private function submit(array &$old): array
    {
        $booking = App::booking();
        $limiter = App::rateLimiter();

        if ($this->request->post('website') !== '') {
            // Ô ẩn (honeypot) chỉ bot mới điền
            $this->flash('danger', 'Yêu cầu không hợp lệ.');
            return [];
        }
        if ($limiter->exceeded('booking', self::RATE_MAX, self::RATE_MINUTES)) {
            $this->flash('danger', 'Bạn đã đặt lịch quá nhiều lần trong thời gian ngắn. Vui lòng thử lại sau hoặc gọi ' . Format::phone(App::settings()->get('salon_phone')) . '.');
            return [];
        }

        $check = $booking->validateCustomer($old['full_name'], $old['phone'], $old['email']);
        if ($check['errors']) {
            return $check['errors'];
        }

        $uploads = App::uploads();
        $upload  = $uploads->saveImage('reference_image', 'references', UPLOAD_MAX_REFERENCE);
        if ($upload['error'] !== null) {
            return ['reference_image' => $upload['error']];
        }

        $result = $booking->create($check['data'], $old['employee_id'], $old['services'], $old['date'], $old['time'], $old['note'], $upload['file']);
        if ($result['ok']) {
            $limiter->hit('booking');
            Session::set('last_booking_id', (int) $result['appointment_id']);
            $this->redirect('booking-success.php');
        }
        $uploads->delete('references', $upload['file']);
        $this->flash('danger', (string) $result['error']);
        $old['step'] = 3;
        return [];
    }

    /** booking-success.php: chỉ hiển thị lịch hẹn VỪA tạo trong phiên hiện tại (không phải chức năng tra cứu). */
    public function success(): void
    {
        $appt = App::booking()->findForConfirmation((int) Session::get('last_booking_id', 0));
        if ($appt === null) {
            $this->redirect('booking.php');
        }
        $this->render('client/booking-success', ['pageTitle' => 'Đặt lịch thành công', 'appt' => $appt]);
    }
}
