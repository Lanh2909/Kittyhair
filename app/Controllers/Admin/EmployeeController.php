<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\App;
use App\Core\Paginator;
use App\Core\QueryFilter;
use App\Helpers\Format;
use App\Models\AppointmentModel;
use App\Models\EmployeeModel;
use App\Services\Validator;
use Throwable;

/**
 * Quản lý nhân viên (admin/employees.php): thông tin, ảnh và GIỜ LÀM 7 NGÀY trong cùng một form.
 * Nhân viên + giờ làm được lưu trong 1 transaction (hoặc lưu cả hai, hoặc không lưu gì).
 */
final class EmployeeController extends AdminController
{
    protected ?string $permission = 'employees';

    private EmployeeModel $employees;

    public function __construct()
    {
        parent::__construct();
        $this->employees = new EmployeeModel(App::db());
    }

    public function handle(): void
    {
        $action = $this->request->get('action');
        $state  = ['errors' => [], 'form' => null, 'scheduleInput' => null, 'scheduleErrors' => null];

        if ($this->request->isPost()) {
            $this->verifyCsrf();
            $postAction = $this->request->post('action');
            $id         = (int) $this->request->post('employee_id');

            if ($postAction === 'delete') {
                $this->delete($id);
            }
            if ($postAction === 'save') {
                // Thành công thì save() chuyển hướng; quay lại đây nghĩa là có lỗi -> hiển thị lại form
                $existing = $id > 0 ? $this->employees->find($id) : null;
                $state    = $this->save($id, $existing);
                $action   = $existing ? 'edit' : 'create';
                $this->request->setQuery('id', (string) $id);
            }
        }

        if ($action === 'create' || $action === 'edit') {
            $this->form($action, $state);
            return;
        }
        $this->index();
    }

    /** Chỉ xóa được nhân viên chưa từng có lịch hẹn (bảo toàn lịch sử). */
    private function delete(int $id): void
    {
        $appointments = new AppointmentModel(App::db());
        $employee = $this->employees->find($id);
        $upcoming = $appointments->countUpcomingForEmployee($id);
        $history  = $appointments->countForEmployee($id);
        if ($employee === null) {
            $this->flash('danger', 'Không tìm thấy nhân viên.');
        } elseif ($upcoming > 0) {
            $this->flash('danger', "Nhân viên còn $upcoming lịch hẹn sắp tới. Hãy hủy các lịch này trước khi xóa.");
        } elseif ($history > 0) {
            $this->flash('danger', 'Nhân viên đã có lịch sử lịch hẹn nên không thể xóa để bảo toàn dữ liệu. Bạn có thể bỏ chọn tất cả ngày làm việc trong form Sửa để ngừng nhận lịch mới.');
        } else {
            $this->employees->delete($id);
            App::uploads()->delete('avatars', $employee['avatar']);
            App::logger()->log('employee_delete', 'Xóa nhân viên: ' . $employee['full_name']);
            $this->flash('success', 'Đã xóa nhân viên.');
        }
        $this->redirect('admin/employees.php');
    }

