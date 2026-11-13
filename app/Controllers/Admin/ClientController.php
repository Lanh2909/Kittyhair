<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\App;
use App\Core\Paginator;
use App\Core\QueryFilter;
use App\Models\ClientModel;

/**
 * Danh sách khách hàng (admin/clients.php): tìm theo tên / SĐT / email, phân trang.
 */
final class ClientController extends AdminController
{
    protected ?string $permission = 'clients';

    public function handle(): void
    {
        $clients = new ClientModel(App::db());
        $keyword = $this->keyword();

        $filter = new QueryFilter();
        if ($keyword !== '') {
            $digits = preg_replace('/\D/', '', $keyword);
            $filter->where(
                '(c.full_name LIKE ? OR c.email LIKE ? OR c.phone_number LIKE ?)',
                [QueryFilter::like($keyword), QueryFilter::like($keyword), QueryFilter::like($digits !== '' ? $digits : $keyword)]
            );
        }
        $pager = new Paginator($clients->count($filter), PER_PAGE_ADMIN);

        $this->renderAdmin('admin/clients', 'Khách hàng', 'clients', [
            'rows'    => $clients->page($filter, $pager),
            'keyword' => $keyword,
            'pager'   => $pager,
        ]);
    }
}
