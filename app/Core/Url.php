<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Tạo URL theo thư mục cài đặt (tự nhận diện, có thể ghi đè bằng biến môi trường KH_BASE_URL).
 */
final class Url
{
    private static ?string $base = null;

    /** URL gốc của site, vd "/kittyhair" khi chạy ở http://localhost/kittyhair/. */
    public static function base(): string
    {
        if (self::$base !== null) {
            return self::$base;
        }
        $env = getenv('KH_BASE_URL');
        if ($env !== false) {
            return self::$base = rtrim($env, '/');
        }
        $root = str_replace('\\', '/', (string) realpath(APP_ROOT));
        $doc  = str_replace('\\', '/', (string) realpath($_SERVER['DOCUMENT_ROOT'] ?? ''));
        if ($doc !== '' && strpos($root, $doc) === 0) {
            return self::$base = rtrim(substr($root, strlen($doc)), '/');
        }
        return self::$base = '';
    }

    /** Url::to('booking.php') -> "/kittyhair/booking.php". */
    public static function to(string $path = ''): string
    {
        return self::base() . '/' . ltrim($path, '/');
    }
}
