<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Request;
use App\Models\RateLimitModel;

/**
 * Giới hạn tần suất theo khóa + IP (đăng nhập admin sai, đặt lịch dồn dập).
 *   exceeded('admin:letan', 5, 10) -> true nếu IP này đã sai ≥ 5 lần trong 10 phút với tài khoản letan.
 */
final class RateLimiter
{
    private RateLimitModel $model;
    private Request $request;

    public function __construct(RateLimitModel $model, Request $request)
    {
        $this->model   = $model;
        $this->request = $request;
    }

    public function exceeded(string $key, int $max, int $minutes): bool
    {
        return $this->model->countRecent(mb_strtolower($key), $this->request->ip(), $minutes) >= $max;
    }

    /** Ghi nhận 1 lần. */
    public function hit(string $key): void
    {
        $this->model->add(mb_strtolower($key), $this->request->ip());
    }

    /** Xóa bộ đếm (vd: đăng nhập đúng) và dọn bản ghi cũ. */
    public function clear(string $key): void
    {
        $this->model->clear(mb_strtolower($key), $this->request->ip());
        $this->model->purgeOld();
    }
}
