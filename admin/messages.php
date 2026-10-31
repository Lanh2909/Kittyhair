<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/init.php';
require_permission('messages');

if (is_post()) {
    verify_csrf();
    $action = post('action');
    $id     = (int) post('message_id');

    if ($action === 'toggle_read') {
        db_query('UPDATE contact_messages SET is_read = 1 - is_read WHERE message_id = ?', [$id]);
    } elseif ($action === 'delete') {
        db_query('DELETE FROM contact_messages WHERE message_id = ?', [$id]);
        log_activity('message_delete', "Xóa tin nhắn liên hệ #$id.");
        flash('success', 'Đã xóa tin nhắn.');
    }
    redirect('admin/messages.php' . (empty($_SERVER['QUERY_STRING']) ? '' : '?' . $_SERVER['QUERY_STRING']));
}

$filter  = get('filter') === 'unread' ? 'unread' : 'all';
$keyword = mb_substr(get('q'), 0, 100);
$where   = ['1 = 1'];
$params  = [];
if ($filter === 'unread') {
    $where[] = 'is_read = 0';
}
if ($keyword !== '') {
    $where[] = '(name LIKE ? OR email LIKE ? OR subject LIKE ? OR message LIKE ?)';
    array_push($params, ...array_fill(0, 4, like_param($keyword)));
}
$whereSql = implode(' AND ', $where);

$total = (int) db_value("SELECT COUNT(*) FROM contact_messages WHERE $whereSql", $params);
$pager = paginate($total, PER_PAGE_ADMIN);
$rows  = db_all("SELECT * FROM contact_messages WHERE $whereSql ORDER BY created_at DESC, message_id DESC LIMIT {$pager['per_page']} OFFSET {$pager['offset']}", $params);

$pageTitle  = 'Tin nhắn liên hệ';
$activeMenu = 'messages';
require __DIR__ . '/templates/header.php';
?>
<h1 class="h3 mb-3 text-gray-800">Tin nhắn liên hệ</h1>
<div class="card shadow mb-4">
    <div class="card-body">
        <div class="filter-pills">
            <a href="?filter=all&q=<?= e(urlencode($keyword)) ?>" class="<?= $filter === 'all' ? 'active' : '' ?>">Tất cả</a>
            <a href="?filter=unread&q=<?= e(urlencode($keyword)) ?>" class="<?= $filter === 'unread' ? 'active' : '' ?>">Chưa đọc</a>
        </div>
        <?= render_search_form($keyword, 'Tìm theo tên, email, tiêu đề hoặc nội dung…', ['filter' => $filter === 'unread' ? 'unread' : '']) ?>

        <?php if (!$rows): ?><p class="text-center text-muted py-4">Không có tin nhắn nào.</p><?php endif; ?>
        <?php foreach ($rows as $row): ?>
            <details class="border rounded mb-2 p-3 <?= (int) $row['is_read'] ? '' : 'bg-light' ?>">
                <summary>
                    <?= (int) $row['is_read'] ? '' : '<span class="badge badge-primary mr-1">Mới</span>' ?>
                    <strong><?= e($row['subject']) ?></strong>
                    <span class="text-muted small"> – <?= e($row['name']) ?> · <?= e(format_datetime($row['created_at'])) ?></span>
                </summary>
                <div class="mt-3">
                    <p class="small text-muted mb-2"><i class="far fa-envelope"></i> <?= e($row['email']) ?></p>
                    <p style="white-space: pre-wrap;"><?= e($row['message']) ?></p>
                    <a class="btn btn-sm btn-primary" href="mailto:<?= e($row['email']) ?>?subject=<?= e(rawurlencode('Re: ' . $row['subject'])) ?>"><i class="fas fa-reply"></i> Trả lời qua email</a>
                    <form method="post" class="d-inline">
                        <?= csrf_field() ?><input type="hidden" name="action" value="toggle_read"><input type="hidden" name="message_id" value="<?= (int) $row['message_id'] ?>">
                        <button class="btn btn-sm btn-outline-primary" type="submit"><?= (int) $row['is_read'] ? 'Đánh dấu chưa đọc' : 'Đánh dấu đã đọc' ?></button>
                    </form>
                    <form method="post" class="d-inline" data-confirm="Xóa tin nhắn này?">
                        <?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="message_id" value="<?= (int) $row['message_id'] ?>">
                        <button class="btn btn-sm btn-danger" type="submit"><i class="far fa-trash-alt"></i> Xóa</button>
                    </form>
                </div>
            </details>
        <?php endforeach; ?>

        <div class="d-flex justify-content-between align-items-center flex-wrap mt-3">
            <?= render_pager_summary($pager) ?>
            <?= render_pagination($pager, ['q' => $keyword, 'filter' => $filter === 'unread' ? 'unread' : '']) ?>
        </div>
    </div>
</div>
<?php require __DIR__ . '/templates/footer.php'; ?>
