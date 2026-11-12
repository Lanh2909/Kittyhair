<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\App;
use App\Core\Paginator;
use App\Core\QueryFilter;
use App\Models\CategoryModel;
use App\Models\ServiceModel;

/**
 * Quản lý dịch vụ (admin/services.php): danh sách + tìm/lọc, thêm, sửa, xóa, upload/xem/tải/xóa ảnh dịch vụ.
 *   ?action=create | ?action=edit&id=5 -> form;  không có action -> danh sách.
 */
final class ServiceController extends AdminController
{
    protected ?string $permission = 'catalog';

    private ServiceModel $services;
    private array $categories;

    public function __construct()
    {
        parent::__construct();
        $this->services   = new ServiceModel(App::db());
        $this->categories = (new CategoryModel(App::db()))->options();
    }

    public function handle(): void
    {
        $action   = $this->request->get('action');
        $errors   = [];
        $formData = null;

        if ($this->request->isPost()) {
            $this->verifyCsrf();
            $postAction = $this->request->post('action');
            $id         = (int) $this->request->post('service_id');

            if ($postAction === 'delete') {
                $this->delete($id);
            }
            if ($postAction === 'save') {
                // Thành công thì save() chuyển hướng; quay lại đây nghĩa là có lỗi -> hiển thị lại form
                $existing = $id > 0 ? $this->services->find($id) : null;
                [$errors, $formData] = $this->save($id, $existing);
                $action = $existing ? 'edit' : 'create';
                $this->request->setQuery('id', (string) $id);
            }
        }

        if ($action === 'create' || $action === 'edit') {
            $this->form($action, $errors, $formData);
            return;
        }
        $this->index();
    }

    private function delete(int $id): void
    {
        $service = $this->services->find($id);
        if ($service === null) {
            $this->flash('danger', 'Không tìm thấy dịch vụ.');
        } else {
            $this->services->delete($id);
            App::uploads()->delete('services', $service['image']);
            App::logger()->log('service_delete', 'Xóa dịch vụ: ' . $service['service_name']);
            $this->flash('success', 'Đã xóa dịch vụ. Lịch hẹn cũ vẫn giữ nguyên tên, giá và thời lượng đã đặt.');
        }
        $this->redirect('admin/services.php');
    }

    /** @return array{0:array,1:array} [lỗi, dữ liệu để điền lại form] */
    private function save(int $id, ?array $existing): array
    {
        $data = [
            'service_name'        => $this->request->post('service_name'),
            'category_id'         => (int) $this->request->post('category_id'),
            'service_description' => $this->request->post('service_description'),
            'service_price'       => preg_replace('/\D/', '', $this->request->post('service_price')),
            'service_duration'    => (int) $this->request->post('service_duration'),
        ];
        $errors = $this->validate($id, $data);

        $newImage = null;
        $uploads  = App::uploads();
        if (!$errors) {
            $upload = $uploads->saveImage('image', 'services', UPLOAD_MAX_AVATAR);
            if ($upload['error'] !== null) {
                $errors['image'] = $upload['error'];
            }
            $newImage = $upload['file'];
        }

        if (!$errors) {
            $image = $existing['image'] ?? null;
            if ($newImage !== null) {
                $uploads->delete('services', $image);
                $image = $newImage;
            } elseif ($existing && $this->request->post('remove_image') === '1') {
                $uploads->delete('services', $image);
                $image = null;
            }

            if ($existing) {
                $this->services->update($id, $data, $image);
                App::logger()->log('service_update', 'Cập nhật dịch vụ: ' . $data['service_name']);
                $this->flash('success', 'Đã cập nhật dịch vụ.');
            } else {
                $this->services->create($data, $image);
                App::logger()->log('service_create', 'Thêm dịch vụ: ' . $data['service_name']);
                $this->flash('success', 'Đã thêm dịch vụ mới.');
            }
            $this->redirect('admin/services.php');
        }
        return [$errors, $data + ['image' => $existing['image'] ?? null]];
    }

    /** @return array<string,string> */
    private function validate(int $id, array $data): array
    {
        $errors = [];
        if (mb_strlen($data['service_name']) < 2 || mb_strlen($data['service_name']) > 100) {
            $errors['service_name'] = 'Tên dịch vụ phải từ 2 đến 100 ký tự.';
        }
        if (mb_strlen($data['service_description']) < 5 || mb_strlen($data['service_description']) > 255) {
            $errors['service_description'] = 'Mô tả phải từ 5 đến 255 ký tự.';
        }
        if (!in_array($data['category_id'], array_map('intval', array_column($this->categories, 'category_id')), true)) {
            $errors['category_id'] = 'Vui lòng chọn danh mục.';
        }
        if ($data['service_price'] === '' || (int) $data['service_price'] < 1000 || (int) $data['service_price'] > 99999999) {
            $errors['service_price'] = 'Giá phải từ 1.000đ đến 99.999.999đ.';
        }
        if ($data['service_duration'] < 5 || $data['service_duration'] > 480 || $data['service_duration'] % 5 !== 0) {
            $errors['service_duration'] = 'Thời lượng từ 5 đến 480 phút, là bội số của 5.';
        }
        if (!isset($errors['service_name']) && $this->services->nameTaken($data['service_name'], $id)) {
            $errors['service_name'] = 'Đã có dịch vụ trùng tên.';
        }
        return $errors;
    }

    private function form(string $action, array $errors, ?array $formData): void
    {
        $editing = $action === 'edit' ? $this->services->find((int) $this->request->get('id', '0')) : null;
        if ($action === 'edit' && $editing === null) {
            $this->flash('danger', 'Không tìm thấy dịch vụ.');
            $this->redirect('admin/services.php');
        }
        $form = $formData ?? $editing ?? [
            'service_name' => '', 'category_id' => $this->categories[0]['category_id'] ?? 0, 'service_description' => '',
            'service_price' => '', 'service_duration' => 30, 'image' => null,
        ];
        $pageTitle = $editing ? 'Sửa dịch vụ' : 'Thêm dịch vụ';

        $this->renderAdmin('admin/services-form', $pageTitle, 'services', [
            'editing'    => $editing,
            'form'       => $form,
            'errors'     => $errors,
            'categories' => $this->categories,
            'imageUrl'   => App::uploads()->url('services', $editing['image'] ?? ($form['image'] ?? null)),
        ]);
    }

    private function index(): void
    {
        $keyword    = $this->keyword();
        $categoryId = (int) $this->request->get('category', '0');

        $filter = (new QueryFilter())->whereLikeAny(['s.service_name', 's.service_description'], $keyword);
        if ($categoryId > 0) {
            $filter->where('s.category_id = ?', [$categoryId]);
        }
        $pager = new Paginator($this->services->count($filter), PER_PAGE_ADMIN);

        $this->renderAdmin('admin/services-list', 'Dịch vụ', 'services', [
            'rows'       => $this->services->page($filter, $pager, 'service_name'),
            'categories' => $this->categories,
            'keyword'    => $keyword,
            'categoryId' => $categoryId,
            'pager'      => $pager,
        ]);
    }
}
