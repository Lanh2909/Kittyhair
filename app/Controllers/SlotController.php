<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\App;
use App\Core\Response;
use App\Models\EmployeeModel;
use App\Services\BookingService;

/**
 * API JSON cho bước 3 của wizard (ajax/slots.php).
 * GET ?employee_id=1&services[]=2&services[]=5 -> các ngày và khung giờ còn trống theo tổng thời lượng dịch vụ.
 */
final class SlotController extends Controller
{
    public function index(): void
    {
        header('Cache-Control: no-store');

        $booking    = App::booking();
        $employeeId = (int) $this->request->get('employee_id', '0');
        $services   = $booking->servicesByIds($this->request->getArray('services'));

        if (!$services || !(new EmployeeModel(App::db()))->exists($employeeId)) {
            Response::json(['ok' => false, 'message' => 'Dịch vụ hoặc nhân viên không hợp lệ.'], 422);
        }

        $duration = BookingService::totalDuration($services);
        Response::json([
            'ok'       => true,
            'duration' => $duration,
            'days'     => $booking->availableDays($employeeId, $duration),
        ]);
    }
}
