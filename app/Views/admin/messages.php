<?php

declare(strict_types=1);

use App\Core\Csrf;
use App\Helpers\Format;
use App\Helpers\SearchForm;

/**
 * Tin nhắn liên hệ (TV2). Biến: $rows, $filter, $keyword, $pager.
 */
?>
<h1 class="h3 mb-3 text-gray-800">Tin nhắn liên hệ</h1>
<div class="card shadow mb-4">
    <div class="card-body">
        <div class="filter-pills">
            <a href="?filter=all&q=<?= e(urlencode($keyword)) ?>" class="<?= $filter === 'all' ? 'active' : '' ?>">Tất cả</a>
            <a href="?filter=unread&q=<?= e(urlencode($keyword)) ?>" class="<?= $filter === 'unread' ? 'active' : '' ?>">Chưa đọc</a>
        </div>
        <?= SearchForm::render($keyword, 'Tìm theo tên, email, tiêu đề hoặc nội dung…', ['filter' => $filter === 'unread' ? 'unread' : '']) ?>

        <?php if (!$rows): ?><p class="text-center text-muted py-4">Không có tin nhắn nào.</p><?php endif; ?>
        <?php foreach ($rows as $row): ?>
            <details class="border rounded mb-2 p-3 <?= (int) $row['is_read'] ? '' : 'bg-light' ?>">
                <summary>
                    <?= (int) $row['is_read'] ? '' : '<span class="badge badge-primary mr-1">Mới</span>' ?>
                    <strong><?= e($row['subject']) ?></strong>
                    <span class="text-muted small"> – <?= e($row['name']) ?> · <?= e(Format::datetime($row['created_at'])) ?></span>
                </summary>
                <div class="mt-3">
                    <p class="small text-muted mb-2"><i class="far fa-envelope"></i> <?= e($row['email']) ?></p>
                    <p style="white-space: pre-wrap;"><?= e($row['message']) ?></p>
                    <a class="btn btn-sm btn-primary" href="mailto:<?= e($row['email']) ?>?subject=<?= e(rawurlencode('Re: ' . $row['subject'])) ?>"><i class="fas fa-reply"></i> Trả lời qua email</a>
                    <form method="post" class="d-inline">
                        <?= Csrf::field() ?><input type="hidden" name="action" value="toggle_read"><input type="hidden" name="message_id" value="<?= (int) $row['message_id'] ?>">
                        <button class="btn btn-sm btn-outline-primary" type="submit"><?= (int) $row['is_read'] ? 'Đánh dấu chưa đọc' : 'Đánh dấu đã đọc' ?></button>
                    </form>
                    <form method="post" class="d-inline" data-confirm="Xóa tin nhắn này?">
                        <?= Csrf::field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="message_id" value="<?= (int) $row['message_id'] ?>">
                        <button class="btn btn-sm btn-danger" type="submit"><i class="far fa-trash-alt"></i> Xóa</button>
                    </form>
                </div>
            </details>
        <?php endforeach; ?>

        <div class="d-flex justify-content-between align-items-center flex-wrap mt-3">
            <?= $pager->summary() ?>
            <?= $pager->links(['q' => $keyword, 'filter' => $filter === 'unread' ? 'unread' : '']) ?>
        </div>
    </div>
</div>
