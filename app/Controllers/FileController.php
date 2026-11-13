<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\App;
use App\Models\AppointmentModel;
use finfo;

/**
 * Xem / tải ảnh kiểu tóc tham khảo của lịch hẹn (tệp RIÊNG TƯ) – file.php?id=<mã lịch>[&download=1].
 * Chỉ quản trị viên đã đăng nhập mới xem được; thư mục uploads/references bị chặn truy cập trực tiếp.
 */
final class FileController extends Controller
{
    public function show(): void
    {
        $appointmentId = (int) $this->request->get('id', '0');
        $appt = (new AppointmentModel(App::db()))->find($appointmentId);

        // Không phân biệt "không có quyền" và "không tồn tại" để không lộ thông tin
        if ($appt === null || App::auth()->current() === null) {
            $this->notFound();
        }

        $path = $appt['reference_image'] ? App::uploads()->path('references', $appt['reference_image']) : '';
        if ($path === '' || !is_file($path)) {
            $this->notFound();
        }

        $mime        = (new finfo(FILEINFO_MIME_TYPE))->file($path) ?: 'application/octet-stream';
        $ext         = pathinfo($path, PATHINFO_EXTENSION);
        $disposition = $this->request->get('download') === '1' ? 'attachment' : 'inline';

        header('Content-Type: ' . $mime);
        header('Content-Length: ' . filesize($path));
        header('Content-Disposition: ' . $disposition . '; filename="tham-khao-lich-hen-' . $appointmentId . '.' . $ext . '"');
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: private, max-age=600');
        readfile($path);
        exit;
    }

    private function notFound(): void
    {
        http_response_code(404);
        exit('Không tìm thấy tệp.');
    }
}