    /** @return array{errors:array,form:array,scheduleInput:array,scheduleErrors:array} dữ liệu hiển thị lại form khi có lỗi */
    private function save(int $id, ?array $existing): array
    {
        $scheduleService = App::schedule();
        $data = [
            'full_name' => $this->request->post('full_name'),
            'position'  => $this->request->post('position'),
            'phone'     => $this->request->post('phone'),
            'email'     => mb_strtolower($this->request->post('email')),
        ];
        $phone    = Validator::normalizePhone($data['phone']);
        $schedule = $scheduleService->parse($this->request->postArray('days'));
        $errors   = [];

        if (!Validator::isValidFullName($data['full_name'])) {
            $errors['full_name'] = 'Họ tên không hợp lệ (2–100 ký tự, chỉ gồm chữ cái).';
        }
        if (mb_strlen($data['position']) < 2 || mb_strlen($data['position']) > 100) {
            $errors['position'] = 'Chức danh phải từ 2 đến 100 ký tự.';
        }
        if ($phone === null) {
            $errors['phone'] = Validator::phoneErrorMessage();
        } elseif ($this->employees->phoneTaken($phone, $id)) {
            $errors['phone'] = 'Số điện thoại đã thuộc về nhân viên khác.';
        }
        if (!Validator::isValidEmail($data['email'])) {
            $errors['email'] = 'Email không hợp lệ.';
        } elseif ($this->employees->emailTaken($data['email'], $id)) {
            $errors['email'] = 'Email đã thuộc về nhân viên khác.';
        }
        if ($schedule['errors']) {
            $errors['schedule'] = 'Giờ làm việc chưa hợp lệ, vui lòng kiểm tra các ngày được đánh dấu.';
        } elseif ($existing) {
            // Không cho thu hẹp giờ làm nếu làm các lịch hẹn đã đặt nằm ngoài giờ làm
            $conflicts = $scheduleService->conflicts($id, $schedule['rows']);
            if ($conflicts) {
                $errors['schedule'] = 'Không thể lưu giờ làm vì các lịch hẹn đã đặt sẽ nằm ngoài giờ làm: ' . implode(', ', array_slice($conflicts, 0, 5)) . (count($conflicts) > 5 ? '…' : '') . '. Hãy hủy các lịch này hoặc giữ nguyên giờ làm.';
            }
        }

        $uploads   = App::uploads();
        $newAvatar = null;
        if (!$errors) {
            $upload = $uploads->saveImage('avatar', 'avatars', UPLOAD_MAX_AVATAR);
            if ($upload['error'] !== null) {
                $errors['avatar'] = $upload['error'];
            }
            $newAvatar = $upload['file'];
        }

        if (!$errors) {
            $oldAvatar = $existing['avatar'] ?? null;
            $avatar    = $oldAvatar;
            if ($newAvatar !== null) {
                $avatar = $newAvatar;
            } elseif ($existing && $this->request->post('remove_avatar') === '1') {
                $avatar = null;
            }

            try {
                App::db()->transaction(function () use ($existing, $id, $data, $phone, $avatar, $schedule, $scheduleService): void {
                    if ($existing) {
                        $this->employees->update($id, $data['full_name'], $data['position'], (string) $phone, $data['email'], $avatar);
                        $employeeId = $id;
                    } else {
                        $employeeId = $this->employees->create($data['full_name'], $data['position'], (string) $phone, $data['email'], $avatar);
                    }
                    $scheduleService->save($employeeId, $schedule['rows']);
                });
            } catch (Throwable $e) {
                error_log('employee save failed: ' . $e->getMessage());
                $uploads->delete('avatars', $newAvatar);
                $this->flash('danger', 'Không thể lưu nhân viên, vui lòng thử lại.');
                $this->redirect('admin/employees.php');
            }

            if ($avatar !== $oldAvatar) {
                $uploads->delete('avatars', $oldAvatar);
            }
            App::logger()->log($existing ? 'employee_update' : 'employee_create', ($existing ? 'Cập nhật' : 'Thêm') . ' nhân viên: ' . $data['full_name']);
            $this->flash('success', $existing ? 'Đã cập nhật nhân viên và giờ làm việc.' : 'Đã thêm nhân viên.');
            $this->redirect('admin/employees.php');
        }

        return [
            'errors'         => $errors,
            'form'           => ['full_name' => $data['full_name'], 'position' => $data['position'], 'phone_number' => $data['phone'], 'email' => $data['email'], 'avatar' => $existing['avatar'] ?? null],
            'scheduleInput'  => $schedule['input'],
            'scheduleErrors' => $schedule['errors'],
        ];
    }

    private function form(string $action, array $state): void
    {
        $editing = $action === 'edit' ? $this->employees->find((int) $this->request->get('id', '0')) : null;
        if ($action === 'edit' && $editing === null) {
            $this->flash('danger', 'Không tìm thấy nhân viên.');
            $this->redirect('admin/employees.php');
        }
        $form = $state['form'] ?? $editing ?? ['full_name' => '', 'position' => 'Stylist', 'phone_number' => '', 'email' => '', 'avatar' => null];
        if ($state['form'] === null && $editing) {
            $form['phone_number'] = Format::phone($form['phone_number']);
        }
        $schedule  = App::schedule();
        $pageTitle = $editing ? 'Sửa nhân viên' : 'Thêm nhân viên';

        $this->renderAdmin('admin/employees-form', $pageTitle, 'employees', [
            'editing'        => $editing,
            'form'           => $form,
            'errors'         => $state['errors'],
            'scheduleInput'  => $state['scheduleInput'] ?? ($editing ? $schedule->load((int) $editing['employee_id']) : $schedule->defaults()),
            'scheduleErrors' => $state['scheduleErrors'] ?? [],
            'avatarUrl'      => App::uploads()->url('avatars', $editing['avatar'] ?? null),
        ]);
    }

    private function index(): void
    {
        $keyword = $this->keyword();
        $filter  = new QueryFilter();
        if ($keyword !== '') {
            $digits = preg_replace('/\D/', '', $keyword);
            $filter->where(
                '(e.full_name LIKE ? OR e.position LIKE ? OR e.email LIKE ? OR e.phone_number LIKE ?)',
                [QueryFilter::like($keyword), QueryFilter::like($keyword), QueryFilter::like($keyword), QueryFilter::like($digits !== '' ? $digits : $keyword)]
            );
        }
        $pager = new Paginator($this->employees->count($filter), PER_PAGE_ADMIN);

        $schedule = App::schedule();
        $rows     = $this->employees->page($filter, $pager);
        foreach ($rows as &$row) {
            $row['schedule_summary'] = $schedule->summary($schedule->load((int) $row['employee_id']));
        }
        unset($row);

        $this->renderAdmin('admin/employees-list', 'Nhân viên', 'employees', [
            'rows'    => $rows,
            'keyword' => $keyword,
            'pager'   => $pager,
        ]);
    }
}
