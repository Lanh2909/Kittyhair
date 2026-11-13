<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\App;
use App\Core\Paginator;
use App\Core\QueryFilter;
use App\Models\CategoryModel;
use App\Models\ServiceModel;

/**
 * Danh mục dịch vụ (admin/service-categories.php): thêm, đổi tên, xóa (dịch vụ chuyển về "Chưa phân loại").
 */
final class CategoryController extends AdminController
{
    protected ?string $permission = 'catalog';

    private CategoryModel $categories;

    public function __construct()
    {
        parent::__construct();
        $this->categories = new CategoryModel(App::db());
    }

    public function handle(): void
    {
        if ($this->request->isPost()) {
            $this->verifyCsrf();
            $action = $this->request->post('action');
            $id     = (int) $this->request->post('category_id');

            if ($action === 'save') {
                $this->save($id, $this->request->post('category_name'));
            } elseif ($action === 'delete') {
                $this->delete($id);
            }
            $this->redirect($this->withQuery('admin/service-categories.php'));
        }

        $keyword = $this->keyword();
        $filter  = (new QueryFilter())->whereLikeAny(['c.category_name'], $keyword);
        $pager   = new Paginator($this->categories->count($filter), PER_PAGE_ADMIN);

        $this->renderAdmin('admin/categories', 'Danh mục dịch vụ', 'categories', [
            'rows'    => $this->categories->page($filter, $pager),
            'keyword' => $keyword,
            'pager'   => $pager,
        ]);
    }

    /** Thêm mới ($id = 0) hoặc đổi tên danh mục. */
    private function save(int $id, string $name): void
    {
        $existing = $id > 0 ? $this->categories->find($id) : null;
        if ($id > 0 && $existing === null) {
            $this->flash('danger', 'Không tìm thấy danh mục.');
        } elseif ($existing && (int) $existing['is_default'] === 1) {
            $this->flash('danger', 'Không thể sửa danh mục mặc định.');
        } elseif (mb_strlen($name) < 2 || mb_strlen($name) > 100) {
            $this->flash('danger', 'Tên danh mục phải từ 2 đến 100 ký tự.');
        } elseif ($this->categories->nameTaken($name, $id)) {
            $this->flash('danger', 'Tên danh mục đã tồn tại.');
        } elseif ($existing) {
            $this->categories->rename($id, $name);
            App::logger()->log('category_update', 'Đổi tên danh mục: ' . $existing['category_name'] . ' → ' . $name);
            $this->flash('success', 'Đã cập nhật danh mục.');
        } else {
            $this->categories->create($name);
            App::logger()->log('category_create', 'Thêm danh mục: ' . $name);
            $this->flash('success', 'Đã thêm danh mục mới.');
        }
    }

    /** Xóa danh mục: chuyển dịch vụ sang danh mục mặc định rồi xóa, trong 1 transaction. */
    private function delete(int $id): void
    {
        $category = $this->categories->find($id);
        $default  = $this->categories->defaultId();
        if ($category === null) {
            $this->flash('danger', 'Không tìm thấy danh mục.');
            return;
        }
        if ((int) $category['is_default'] === 1 || $default === 0) {
            $this->flash('danger', 'Không thể xóa danh mục mặc định.');
            return;
        }

        $services = new ServiceModel(App::db());
        $moved = App::db()->transaction(function () use ($services, $id, $default): int {
            $moved = $services->moveCategory($id, $default);
            $this->categories->delete($id);
            return $moved;
        });
        App::logger()->log('category_delete', 'Xóa danh mục: ' . $category['category_name'] . " ($moved dịch vụ chuyển sang danh mục mặc định)");
        $this->flash('success', 'Đã xóa danh mục' . ($moved ? " và chuyển $moved dịch vụ sang \"Chưa phân loại\"" : '') . '.');
    }
}
