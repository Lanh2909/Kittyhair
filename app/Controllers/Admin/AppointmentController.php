<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\App;
use App\Core\Paginator;
use App\Core\QueryFilter;
use App\Models\AppointmentModel;

/**
 * Quản lý lịch hẹn (admin/appointments.php): lọc theo trạng thái, tìm kiếm, phân trang,
 * hủy lịch kèm người yêu cầu hủy, xóa ảnh tham khảo khách gửi.
 */
final class AppointmentController extends AdminController
{
    protected ?string $permission = 'appointments';

    private const FILTERS = ['upcoming' => 'Sắp tới', 'today' => 'Hôm nay', 'past' => 'Đã hoàn thành', 'canceled' => 'Đã hủy', 'all' => 'Tất cả'];

    /** Điều kiện SQL của từng bộ lọc trạng thái. */
    private const STATUS_SQL = [
        'upcoming' => 'a.canceled = 0 AND a.start_time >= NOW()',
        'today'    => 'a.canceled = 0 AND DATE(a.start_time) = CURDATE()',
        'past'     => 'a.canceled = 0 AND a.start_time < NOW()',
        'canceled' => 'a.canceled = 1',
    ];

    private AppointmentModel $appointments;

    public function __construct()
    {
        parent::__construct();
        $this->appointments = new AppointmentModel(App::db());
    }

    public function handle(): void
    {
        if ($this->request->isPost()) {
            $this->handlePost();
        }
        $this->index();
    }

    private function handlePost(): void
    {
        $this->verifyCsrf();
        $action = $this->request->post('action');
        $id     = (int) $this->request->post('appointment_id');

        if ($action === 'cancel') {
            $requestedBy = $this->request->post('requested_by') === 'client' ? 'client' : 'admin';
            $error = App::booking()->cancel($id, $requestedBy, $this->request->post('reason'));
            $error === null ? $this->flash('success', "Đã hủy lịch hẹn #$id.") : $this->flash('danger', $error);
        } elseif ($action === 'delete_reference') {
            $appt = $this->appointments->find($id);
            if ($appt && $appt['reference_image']) {
                App::uploads()->delete('references', $appt['reference_image']);
                $this->appointments->clearReferenceImage($id);
                App::logger()->log('file_delete', "Xóa ảnh tham khảo của lịch hẹn #$id.");
                $this->flash('success', 'Đã xóa ảnh tham khảo.');
            }
        }
        $this->redirect($this->withQuery('admin/appointments.php'));
    }

    private function index(): void
    {
        $status   = array_key_exists($this->request->get('status'), self::FILTERS) ? $this->request->get('status') : 'upcoming';
        $keyword  = $this->keyword();
        $clientId = (int) $this->request->get('client_id', '0');

        $filter = new QueryFilter();
        if (isset(self::STATUS_SQL[$status])) {
            $filter->where(self::STATUS_SQL[$status]);
        }
        if ($clientId > 0) {
            $filter->where('a.client_id = ?', [$clientId]);
        }
        if ($keyword !== '') {
            // SĐT lưu dạng 0xxxxxxxxx nên tìm theo phần chữ số (gõ "0912 345" vẫn ra); gõ số thuần -> tìm cả mã lịch hẹn
            $like   = QueryFilter::like($keyword);
            $digits = preg_replace('/\D/', '', $keyword);
            $filter->where(
                '(c.full_name LIKE ? OR c.phone_number LIKE ? OR c.email LIKE ? OR e.full_name LIKE ? OR a.appointment_id = ?
                 OR EXISTS (SELECT 1 FROM services_booked sb WHERE sb.appointment_id = a.appointment_id AND sb.service_name LIKE ?))',
                [$like, $digits !== '' ? QueryFilter::like($digits) : $like, $like, $like, ctype_digit($keyword) ? (int) $keyword : 0, $like]
            );
        }

        $pager = new Paginator($this->appointments->countForAdmin($filter), PER_PAGE_ADMIN);
        $order = in_array($status, ['upcoming', 'today'], true) ? 'ASC' : 'DESC';
        $rows  = App::booking()->attachServices($this->appointments->pageForAdmin($filter, $pager, $order));

        $this->renderAdmin('admin/appointments', 'Lịch hẹn', 'appointments', [
            'filters'  => self::FILTERS,
            'status'   => $status,
            'keyword'  => $keyword,
            'clientId' => $clientId,
            'rows'     => $rows,
            'pager'    => $pager,
            'keep'     => ['status' => $status, 'q' => $keyword, 'client_id' => $clientId ?: ''],
        ]);
    }
}
