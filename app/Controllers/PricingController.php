<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\App;
use App\Core\Paginator;
use App\Core\QueryFilter;
use App\Models\CategoryModel;
use App\Models\ServiceModel;

/**
 * Bảng giá dịch vụ công khai (services.php): tìm kiếm, lọc theo danh mục, phân trang.
 */
final class PricingController extends Controller
{
    private const PER_PAGE = 9;

    public function index(): void
    {
        $services   = new ServiceModel(App::db());
        $keyword    = $this->keyword();
        $categoryId = (int) $this->request->get('category', '0');

        $filter = (new QueryFilter())->whereLikeAny(['s.service_name', 's.service_description'], $keyword);
        if ($categoryId > 0) {
            $filter->where('s.category_id = ?', [$categoryId]);
        }
        $pager = new Paginator($services->count($filter), self::PER_PAGE);

        $this->render('client/services', [
            'pageTitle'  => 'Bảng giá dịch vụ',
            'categories' => (new CategoryModel(App::db()))->withServices(),
            'rows'       => $services->page($filter, $pager, 'service_price'),
            'keyword'    => $keyword,
            'categoryId' => $categoryId,
            'pager'      => $pager,
            'keep'       => ['q' => $keyword, 'category' => $categoryId ?: ''],
        ]);
    }
}
