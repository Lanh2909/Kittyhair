<?php

declare(strict_types=1);

/**
 * Xử lý tệp ảnh tải lên: kiểm tra, mã hóa lại (xóa EXIF/mã độc), lưu tên ngẫu nhiên.
 * Thư mục con hợp lệ: avatars, services (công khai) và references (riêng tư, tải qua file.php).
 */

const UPLOAD_SUBDIRS = ['avatars', 'services', 'references'];

function upload_path(string $subdir, string $filename): string
{
    if (!in_array($subdir, UPLOAD_SUBDIRS, true)) {
        throw new InvalidArgumentException('Thư mục upload không hợp lệ.');
    }
    return UPLOAD_DIR . '/' . $subdir . '/' . basename($filename);
}

/** URL công khai của ảnh (avatars/services). Trả về null nếu chưa có ảnh. */
function upload_url(string $subdir, ?string $filename): ?string
{
    if (!$filename || $subdir === 'references' || !is_file(upload_path($subdir, $filename))) {
        return null;
    }
    return url('uploads/' . $subdir . '/' . rawurlencode(basename($filename)));
}

/** Có tệp được chọn trong ô input file hay không. */
function has_upload(string $field): bool
{
    return isset($_FILES[$field]) && ($_FILES[$field]['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
}

/**
 * Lưu ảnh upload.
 * @return array{file:?string,error:?string} file = tên tệp đã lưu
 */
function save_uploaded_image(string $field, string $subdir, int $maxBytes): array
{
    if (!has_upload($field)) {
        return ['file' => null, 'error' => null];
    }
    $file = $_FILES[$field];

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

    $ext      = UPLOAD_ALLOWED_MIME[$mime];
    $filename = bin2hex(random_bytes(16)) . '.' . $ext;
    $target   = upload_path($subdir, $filename);

    if (!is_dir(dirname($target))) {
        mkdir(dirname($target), 0755, true);
    }
    if (!reencode_image($file['tmp_name'], $target, $mime) && !move_uploaded_file($file['tmp_name'], $target)) {
        return ['file' => null, 'error' => 'Không thể lưu tệp, vui lòng kiểm tra quyền ghi thư mục uploads.'];
    }
    return ['file' => $filename, 'error' => null];
}

/** Mã hóa lại ảnh bằng GD (giới hạn 1600px, bỏ EXIF). Trả về false nếu GD không khả dụng. */
function reencode_image(string $source, string $target, string $mime): bool
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
    $max = 1600;
    if (max($w, $h) > $max) {
        $ratio  = $max / max($w, $h);
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

function delete_uploaded_file(string $subdir, ?string $filename): void
{
    if (!$filename) {
        return;
    }
    $path = upload_path($subdir, $filename);
    if (is_file($path)) {
        @unlink($path);
    }
}

/** Avatar mặc định dạng chữ cái đầu (HTML). */
function render_avatar(?string $subdir, ?string $filename, string $name, string $class = ''): string
{
    $src = $subdir ? upload_url($subdir, $filename) : null;
    if ($src !== null) {
        return '<img class="avatar-img ' . e($class) . '" src="' . e($src) . '" alt="' . e($name) . '">';
    }
    return '<span class="avatar-initial ' . e($class) . '" aria-hidden="true">' . e(initials($name)) . '</span>';
}
