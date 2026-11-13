<?php

declare(strict_types=1);

namespace App\Helpers;

/**
 * Đổi giờ "HH:MM" <-> số phút kể từ 00:00 để so sánh / cộng trừ khung giờ bằng số nguyên.
 * Vd: "09:30" -> 570; 570 + 45 = 615 -> "10:15".
 */
final class Time
{
    /** "HH:MM[:SS]" -> số phút. */
    public static function toMinutes(string $time): int
    {
        return (int) substr($time, 0, 2) * 60 + (int) substr($time, 3, 2);
    }

    /** Số phút -> "HH:MM". */
    public static function fromMinutes(int $minutes): string
    {
        return sprintf('%02d:%02d', intdiv($minutes, 60), $minutes % 60);
    }
}
