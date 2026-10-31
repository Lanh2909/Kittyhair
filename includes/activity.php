<?php

declare(strict_types=1);

/**
 * Ghi nhật ký hoạt động. Tự nhận diện người thực hiện: quản trị viên hoặc khách đặt lịch (guest).
 * Lỗi ghi log không được làm hỏng nghiệp vụ chính.
 */
function log_activity(string $action, string $description, ?array $actor = null): void
{
    try {
        if ($actor === null) {
            if (!empty($_SESSION['admin_id'])) {
                $actor = ['type' => 'admin', 'id' => (int) $_SESSION['admin_id'], 'name' => (string) ($_SESSION['admin_username'] ?? '')];
            } else {
                $actor = ['type' => 'guest', 'id' => null, 'name' => ''];
            }
        }
        db_query(
            'INSERT INTO activity_logs (actor_type, actor_id, actor_name, action, description, ip_address)
             VALUES (?, ?, ?, ?, ?, ?)',
            [$actor['type'], $actor['id'], mb_substr($actor['name'], 0, 100), $action, mb_substr($description, 0, 500), client_ip()]
        );
    } catch (Throwable $e) {
        error_log('Activity log failed: ' . $e->getMessage());
    }
}
