<?php

declare(strict_types=1);

namespace App\Core;

use App\Models\ActivityLogModel;
use App\Models\AdminModel;
use App\Models\AppointmentModel;
use App\Models\ClientModel;
use App\Models\EmployeeModel;
use App\Models\RateLimitModel;
use App\Models\ScheduleModel;
use App\Models\ServiceModel;
use App\Models\SettingModel;
use App\Services\AccountService;
use App\Services\ActivityLogger;
use App\Services\AuthService;
use App\Services\BookingService;
use App\Services\Permission;
use App\Services\RateLimiter;
use App\Services\ScheduleService;
use App\Services\SettingService;
use App\Services\UploadService;

/**
 * Lớp khởi tạo ứng dụng + "kho" giữ các đối tượng dùng chung (mỗi loại chỉ tạo 1 lần trong 1 request).
 *
 * Ví dụ: App::booking() luôn trả về CÙNG một đối tượng BookingService. Các service nhận phụ thuộc
 * qua constructor (dependency injection), App chịu trách nhiệm lắp ráp chúng ở một chỗ duy nhất.
 */
final class App
{
    /** @var array<string,object> */
    private static array $instances = [];

    /** Cấu hình môi trường chạy (trước đây nằm đầu includes/bootstrap.php). */
    public static function boot(): void
    {
        date_default_timezone_set(APP_TIMEZONE);
        mb_internal_encoding('UTF-8');
        error_reporting(E_ALL);
        ini_set('display_errors', APP_DEBUG ? '1' : '0');
        Session::start();
    }

    /**
     * Trả về đối tượng dùng chung theo khóa, tạo mới bằng $factory nếu chưa có.
     * @template T of object
     * @param callable():T $factory
     * @return T
     */
    private static function shared(string $key, callable $factory): object
    {
        if (!isset(self::$instances[$key])) {
            self::$instances[$key] = $factory();
        }
        return self::$instances[$key];
    }

    public static function db(): Database
    {
        return Database::getInstance();
    }

    public static function request(): Request
    {
        return self::shared('request', static function (): Request {
            return new Request();
        });
    }

    public static function view(): View
    {
        return self::shared('view', static function (): View {
            $view = new View(dirname(__DIR__) . '/Views');
            // Biến dùng chung cho mọi giao diện
            $view->share('settings', self::settings());
            $view->share('uploads', self::uploads());
            return $view;
        });
    }

    public static function settings(): SettingService
    {
        return self::shared('settings', static function (): SettingService {
            return new SettingService(new SettingModel(self::db()));
        });
    }

    public static function logger(): ActivityLogger
    {
        return self::shared('logger', static function (): ActivityLogger {
            return new ActivityLogger(new ActivityLogModel(self::db()), self::request());
        });
    }

    public static function rateLimiter(): RateLimiter
    {
        return self::shared('rateLimiter', static function (): RateLimiter {
            return new RateLimiter(new RateLimitModel(self::db()), self::request());
        });
    }

    public static function auth(): AuthService
    {
        return self::shared('auth', static function (): AuthService {
            return new AuthService(new AdminModel(self::db()), self::rateLimiter(), self::logger(), self::request());
        });
    }

    public static function permission(): Permission
    {
        return self::shared('permission', static function (): Permission {
            return new Permission(self::auth());
        });
    }

    public static function accounts(): AccountService
    {
        return self::shared('accounts', static function (): AccountService {
            return new AccountService(new AdminModel(self::db()), self::rateLimiter(), self::logger());
        });
    }

    public static function uploads(): UploadService
    {
        return self::shared('uploads', static function (): UploadService {
            return new UploadService(self::request());
        });
    }

    public static function booking(): BookingService
    {
        return self::shared('booking', static function (): BookingService {
            return new BookingService(
                self::db(),
                new AppointmentModel(self::db()),
                new ClientModel(self::db()),
                new EmployeeModel(self::db()),
                new ServiceModel(self::db()),
                new ScheduleModel(self::db()),
                self::settings(),
                self::logger()
            );
        });
    }

    public static function schedule(): ScheduleService
    {
        return self::shared('schedule', static function (): ScheduleService {
            return new ScheduleService(new ScheduleModel(self::db()), new AppointmentModel(self::db()));
        });
    }
}
