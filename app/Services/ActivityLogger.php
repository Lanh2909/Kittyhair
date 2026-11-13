<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Request;
use App\Models\ActivityLogModel;
use Throwable;

/**
 * Ghi nhật ký hoạt động. Tự nhận diện người thực hiện: quản trị viên đang đăng nhập hoặc khách (guest).
 * Lỗi khi ghi log KHÔNG được làm hỏng nghiệp vụ chính nên mọi lỗi đều bị bắt và chỉ ghi vào error_log.
 */
final class ActivityLogger
{
    private ActivityLogModel $model;
    private Request $request;

    public function __construct(ActivityLogModel $model, Request $request)
    {
        $this->model   = $model;
        $this->request = $request;
    }

    /** @param array|null $actor ['type' => 'admin'|'guest', 'id' => ?int, 'name' => string]; null = tự nhận diện */
    public function log(string $action, string $description, ?array $actor = null): void
    {
        try {
            if ($actor === null) {
                if (!empty($_SESSION['admin_id'])) {
                    $actor = ['type' => 'admin', 'id' => (int) $_SESSION['admin_id'], 'name' => (string) ($_SESSION['admin_username'] ?? '')];
                } else {
                    $actor = ['type' => 'guest', 'id' => null, 'name' => ''];
                }
            }
            $this->model->insert(
                $actor['type'],
                $actor['id'],
                mb_substr($actor['name'], 0, 100),
                $action,
                mb_substr($description, 0, 500),
                $this->request->ip()
            );
        } catch (Throwable $e) {
            error_log('Activity log failed: ' . $e->getMessage());
        }
    }
}
