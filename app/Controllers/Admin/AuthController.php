<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\Controller;
use App\Core\App;

/**
 * Đăng nhập (admin/login.php) và đăng xuất (admin/logout.php).
 * Kế thừa Controller (không phải AdminController) vì trang đăng nhập không yêu cầu đã đăng nhập.
 */
final class AuthController extends Controller
{
    public function login(): void
    {
        $auth = App::auth();
        if ($auth->current() !== null) {
            $this->redirect('admin/index.php');
        }

        $error    = null;
        $username = '';

        if ($this->request->isPost()) {
            $this->verifyCsrf();
            $username = $this->request->post('username');
            $password = $this->request->raw('password');
            if ($username === '' || $password === '') {
                $error = 'Vui lòng nhập tên đăng nhập và mật khẩu.';
            } else {
                $error = $auth->login($username, $password);
                if ($error === null) {
                    $this->redirect('admin/index.php');
                }
            }
        }

        // Trang độc lập, không dùng layout quản trị
        $this->render('admin/login', ['error' => $error, 'username' => $username], null);
    }

    /** Đăng xuất bằng POST kèm CSRF (link GET có thể bị trang khác lợi dụng để loại người dùng ra). */
    public function logout(): void
    {
        if ($this->request->isPost()) {
            $this->verifyCsrf();
            if (App::auth()->current() !== null) {
                App::auth()->logout();
            }
        }
        $this->redirect('admin/login.php');
    }
}
