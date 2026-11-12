-- =====================================================================
-- Kitty Hair - Cơ sở dữ liệu hệ thống đặt lịch làm tóc nữ
-- Charset: utf8mb4 | Engine: InnoDB | MySQL 5.7+ / MariaDB 10.3+
-- Cách dùng: import file này trong phpMyAdmin (file tự tạo database).
-- Tài khoản mẫu: xem README.md
-- =====================================================================

SET NAMES utf8mb4;
SET time_zone = '+07:00';
SET FOREIGN_KEY_CHECKS = 0;

CREATE DATABASE IF NOT EXISTS `kitty_hair` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `kitty_hair`;

DROP TABLE IF EXISTS `activity_logs`, `rate_limits`, `contact_messages`, `settings`,
    `services_booked`, `appointments`, `employees_schedule`, `services`, `service_categories`,
    `employees`, `clients`, `admins`;

-- ---------------------------------------------------------------------
-- Quản trị viên
-- ---------------------------------------------------------------------
CREATE TABLE `admins` (
  `admin_id`      INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `username`      VARCHAR(50)  NOT NULL,
  `full_name`     VARCHAR(100) NOT NULL,
  `email`         VARCHAR(100) NOT NULL,
  `password_hash` VARCHAR(255) NOT NULL,
  `avatar`        VARCHAR(100) DEFAULT NULL,
  `role`          ENUM('admin','staff') NOT NULL DEFAULT 'staff' COMMENT 'admin = Quản trị viên (toàn quyền), staff = Lễ tân (quyền hạn chế)',
  `is_active`     TINYINT(1)   NOT NULL DEFAULT 1 COMMENT '0 = tài khoản bị khóa, không đăng nhập được',
  `must_change_password` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1 = bắt buộc đổi mật khẩu ở lần đăng nhập kế tiếp',
  `security_stamp` CHAR(32)    NOT NULL COMMENT 'Đổi khi đặt lại mật khẩu/đổi quyền để vô hiệu các phiên đăng nhập cũ',
  `last_login_at` DATETIME     DEFAULT NULL,
  `created_at`    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`admin_id`),
  UNIQUE KEY `uq_admins_username` (`username`),
  UNIQUE KEY `uq_admins_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Khách hàng: KHÔNG có tài khoản. Bản ghi được tạo/cập nhật tự động khi khách đặt lịch
-- (khách được nhận diện theo email, giống bản barbershop gốc).
-- ---------------------------------------------------------------------
CREATE TABLE `clients` (
  `client_id`    INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `full_name`    VARCHAR(100) NOT NULL,
  `phone_number` VARCHAR(15)  NOT NULL COMMENT 'Chuẩn hóa dạng 0xxxxxxxxx (10 số)',
  `email`        VARCHAR(100) NOT NULL,
  `created_at`   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`client_id`),
  UNIQUE KEY `uq_clients_email` (`email`),
  KEY `idx_clients_phone` (`phone_number`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Nhân viên (stylist)
-- ---------------------------------------------------------------------
CREATE TABLE `employees` (
  `employee_id`  INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `full_name`    VARCHAR(100) NOT NULL,
  `position`     VARCHAR(100) NOT NULL DEFAULT 'Stylist',
  `phone_number` VARCHAR(15)  NOT NULL,
  `email`        VARCHAR(100) NOT NULL,
  `avatar`       VARCHAR(100) DEFAULT NULL,
  PRIMARY KEY (`employee_id`),
  UNIQUE KEY `uq_employees_phone` (`phone_number`),
  UNIQUE KEY `uq_employees_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Lịch làm việc: day_id theo ISO-8601 (1 = Thứ Hai ... 7 = Chủ Nhật)
