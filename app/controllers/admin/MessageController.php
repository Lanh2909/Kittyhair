<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\App;
use App\Core\Paginator;
use App\Core\QueryFilter;
use App\Models\ContactMessageModel;

/**
 * Tin nhắn liên hệ (admin/messages.php): lọc chưa đọc, tìm kiếm, đánh dấu đã đọc / chưa đọc, xóa.
 */
final class MessageController extends AdminController
{
    protected ?string $permission = 'messages';

    private ContactMessageModel $messages;

    public function __construct()
    {
        parent::__construct();
        $this->messages = new ContactMessageModel(App::db());
    }

    public function handle(): void
    {
        if ($this->request->isPost()) {
            $this->handlePost();
        }

        $filter  = $this->request->get('filter') === 'unread' ? 'unread' : 'all';
        $keyword = $this->keyword();

        $query = (new QueryFilter())->whereLikeAny(['name', 'email', 'subject', 'message'], $keyword);
        if ($filter === 'unread') {
            $query->where('is_read = 0');
        }
        $pager = new Paginator($this->messages->count($query), PER_PAGE_ADMIN);

        $this->renderAdmin('admin/messages', 'Tin nhắn liên hệ', 'messages', [
            'rows'    => $this->messages->page($query, $pager),
            'filter'  => $filter,
            'keyword' => $keyword,
            'pager'   => $pager,
        ]);
    }

    private function handlePost(): void
    {
        $this->verifyCsrf();
        $action = $this->request->post('action');
        $id     = (int) $this->request->post('message_id');

        if ($action === 'toggle_read') {
            $this->messages->toggleRead($id);
        } elseif ($action === 'delete') {
            $this->messages->delete($id);
            App::logger()->log('message_delete', "Xóa tin nhắn liên hệ #$id.");
            $this->flash('success', 'Đã xóa tin nhắn.');
        }
        $this->redirect($this->withQuery('admin/messages.php'));
    }
}
