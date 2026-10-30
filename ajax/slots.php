<?php

declare(strict_types=1);

/**
 * GET ?employee_id=1&services[]=2&services[]=5
 * Trả về các ngày và khung giờ còn trống của nhân viên (theo tổng thời lượng dịch vụ).
 */

require_once __DIR__ . '/../includes/bootstrap.php';

header('Cache-Control: no-store');

$employeeId = (int) get('employee_id', '0');
$serviceIds = isset($_GET['services']) && is_array($_GET['services']) ? $_GET['services'] : [];

$services = booking_services_by_ids($serviceIds);
if (!$services || db_value('SELECT COUNT(*) FROM employees WHERE employee_id = ?', [$employeeId]) < 1) {
    json_response(['ok' => false, 'message' => 'Dịch vụ hoặc nhân viên không hợp lệ.'], 422);
}

$duration = booking_total_duration($services);
json_response([
    'ok'       => true,
    'duration' => $duration,
    'days'     => booking_available_days($employeeId, $duration),
]);
