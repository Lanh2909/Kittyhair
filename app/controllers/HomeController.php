<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\App;
use App\Models\CategoryModel;
use App\Models\EmployeeModel;
use App\Models\ServiceModel;

/**
 * Trang chủ (index.php): banner, giới thiệu, dịch vụ nổi bật, bảng giá rút gọn, thư viện, đội ngũ, đánh giá, liên hệ.
 */
final class HomeController extends Controller
{
    private const HERO_SLIDES = [
        ['img' => 'hero-1.jpg', 'eyebrow' => 'Salon tóc nữ chuyên nghiệp', 'title' => 'Mái tóc đẹp – Tự tin tỏa sáng mỗi ngày', 'text' => 'Đội ngũ stylist tận tâm cùng sản phẩm chăm sóc tóc cao cấp, thiết kế kiểu tóc riêng cho từng khuôn mặt.'],
        ['img' => 'hero-2.jpg', 'eyebrow' => 'Nhuộm màu thời thượng', 'title' => 'Sắc màu cá tính, bền màu và an toàn', 'text' => 'Từ tông nâu tự nhiên đến balayage, highlight – nhuộm với công nghệ bảo vệ liên kết tóc.'],
        ['img' => 'hero-3.jpg', 'eyebrow' => 'Tóc cô dâu & dự tiệc', 'title' => 'Rạng rỡ trong những khoảnh khắc đáng nhớ', 'text' => 'Búi, tết, uốn lọn tạo kiểu cho tiệc cưới, sự kiện và chụp ảnh – đặt lịch trước để được ưu tiên.'],
    ];

    private const FEATURED = [
        ['fa-cut', 'Cắt và Tạo kiểu', 'Tư vấn kiểu tóc hợp khuôn mặt, cắt layer, tỉa mái mềm mại và hiện đại.'],
        ['fa-palette', 'Nhuộm tóc', 'Nhuộm thời trang, balayage, highlight với thuốc nhuộm dịu nhẹ, lên màu chuẩn.'],
        ['fa-magic', 'Uốn và Duỗi', 'Uốn gợn sóng Hàn Quốc, duỗi Keratin cho mái tóc bồng bềnh hoặc suôn mượt.'],
        ['fa-spa', 'Phục hồi và Spa', 'Hấp collagen, ủ Keratin, gội đầu dưỡng sinh giúp tóc khỏe và óng ả.'],
    ];

    private const REVIEWS = [
        ['review-1.jpg', 'Nguyễn Thu Hà', 'Stylist tư vấn rất kỹ, kiểu layer mới hợp với mình hơn mình tưởng. Đặt lịch online tiện, đến là được phục vụ ngay.'],
        ['review-2.jpg', 'Trần Bảo Ngọc', 'Nhuộm màu lên chuẩn, tóc vẫn mềm mượt. Không gian salon sạch sẽ, nhân viên nhẹ nhàng và chuyên nghiệp.'],
        ['review-3.jpg', 'Lê Diệu Linh', 'Làm tóc dự tiệc cưới bạn thân xinh hơn mong đợi. Chắc chắn sẽ quay lại làm liệu trình phục hồi.'],
    ];

    public function index(): void
    {
        $db = App::db();
        $this->render('client/home', [
            'categories'         => (new CategoryModel($db))->withServices(),
            'servicesByCategory' => (new ServiceModel($db))->groupedByCategory('service_price'),
            'employees'          => (new EmployeeModel($db))->all(),
            'heroSlides'         => self::HERO_SLIDES,
            'featured'           => self::FEATURED,
            'reviews'            => self::REVIEWS,
        ]);
    }
}
