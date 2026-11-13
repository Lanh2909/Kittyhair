<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\App;
use App\Helpers\Format;
use App\Models\AppointmentModel;
use App\Models\DashboardModel;

/**
 * Bảng điều khiển (admin/index.php): thẻ thống kê + 8 lịch hẹn sắp tới.
 */
final class DashboardController extends AdminController
{
    protected ?string $permission = 'dashboard';

    public function handle(): void
    {
        $stats    = (new DashboardModel(App::db()))->stats();
        $upcoming = App::booking()->attachServices((new AppointmentModel(App::db()))->upcoming(8));

        $cards = [
            ['Lịch hẹn sắp tới', (int) $stats['upcoming'], 'fa-calendar-check', 'primary'],
            ['Lịch hẹn hôm nay', (int) $stats['today'], 'fa-calendar-day', 'success'],
            ['Khách hàng', (int) $stats['clients'], 'fa-users', 'info'],
            ['Doanh thu dự kiến', Format::money($stats['expected_revenue']), 'fa-coins', 'warning'],
        ];

        $this->renderAdmin('admin/dashboard', 'Bảng điều khiển', 'dashboard', [
            'stats'    => $stats,
            'cards'    => $cards,
            'upcoming' => $upcoming,
        ]);
    }
}
