<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\App;
use App\Helpers\Format;
use App\Helpers\Time;
use App\Services\Validator;

/**
 * Cài đặt hệ thống (admin/settings.php): thông tin salon, mạng xã hội, giờ mở cửa, quy tắc đặt lịch.
 */
final class SettingController extends AdminController
{
    protected ?string $permission = 'settings';

    /** Các khóa được sửa trên form. */
    private const KEYS = ['salon_name', 'salon_slogan', 'salon_address', 'salon_phone', 'salon_email', 'open_time', 'close_time', 'slot_step',
                          'booking_days_ahead', 'min_notice_minutes', 'facebook_url', 'instagram_url', 'tiktok_url'];

    public function handle(): void
    {
        $settings = App::settings();
        $errors   = [];
        $values   = $settings->all();
        $values['salon_phone'] = Format::phone($values['salon_phone']);

        if ($this->request->isPost()) {
            $this->verifyCsrf();
            foreach (self::KEYS as $key) {
                $values[$key] = $this->request->post($key);
            }
            [$errors, $phone] = $this->validate($values);

            if (!$errors) {
                $values['salon_phone'] = (string) $phone;
                $settings->saveMany(array_intersect_key($values, array_flip(self::KEYS)));
                App::logger()->log('settings_update', 'Cập nhật cài đặt hệ thống.');
                $this->flash('success', 'Đã lưu cài đặt.');
                $this->redirect('admin/settings.php');
            }
        }

        $this->renderAdmin('admin/settings', 'Cài đặt', 'settings', ['values' => $values, 'errors' => $errors]);
    }

    /** @return array{0:array<string,string>,1:?string} [lỗi, SĐT đã chuẩn hóa] */
    private function validate(array $values): array
    {
        $errors = [];
        if (mb_strlen($values['salon_name']) < 2 || mb_strlen($values['salon_name']) > 60) {
            $errors['salon_name'] = 'Tên salon phải từ 2 đến 60 ký tự.';
        }
        if (mb_strlen($values['salon_slogan']) > 120) {
            $errors['salon_slogan'] = 'Khẩu hiệu tối đa 120 ký tự.';
        }
        if ($values['salon_address'] === '' || mb_strlen($values['salon_address']) > 200) {
            $errors['salon_address'] = 'Vui lòng nhập địa chỉ (tối đa 200 ký tự).';
        }
        $phone = Validator::normalizePhone($values['salon_phone']);
        if ($phone === null) {
            $errors['salon_phone'] = Validator::phoneErrorMessage();
        }
        if (!Validator::isValidEmail($values['salon_email'])) {
            $errors['salon_email'] = 'Email không hợp lệ.';
        }
        if (!Validator::isValidTime($values['open_time']) || !Validator::isValidTime($values['close_time'])) {
            $errors['open_time'] = 'Giờ mở/đóng cửa không hợp lệ.';
        } elseif (Time::toMinutes($values['open_time']) >= Time::toMinutes($values['close_time'])) {
            $errors['open_time'] = 'Giờ đóng cửa phải sau giờ mở cửa.';
        }
        if (!in_array($values['slot_step'], ['15', '30', '60'], true)) {
            $errors['slot_step'] = 'Bước thời gian không hợp lệ.';
        }
        $ranges = ['booking_days_ahead' => [1, 60], 'min_notice_minutes' => [0, 1440]];
        foreach ($ranges as $key => [$min, $max]) {
            if (!ctype_digit($values[$key]) || (int) $values[$key] < $min || (int) $values[$key] > $max) {
                $errors[$key] = "Giá trị từ $min đến $max.";
            }
        }
        foreach (['facebook_url', 'instagram_url', 'tiktok_url'] as $key) {
            if ($values[$key] !== '' && (!preg_match('#^https?://#i', $values[$key]) || filter_var($values[$key], FILTER_VALIDATE_URL) === false || mb_strlen($values[$key]) > 255)) {
                $errors[$key] = 'Liên kết phải bắt đầu bằng http:// hoặc https://';
            }
        }
        return [$errors, $phone];
    }
}
