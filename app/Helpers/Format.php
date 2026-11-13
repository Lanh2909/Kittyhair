<?php

declare(strict_types=1);

namespace App\Helpers;

/**
 * Định dạng dữ liệu để hiển thị (tiền, SĐT, ngày giờ, thời lượng, thứ trong tuần, chữ cái đầu tên).
 */
final class Format
{
    private const WEEKDAYS       = [1 => 'Thứ Hai', 2 => 'Thứ Ba', 3 => 'Thứ Tư', 4 => 'Thứ Năm', 5 => 'Thứ Sáu', 6 => 'Thứ Bảy', 7 => 'Chủ Nhật'];
    private const SHORT_WEEKDAYS = [1 => 'T2', 2 => 'T3', 3 => 'T4', 4 => 'T5', 5 => 'T6', 6 => 'T7', 7 => 'CN'];

    /** 1500000 -> "1.500.000đ" @param mixed $amount */
    public static function money($amount): string
    {
        return number_format((float) $amount, 0, ',', '.') . 'đ';
    }

    /** 0912345678 -> "0912 345 678" */
    public static function phone(string $phone): string
    {
        $digits = preg_replace('/\D/', '', $phone);
        return strlen($digits) === 10
            ? substr($digits, 0, 4) . ' ' . substr($digits, 4, 3) . ' ' . substr($digits, 7)
            : $phone;
    }

    /** "2026-11-20 14:30:00" -> "14:30 20/11/2026" */
    public static function datetime(string $datetime): string
    {
        return date('H:i d/m/Y', strtotime($datetime));
    }

    /** "2026-11-20 14:30:00" -> "20/11/2026" */
    public static function date(string $datetime): string
    {
        return date('d/m/Y', strtotime($datetime));
    }

    /** 75 -> "1 giờ 15 phút" */
    public static function duration(int $minutes): string
    {
        if ($minutes < 60) {
            return $minutes . ' phút';
        }
        $h = intdiv($minutes, 60);
        $m = $minutes % 60;
        return $h . ' giờ' . ($m ? ' ' . $m . ' phút' : '');
    }

    /** Tên thứ theo ISO-8601 (1 = Thứ Hai ... 7 = Chủ Nhật). */
    public static function weekday(int $isoDay): string
    {
        return self::WEEKDAYS[$isoDay] ?? '';
    }

    public static function shortWeekday(int $isoDay): string
    {
        return self::SHORT_WEEKDAYS[$isoDay] ?? '';
    }

    /** Chữ cái đầu của tên (từ cuối cùng) để làm avatar mặc định: "Trần Ngọc Lan" -> "L". */
    public static function initials(string $name): string
    {
        $parts = preg_split('/\s+/u', trim($name));
        return mb_strtoupper(mb_substr((string) end($parts), 0, 1));
    }
}
