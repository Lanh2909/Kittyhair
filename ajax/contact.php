<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

if (!is_post()) {
    json_response(['ok' => false, 'message' => 'Phương thức không hợp lệ.'], 405);
}
verify_csrf();

$name    = post('name');
$email   = mb_strtolower(post('email'));
$subject = post('subject');
$message = post('message');

if ($name === '' || $subject === '' || $message === '') {
    json_response(['ok' => false, 'message' => 'Vui lòng điền đầy đủ các trường.'], 422);
}
if (mb_strlen($name) > 100 || mb_strlen($subject) > 150 || mb_strlen($message) > 2000) {
    json_response(['ok' => false, 'message' => 'Nội dung quá dài.'], 422);
}
if (!is_valid_email($email)) {
    json_response(['ok' => false, 'message' => 'Email không hợp lệ.'], 422);
}

// Chống gửi liên tục: tối đa 3 tin / 10 phút cho mỗi phiên.
$_SESSION['contact_sent'] = array_filter($_SESSION['contact_sent'] ?? [], static function (int $t): bool {
    return $t > time() - 600;
});
if (count($_SESSION['contact_sent']) >= 3) {
    json_response(['ok' => false, 'message' => 'Bạn gửi quá nhiều tin nhắn. Vui lòng thử lại sau ít phút.'], 429);
}

db_query(
    'INSERT INTO contact_messages (name, email, subject, message) VALUES (?, ?, ?, ?)',
    [$name, $email, $subject, $message]
);
$_SESSION['contact_sent'][] = time();
log_activity('contact', 'Tin nhắn liên hệ mới từ ' . $email . '.');

json_response(['ok' => true, 'message' => 'Cảm ơn bạn! Salon đã nhận được tin nhắn và sẽ phản hồi sớm.']);
