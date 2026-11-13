<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Request;
use App\Helpers\Format;
use finfo;
use InvalidArgumentException;

/**
 * Xử lý ảnh tải lên an toàn: kiểm tra dung lượng, kiểm tra NỘI DUNG thật (MIME + getimagesize, không tin đuôi tệp),
 * đổi tên ngẫu nhiên, mã hóa lại bằng GD (xóa EXIF/GPS và mã độc chèn trong ảnh), giới hạn 1600 px.
 * Thư mục con: avatars, services (công khai) và references (riêng tư – chỉ tải qua file.php).
 */
final class UploadService
{
    public const SUBDIRS = ['avatars', 'services', 'references'];
    private const MAX_DIMENSION = 1600;

    private Request $request;

    public function __construct(Request $request)
    {
        $this->request = $request;
    }

    /** Đường dẫn vật lý của tệp; chỉ nhận thư mục con hợp lệ, basename() chặn "../". */
    public function path(string $subdir, string $filename): string
    {
        if (!in_array($subdir, self::SUBDIRS, true)) {
            throw new InvalidArgumentException('Thư mục upload không hợp lệ.');
        }
        return UPLOAD_DIR . '/' . $subdir . '/' . basename($filename);
    }

    /** URL công khai của ảnh (avatars/services). null nếu chưa có ảnh hoặc là ảnh riêng tư. */
    public function url(string $subdir, ?string $filename): ?string
    {
        if (!$filename || $subdir === 'references' || !is_file($this->path($subdir, $filename))) {
            return null;
        }
        return url('uploads/' . $subdir . '/' . rawurlencode(basename($filename)));
    }

    /**
     * Lưu ảnh từ ô input file $field.
     * @return array{file:?string,error:?string} file = tên tệp đã lưu; cả 2 null = người dùng không chọn tệp
     */
    public function saveImage(string $field, string $subdir, int $maxBytes): array
    {
        if (!$this->request->hasFile($field)) {
            return ['file' => null, 'error' => null];
        }
        $file = (array) $this->request->file($field);

        if ($file['error'] !== UPLOAD_ERR_OK) {
            $msg = in_array($file['error'], [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)
                ? 'Tệp vượt quá dung lượng cho phép.'
                : 'Tải tệp lên thất bại, vui lòng thử lại.';
            return ['file' => null, 'error' => $msg];
        }
        if ($file['size'] > $maxBytes) {
            return ['file' => null, 'error' => 'Ảnh tối đa ' . round($maxBytes / 1048576) . ' MB.'];
        }
        if (!is_uploaded_file($file['tmp_name'])) {
            return ['file' => null, 'error' => 'Tệp tải lên không hợp lệ.'];
        }

        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
        if (!isset(UPLOAD_ALLOWED_MIME[$mime]) || @getimagesize($file['tmp_name']) === false) {
            return ['file' => null, 'error' => 'Chỉ chấp nhận ảnh JPG, PNG hoặc WEBP.'];
        }

        $filename = bin2hex(random_bytes(16)) . '.' . UPLOAD_ALLOWED_MIME[$mime];
        $target   = $this->path($subdir, $filename);

        if (!is_dir(dirname($target))) {
            mkdir(dirname($target), 0755, true);
        }
        if (!$this->reencode($file['tmp_name'], $target, $mime) && !move_uploaded_file($file['tmp_name'], $target)) {
            return ['file' => null, 'error' => 'Không thể lưu tệp, vui lòng kiểm tra quyền ghi thư mục uploads.'];
        }
        return ['file' => $filename, 'error' => null];
    }

    /** Mã hóa lại ảnh bằng GD. Trả về false nếu GD không khả dụng (khi đó tệp gốc được giữ nguyên). */
    private function reencode(string $source, string $target, string $mime): bool
    {
        if (!function_exists('imagecreatefromstring')) {
            return false;
        }
        $image = @imagecreatefromstring((string) file_get_contents($source));
        if ($image === false) {
            return false;
        }

        $w = imagesx($image);
        $h = imagesy($image);
        if (max($w, $h) > self::MAX_DIMENSION) {
            $ratio  = self::MAX_DIMENSION / max($w, $h);
            $scaled = imagescale($image, (int) round($w * $ratio), (int) round($h * $ratio));
            if ($scaled !== false) {
                $image = $scaled;
            }
        }

        switch ($mime) {
            case 'image/png':
                imagesavealpha($image, true);
                $ok = imagepng($image, $target, 6);
                break;
            case 'image/webp':
                $ok = function_exists('imagewebp') && imagewebp($image, $target, 85);
                break;
            default:
                $ok = imagejpeg($image, $target, 85);
        }
        return (bool) $ok;
    }

    public function delete(string $subdir, ?string $filename): void
    {
        if (!$filename) {
            return;
        }
        $path = $this->path($subdir, $filename);
        if (is_file($path)) {
            @unlink($path);
        }
    }

    /** Ảnh đại diện; chưa có ảnh -> ô tròn chứa chữ cái đầu của tên. */
    public function avatar(?string $subdir, ?string $filename, string $name, string $class = ''): string
    {
        $src = $subdir ? $this->url($subdir, $filename) : null;
        if ($src !== null) {
            return '<img class="avatar-img ' . e($class) . '" src="' . e($src) . '" alt="' . e($name) . '">';
        }
        return '<span class="avatar-initial ' . e($class) . '" aria-hidden="true">' . e(Format::initials($name)) . '</span>';
    }
}
