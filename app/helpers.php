<?php

declare(strict_types=1);

/**
 * Hai hàm tắt toàn cục của dự án, chỉ dùng trong file giao diện (app/Views).
 
 */

/** Escape HTML khi xuất ra trình duyệt = Html::e(). */
function e($value): string
{
    return \App\Helpers\Html::e($value);
}

/** URL tuyệt đối theo gốc site = Url::to(). */
function url(string $path = ''): string
{
    return \App\Core\Url::to($path);
}
