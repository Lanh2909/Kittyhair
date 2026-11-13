<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\App;
use App\Core\Response;
use App\Models\ContactMessageModel;
use App\Services\Validator;

/**
 * Nhận form Liên hệ gửi bằng AJAX (ajax/contact.php) và trả kết quả JSON.
 */
final class ContactController extends Controller
{
    /** Tối đa 3 tin / 10 phút cho mỗi phiên. */
    private const MAX_MESSAGES = 3;
    private const WINDOW_SECONDS = 600;

    public function send(): void
    {
        if (!$this->request->isPost()) {
            Response::json(['ok' => false, 'message' => 'Phương thức không hợp lệ.'], 405);
        }
        $this->verifyCsrf();

        $name    = $this->request->post('name');
        $email   = mb_strtolower($this->request->post('email'));
        $subject = $this->request->post('subject');
        $message = $this->request->post('message');

        if ($name === '' || $subject === '' || $message === '') {
            Response::json(['ok' => false, 'message' => 'Vui lòng điền đầy đủ các trường.'], 422);
        }
        if (mb_strlen($name) > 100 || mb_strlen($subject) > 150 || mb_strlen($message) > 2000) {
            Response::json(['ok' => false, 'message' => 'Nội dung quá dài.'], 422);
        }
        if (!Validator::isValidEmail($email)) {
            Response::json(['ok' => false, 'message' => 'Email không hợp lệ.'], 422);
        }

        // Chống gửi liên tục: chỉ giữ các mốc thời gian gửi trong 10 phút gần nhất
        $_SESSION['contact_sent'] = array_filter($_SESSION['contact_sent'] ?? [], static function (int $t): bool {
            return $t > time() - self::WINDOW_SECONDS;
        });
        if (count($_SESSION['contact_sent']) >= self::MAX_MESSAGES) {
            Response::json(['ok' => false, 'message' => 'Bạn gửi quá nhiều tin nhắn. Vui lòng thử lại sau ít phút.'], 429);
        }

        (new ContactMessageModel(App::db()))->create($name, $email, $subject, $message);
        $_SESSION['contact_sent'][] = time();
        App::logger()->log('contact', 'Tin nhắn liên hệ mới từ ' . $email . '.');

        Response::json(['ok' => true, 'message' => 'Cảm ơn bạn! Salon đã nhận được tin nhắn và sẽ phản hồi sớm.']);
    }
}
