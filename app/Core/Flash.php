<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Thông báo hiển thị 1 lần sau khi chuyển hướng (flash message), lưu trong session.
 */
final class Flash
{
    /** @param string $type success | danger | info | warning (class alert của Bootstrap) */
    public static function add(string $type, string $message): void
    {
        $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
    }

    /** HTML các thông báo đang chờ rồi xóa khỏi session. */
    public static function render(): string
    {
        $html = '';
        foreach ($_SESSION['flash'] ?? [] as $item) {
            $html .= '<div class="alert alert-' . e($item['type']) . ' alert-dismissible fade show" role="alert">'
                . e($item['message'])
                . '<button type="button" class="close" data-dismiss="alert" aria-label="Đóng"><span aria-hidden="true">&times;</span></button></div>';
        }
        unset($_SESSION['flash']);
        return $html;
    }
}