CREATE TABLE `employees_schedule` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `employee_id` INT UNSIGNED NOT NULL,
  `day_id`      TINYINT UNSIGNED NOT NULL,
  `from_hour`   TIME NOT NULL,
  `to_hour`     TIME NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_schedule_employee_day` (`employee_id`, `day_id`),
  CONSTRAINT `fk_schedule_employee` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`employee_id`) ON DELETE CASCADE,
  CONSTRAINT `chk_schedule_day` CHECK (`day_id` BETWEEN 1 AND 7)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Dịch vụ
-- ---------------------------------------------------------------------
CREATE TABLE `service_categories` (
  `category_id`   INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `category_name` VARCHAR(100) NOT NULL,
  `is_default`    TINYINT(1)   NOT NULL DEFAULT 0 COMMENT 'Danh mục mặc định, không được xóa/đổi tên',
  PRIMARY KEY (`category_id`),
  UNIQUE KEY `uq_categories_name` (`category_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `services` (
  `service_id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `category_id`         INT UNSIGNED NOT NULL,
  `service_name`        VARCHAR(100) NOT NULL,
  `service_description` VARCHAR(255) NOT NULL,
  `service_price`       DECIMAL(12,0) NOT NULL COMMENT 'VND',
  `service_duration`    SMALLINT UNSIGNED NOT NULL COMMENT 'Phút',
  `image`               VARCHAR(100) DEFAULT NULL,
  PRIMARY KEY (`service_id`),
  KEY `idx_services_category` (`category_id`),
  CONSTRAINT `fk_services_category` FOREIGN KEY (`category_id`) REFERENCES `service_categories` (`category_id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Lịch hẹn
-- ---------------------------------------------------------------------
CREATE TABLE `appointments` (
  `appointment_id`      INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `client_id`           INT UNSIGNED NOT NULL,
  `employee_id`         INT UNSIGNED NOT NULL,
  `start_time`          DATETIME NOT NULL,
  `end_time_expected`   DATETIME NOT NULL,
  `note`                VARCHAR(500) DEFAULT NULL,
  `reference_image`     VARCHAR(100) DEFAULT NULL COMMENT 'Ảnh kiểu tóc tham khảo (riêng tư)',
  `canceled`            TINYINT(1) NOT NULL DEFAULT 0,
  `canceled_by`         ENUM('client','admin') DEFAULT NULL COMMENT 'Người yêu cầu hủy: client = khách, admin = salon',
  `cancellation_reason` VARCHAR(500) DEFAULT NULL,
  `date_created`        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`appointment_id`),
  KEY `idx_appointments_employee_time` (`employee_id`, `start_time`, `end_time_expected`),
  KEY `idx_appointments_client_time` (`client_id`, `start_time`),
  CONSTRAINT `fk_appointments_client` FOREIGN KEY (`client_id`) REFERENCES `clients` (`client_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_appointments_employee` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`employee_id`) ON DELETE RESTRICT,
  CONSTRAINT `chk_appointments_time` CHECK (`end_time_expected` > `start_time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dịch vụ của từng lịch hẹn (lưu snapshot tên/giá/thời lượng tại thời điểm đặt)
CREATE TABLE `services_booked` (
  `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `appointment_id` INT UNSIGNED NOT NULL,
  `service_id`     INT UNSIGNED DEFAULT NULL,
  `service_name`   VARCHAR(100) NOT NULL,
  `price`          DECIMAL(12,0) NOT NULL,
  `duration`       SMALLINT UNSIGNED NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_booked_appointment` (`appointment_id`),
  CONSTRAINT `fk_booked_appointment` FOREIGN KEY (`appointment_id`) REFERENCES `appointments` (`appointment_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_booked_service` FOREIGN KEY (`service_id`) REFERENCES `services` (`service_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Cài đặt, liên hệ, nhật ký, chống dò mật khẩu
-- ---------------------------------------------------------------------
CREATE TABLE `settings` (
  `setting_key`   VARCHAR(50)  NOT NULL,
  `setting_value` VARCHAR(500) NOT NULL DEFAULT '',
  PRIMARY KEY (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `contact_messages` (
  `message_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name`       VARCHAR(100) NOT NULL,
  `email`      VARCHAR(100) NOT NULL,
  `subject`    VARCHAR(150) NOT NULL,
  `message`    TEXT NOT NULL,
  `is_read`    TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`message_id`),
  KEY `idx_messages_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `activity_logs` (
  `log_id`      INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `actor_type`  ENUM('admin','guest') NOT NULL COMMENT 'guest = khách đặt lịch (không có tài khoản)',
  `actor_id`    INT UNSIGNED DEFAULT NULL,
  `actor_name`  VARCHAR(100) NOT NULL DEFAULT '',
  `action`      VARCHAR(50)  NOT NULL,
  `description` VARCHAR(500) NOT NULL,
  `ip_address`  VARCHAR(45)  NOT NULL DEFAULT '',
  `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`log_id`),
  KEY `idx_logs_created` (`created_at`),
  KEY `idx_logs_actor` (`actor_type`, `actor_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Giới hạn tần suất: đăng nhập admin sai, đặt lịch dồn dập từ một IP
CREATE TABLE `rate_limits` (
  `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `identifier`   VARCHAR(100) NOT NULL,
  `ip_address`   VARCHAR(45)  NOT NULL,
  `attempted_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_rate_identifier` (`identifier`, `ip_address`, `attempted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- =====================================================================
-- DỮ LIỆU MẪU
-- =====================================================================

-- Quản trị viên: admin / Admin@123 ; Lễ tân (quyền hạn chế): letan / Letan@123
INSERT INTO `admins` (`username`, `full_name`, `email`, `password_hash`, `role`, `security_stamp`) VALUES
('admin', 'Quản trị viên Kitty Hair', 'admin@kittyhair.vn', '$2y$10$zU4u0imWQCfNJUkTpaiH4uW9xwTgTIkrPyEbDVMo0iPed0VzDM.d2', 'admin', '6fa9615057dd1f33c09b123c418722da'),
('letan', 'Nguyễn Thanh Hương', 'letan@kittyhair.vn', '$2y$10$pNKkS3V6.TFS5tnK5JoEQeSYy09U82AmFu51Pb8rYGfF3MS9SAlHW', 'staff', '9656dd3c42ce46f69ef56711c2bef61d');

INSERT INTO `settings` (`setting_key`, `setting_value`) VALUES
('salon_name',        'Kitty Hair'),
('salon_slogan',      'Salon tóc nữ – Đẹp từ mái tóc'),
('salon_address',     'Số 25 Lý Thường Kiệt, Quận Hoàn Kiếm, Hà Nội'),
('salon_phone',       '0901234567'),
('salon_email',       'lienhe@kittyhair.vn'),
('open_time',         '08:30'),
('close_time',        '21:00'),
('slot_step',         '30'),
('booking_days_ahead','14'),
('min_notice_minutes','60'),
('facebook_url',      'https://facebook.com/kittyhair.vn'),
('instagram_url',     'https://instagram.com/kittyhair.vn'),
('tiktok_url',        'https://tiktok.com/@kittyhair.vn');

INSERT INTO `service_categories` (`category_id`, `category_name`, `is_default`) VALUES
(1, 'Chưa phân loại', 1),
(2, 'Cắt & Tạo kiểu', 0),
(3, 'Nhuộm tóc', 0),
(4, 'Uốn & Duỗi', 0),
(5, 'Phục hồi & Chăm sóc', 0),
(6, 'Gội đầu & Spa tóc', 0);

INSERT INTO `services` (`service_id`, `category_id`, `service_name`, `service_description`, `service_price`, `service_duration`) VALUES
(1, 2, 'Cắt tóc nữ',               'Tư vấn kiểu phù hợp khuôn mặt, cắt tạo form và sấy tạo kiểu hoàn thiện.', 200000, 45),
(2, 2, 'Cắt tỉa tạo kiểu',         'Tỉa layer, tỉa ngọn giữ form tóc gọn gàng và mềm mại.', 150000, 30),
(3, 2, 'Cắt tóc mái',              'Cắt và tạo kiểu mái thưa, mái bay, mái ngố theo yêu cầu.', 50000, 15),
(4, 2, 'Làm tóc dự tiệc',          'Búi, tết, uốn lọn tạo kiểu cho tiệc cưới, sự kiện, chụp ảnh.', 350000, 60),
(5, 3, 'Nhuộm tóc thời trang',     'Nhuộm toàn đầu với thuốc nhuộm không amoniac, lên màu chuẩn và bền.', 650000, 120),
(6, 3, 'Nhuộm Balayage / Highlight','Kỹ thuật loang màu tự nhiên, tạo chiều sâu cho mái tóc.', 1500000, 180),
(7, 3, 'Tẩy tóc',                  'Nâng tone an toàn bằng sản phẩm bảo vệ liên kết tóc.', 500000, 90),
(8, 3, 'Nhuộm phủ chân tóc',       'Dặm chân tóc mới mọc cho màu đồng đều, tự nhiên.', 400000, 75),
(9, 4, 'Uốn xoăn lọn to',          'Uốn lọn to bồng bềnh, giữ nếp lâu, ít hư tổn.', 900000, 150),
(10, 4, 'Uốn gợn sóng Hàn Quốc',   'Gợn sóng mềm mại phong cách Hàn Quốc, tạo độ phồng tự nhiên.', 1100000, 150),
(11, 4, 'Duỗi Keratin',            'Duỗi thẳng mượt kết hợp phục hồi Keratin, tóc suôn mềm.', 1200000, 150),
(12, 4, 'Ép tóc thẳng mượt',       'Ép tóc thẳng bằng nhiệt, tạo độ bóng và vào nếp.', 700000, 120),
(13, 5, 'Hấp phục hồi Collagen',   'Cấp ẩm sâu, phục hồi tóc khô xơ, gãy rụng.', 350000, 45),
(14, 5, 'Ủ dưỡng Keratin',         'Bổ sung Keratin giúp tóc chắc khỏe, óng ả.', 450000, 60),
(15, 5, 'Liệu trình phục hồi tóc hư tổn','Phục hồi tóc hư tổn nặng do hóa chất và nhiệt.', 600000, 75),
(16, 5, 'Chăm sóc da đầu',         'Làm sạch sâu, cân bằng dầu, giảm gàu và ngứa da đầu.', 300000, 45),
(17, 6, 'Gội đầu dưỡng sinh',      'Gội thảo dược kết hợp massage đầu, cổ, vai gáy thư giãn.', 150000, 45),
(18, 6, 'Gội sấy',                 'Gội sạch và sấy tạo nếp nhanh gọn.', 80000, 30),
(19, 6, 'Massage thư giãn vai gáy','Massage giảm căng thẳng, thư giãn sau liệu trình làm tóc.', 200000, 30);

INSERT INTO `employees` (`employee_id`, `full_name`, `position`, `phone_number`, `email`, `avatar`) VALUES
(1, 'Nguyễn Thị Minh Anh', 'Giám đốc sáng tạo', '0912345601', 'minhanh@kittyhair.vn', 'stylist-1.jpg'),
(2, 'Trần Ngọc Lan',       'Chuyên gia nhuộm màu', '0912345602', 'ngoclan@kittyhair.vn', 'stylist-2.jpg'),
(3, 'Lê Phương Thảo',      'Chuyên gia uốn – duỗi', '0912345603', 'phuongthao@kittyhair.vn', 'stylist-3.jpg'),
(4, 'Phạm Khánh Linh',     'Stylist cắt tạo kiểu', '0912345604', 'khanhlinh@kittyhair.vn', 'stylist-4.jpg');

-- Lịch làm việc (1 = Thứ Hai ... 7 = Chủ Nhật). Dữ liệu mẫu cho làm việc cả tuần để lịch hẹn mẫu luôn hợp lệ;
-- quản trị viên có thể tắt ngày nghỉ trong mục "Lịch làm việc".
INSERT INTO `employees_schedule` (`employee_id`, `day_id`, `from_hour`, `to_hour`) VALUES
(1, 1, '09:00', '18:00'),
(1, 2, '09:00', '18:00'),
(1, 3, '09:00', '18:00'),
(1, 4, '09:00', '18:00'),
(1, 5, '09:00', '18:00'),
(1, 6, '09:00', '20:00'),
(1, 7, '09:00', '20:00'),
(2, 1, '10:00', '20:00'),
(2, 2, '10:00', '20:00'),
(2, 3, '10:00', '20:00'),
(2, 4, '10:00', '20:00'),
(2, 5, '10:00', '20:00'),
(2, 6, '10:00', '20:00'),
(2, 7, '10:00', '20:00'),
(3, 1, '10:00', '20:00'),
(3, 2, '10:00', '20:00'),
(3, 3, '10:00', '20:00'),
(3, 4, '10:00', '20:00'),
(3, 5, '10:00', '20:00'),
(3, 6, '10:00', '18:00'),
(3, 7, '10:00', '18:00'),
(4, 1, '09:00', '21:00'),
(4, 2, '09:00', '21:00'),
(4, 3, '09:00', '21:00'),
(4, 4, '09:00', '21:00'),
(4, 5, '09:00', '21:00'),
(4, 6, '09:00', '21:00'),
(4, 7, '09:00', '21:00');

-- Khách hàng mẫu (không có mật khẩu – khách chỉ đặt lịch, không đăng nhập)
INSERT INTO `clients` (`client_id`, `full_name`, `phone_number`, `email`) VALUES
(1, 'Nguyễn Thu Hà', '0981234501', 'khach@kittyhair.vn'),
(2, 'Đặng Thị Mai', '0981234502', 'dangmai@example.com'),
(3, 'Vũ Hồng Nhung', '0981234503', 'hongnhung@example.com'),
(4, 'Bùi Thanh Hằng', '0981234504', 'thanhhang@example.com'),
(5, 'Hoàng Diệu Linh', '0981234505', 'dieulinh@example.com'),
(6, 'Phan Quỳnh Chi', '0981234506', 'quynhchi@example.com'),
(7, 'Đỗ Bảo Ngọc', '0981234507', 'baongoc@example.com'),
(8, 'Ngô Thùy Dương', '0981234508', 'thuyduong@example.com'),
(9, 'Lý Kim Anh', '0981234509', 'kimanh@example.com'),
(10, 'Trịnh Mỹ Duyên', '0981234510', 'myduyen@example.com'),
(11, 'Đinh Hải Yến', '0981234511', 'haiyen@example.com'),
(12, 'Mai Phương Anh', '0981234512', 'phuonganh@example.com');

-- Lịch hẹn mẫu (tính theo ngày import để luôn nằm trong tương lai / quá khứ gần)
INSERT INTO `appointments` (`appointment_id`, `client_id`, `employee_id`, `start_time`, `end_time_expected`, `note`, `canceled`, `canceled_by`, `cancellation_reason`, `date_created`) VALUES
(1, 1, 4, TIMESTAMP(DATE_ADD(CURDATE(), INTERVAL 2 DAY), '10:00:00'), TIMESTAMP(DATE_ADD(CURDATE(), INTERVAL 2 DAY), '10:45:00'), 'Muốn cắt layer ngang vai.', 0, NULL, NULL, NOW()),
(2, 2, 2, TIMESTAMP(DATE_ADD(CURDATE(), INTERVAL 2 DAY), '14:00:00'), TIMESTAMP(DATE_ADD(CURDATE(), INTERVAL 2 DAY), '16:00:00'), NULL, 0, NULL, NULL, NOW()),
(3, 3, 3, TIMESTAMP(DATE_ADD(CURDATE(), INTERVAL 3 DAY), '10:00:00'), TIMESTAMP(DATE_ADD(CURDATE(), INTERVAL 3 DAY), '12:30:00'), 'Uốn lọn to, tóc đã từng nhuộm.', 0, NULL, NULL, NOW()),
(4, 4, 1, TIMESTAMP(DATE_ADD(CURDATE(), INTERVAL 3 DAY), '15:00:00'), TIMESTAMP(DATE_ADD(CURDATE(), INTERVAL 3 DAY), '18:00:00'), NULL, 0, NULL, NULL, NOW()),
(5, 5, 4, TIMESTAMP(DATE_ADD(CURDATE(), INTERVAL 4 DAY), '09:00:00'), TIMESTAMP(DATE_ADD(CURDATE(), INTERVAL 4 DAY), '09:30:00'), NULL, 0, NULL, NULL, NOW()),
(6, 6, 2, TIMESTAMP(DATE_ADD(CURDATE(), INTERVAL 4 DAY), '10:00:00'), TIMESTAMP(DATE_ADD(CURDATE(), INTERVAL 4 DAY), '13:00:00'), 'Nhuộm balayage tông nâu lạnh.', 0, NULL, NULL, NOW()),
(7, 7, 3, TIMESTAMP(DATE_ADD(CURDATE(), INTERVAL 5 DAY), '14:00:00'), TIMESTAMP(DATE_ADD(CURDATE(), INTERVAL 5 DAY), '16:30:00'), NULL, 0, NULL, NULL, NOW()),
(8, 8, 1, TIMESTAMP(DATE_ADD(CURDATE(), INTERVAL 5 DAY), '09:30:00'), TIMESTAMP(DATE_ADD(CURDATE(), INTERVAL 5 DAY), '10:30:00'), 'Làm tóc dự tiệc cưới bạn thân.', 0, NULL, NULL, NOW()),
(9, 9, 4, TIMESTAMP(DATE_ADD(CURDATE(), INTERVAL 6 DAY), '13:00:00'), TIMESTAMP(DATE_ADD(CURDATE(), INTERVAL 6 DAY), '14:15:00'), NULL, 0, NULL, NULL, NOW()),
(10, 10, 2, TIMESTAMP(DATE_ADD(CURDATE(), INTERVAL 7 DAY), '11:00:00'), TIMESTAMP(DATE_ADD(CURDATE(), INTERVAL 7 DAY), '12:30:00'), NULL, 0, NULL, NULL, NOW()),
(11, 11, 1, TIMESTAMP(DATE_ADD(CURDATE(), INTERVAL 8 DAY), '10:00:00'), TIMESTAMP(DATE_ADD(CURDATE(), INTERVAL 8 DAY), '11:00:00'), NULL, 0, NULL, NULL, NOW()),
(12, 12, 3, TIMESTAMP(DATE_ADD(CURDATE(), INTERVAL 9 DAY), '15:00:00'), TIMESTAMP(DATE_ADD(CURDATE(), INTERVAL 9 DAY), '17:30:00'), NULL, 0, NULL, NULL, NOW()),
(13, 1, 1, TIMESTAMP(DATE_ADD(CURDATE(), INTERVAL -10 DAY), '10:00:00'), TIMESTAMP(DATE_ADD(CURDATE(), INTERVAL -10 DAY), '10:45:00'), NULL, 0, NULL, NULL, DATE_ADD(NOW(), INTERVAL -12 DAY)),
(14, 1, 2, TIMESTAMP(DATE_ADD(CURDATE(), INTERVAL -30 DAY), '14:00:00'), TIMESTAMP(DATE_ADD(CURDATE(), INTERVAL -30 DAY), '16:00:00'), NULL, 0, NULL, NULL, DATE_ADD(NOW(), INTERVAL -32 DAY)),
(15, 2, 4, TIMESTAMP(DATE_ADD(CURDATE(), INTERVAL 10 DAY), '10:00:00'), TIMESTAMP(DATE_ADD(CURDATE(), INTERVAL 10 DAY), '10:30:00'), NULL, 1, 'client', 'Bận việc đột xuất, xin đổi lịch.', NOW());

INSERT INTO `services_booked` (`appointment_id`, `service_id`, `service_name`, `price`, `duration`) VALUES
(1, 1, 'Cắt tóc nữ', 200000, 45),
(2, 5, 'Nhuộm tóc thời trang', 650000, 120),
(3, 9, 'Uốn xoăn lọn to', 900000, 150),
(4, 6, 'Nhuộm Balayage / Highlight', 1500000, 180),
(5, 18, 'Gội sấy', 80000, 30),
(6, 6, 'Nhuộm Balayage / Highlight', 1500000, 180),
(7, 10, 'Uốn gợn sóng Hàn Quốc', 1100000, 150),
(8, 4, 'Làm tóc dự tiệc', 350000, 60),
(9, 2, 'Cắt tỉa tạo kiểu', 150000, 30),
(9, 3, 'Cắt tóc mái', 50000, 15),
(10, 8, 'Nhuộm phủ chân tóc', 400000, 75),
(10, 17, 'Gội đầu dưỡng sinh', 150000, 45),
(11, 13, 'Hấp phục hồi Collagen', 350000, 45),
(11, 19, 'Massage thư giãn vai gáy', 200000, 30),
(12, 11, 'Duỗi Keratin', 1200000, 150),
(13, 1, 'Cắt tóc nữ', 200000, 45),
(14, 5, 'Nhuộm tóc thời trang', 650000, 120),
(15, 2, 'Cắt tỉa tạo kiểu', 150000, 30);

INSERT INTO `activity_logs` (`actor_type`, `actor_id`, `actor_name`, `action`, `description`, `ip_address`) VALUES
('admin', 1, 'admin', 'system', 'Khởi tạo dữ liệu mẫu Kitty Hair.', '127.0.0.1');
