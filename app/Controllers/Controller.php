<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\App;
use App\Core\Csrf;
use App\Core\Flash;
use App\Core\Request;
use App\Core\Response;
use App\Core\View;

/**
 * Lớp cha của mọi Controller.
 * Controller = "người điều phối" của 1 trang: đọc Request, gọi Model/Service, chọn View để hiển thị.
 */
abstract class Controller
{
    protected Request $request;
    protected View $view;

    public function __construct()
    {
        $this->request = App::request();
        $this->view    = App::view();
    }

    /** Hiển thị view của site khách hàng trong layout chung. */
    protected function render(string $template, array $data = [], ?string $layout = 'client/layout'): void
    {
        $this->view->render($template, $data, $layout);
    }

    protected function redirect(string $path): void
    {
        Response::redirect($path);
    }

    /** $path kèm query string hiện tại (giữ nguyên bộ lọc/trang sau khi xử lý POST). */
    protected function withQuery(string $path): string
    {
        $query = $this->request->queryString();
        return $path . ($query === '' ? '' : '?' . $query);
    }

    protected function flash(string $type, string $message): void
    {
        Flash::add($type, $message);
    }

    protected function verifyCsrf(): void
    {
        Csrf::verify();
    }

    /** Từ khóa tìm kiếm ?q= (tối đa 100 ký tự). */
    protected function keyword(): string
    {
        return mb_substr($this->request->get('q'), 0, 100);
    }
}
