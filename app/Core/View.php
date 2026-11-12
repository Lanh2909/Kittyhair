<?php

declare(strict_types=1);

namespace App\Core;

use Throwable;

/**
 * Bộ hiển thị giao diện: nạp file trong app/Views, truyền biến, bọc trong layout.
 *
 * render('admin/clients', ['rows' => $rows], 'admin/layout'):
 *   1. Chạy app/Views/admin/clients.php, gom HTML vào $content.
 *   2. Chạy layout app/Views/admin/layout.php (header + sidebar + echo $content + footer).
 * Nhờ vậy Controller (xử lý) tách hẳn khỏi View (HTML).
 */
final class View
{
    private string $basePath;
    /** @var array<string,mixed> biến dùng chung cho mọi view (vd: $settings, $uploads) */
    private array $shared = [];

    public function __construct(string $basePath)
    {
        $this->basePath = rtrim($basePath, '/');
    }

    /** @param mixed $value */
    public function share(string $name, $value): void
    {
        $this->shared[$name] = $value;
    }

    public function render(string $template, array $data = [], ?string $layout = null): void
    {
        echo $this->fetch($template, $data, $layout);
    }

    /** Giống render() nhưng trả về chuỗi HTML thay vì in ra. */
    public function fetch(string $template, array $data = [], ?string $layout = null): string
    {
        $data    = array_merge($this->shared, $data);
        $content = $this->capture($template, $data);
        if ($layout === null) {
            return $content;
        }
        $data['content'] = $content;
        return $this->capture($layout, $data);
    }

    /** Chạy 1 file view với các biến trong $__data và trả về HTML. */
    private function capture(string $template, array $__data): string
    {
        $__file = $this->basePath . '/' . $template . '.php';
        extract($__data, EXTR_SKIP);
        ob_start();
        try {
            require $__file;
        } catch (Throwable $e) {
            ob_end_clean();
            throw $e;
        }
        return (string) ob_get_clean();
    }
}
