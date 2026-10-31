<?php

declare(strict_types=1);

/**
 * Xem / tải xuống ảnh tham khảo của lịch hẹn (tệp riêng tư).
 * GET ?id=<mã lịch hẹn>[&download=1]
 * Chỉ quản trị viên đã đăng nhập mới được truy cập.
 */

require_once __DIR__ . '/includes/bootstrap.php';

$appointmentId = (int) get('id', '0');
$appt = db_row('SELECT appointment_id, client_id, reference_image FROM appointments WHERE appointment_id = ?', [$appointmentId]);

$allowed = admin_current() !== null;

if ($appt === null || !$allowed) {
    http_response_code(404);
    exit('Không tìm thấy tệp.');
}

$path = $appt['reference_image'] ? upload_path('references', $appt['reference_image']) : '';
if ($path === '' || !is_file($path)) {
    http_response_code(404);
    exit('Không tìm thấy tệp.');
}

$mime = (new finfo(FILEINFO_MIME_TYPE))->file($path) ?: 'application/octet-stream';
$ext  = pathinfo($path, PATHINFO_EXTENSION);
$disposition = get('download') === '1' ? 'attachment' : 'inline';

header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($path));
header('Content-Disposition: ' . $disposition . '; filename="tham-khao-lich-hen-' . $appointmentId . '.' . $ext . '"');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, max-age=600');
readfile($path);
exit;
