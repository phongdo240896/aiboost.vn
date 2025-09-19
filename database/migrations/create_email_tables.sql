-- Tạo bảng email_logs để tracking email đã gửi
CREATE TABLE IF NOT EXISTS `email_logs` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` VARCHAR(36) NOT NULL,
    `notification_id` INT DEFAULT NULL,
    `email` VARCHAR(255) NOT NULL,
    `subject` VARCHAR(255),
    `status` ENUM('sent', 'failed', 'bounced', 'pending') DEFAULT 'pending',
    `sent_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `opened_at` TIMESTAMP NULL,
    `clicked_at` TIMESTAMP NULL,
    `error_message` TEXT,
    `message_id` VARCHAR(255) DEFAULT NULL COMMENT 'Email message ID để tracking',
    `template_used` VARCHAR(100) DEFAULT NULL,
    INDEX `idx_user_id` (`user_id`),
    INDEX `idx_notification_id` (`notification_id`),
    INDEX `idx_sent_at` (`sent_at`),
    INDEX `idx_status` (`status`),
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`notification_id`) REFERENCES `notifications`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Thêm columns vào bảng notifications để track email (nếu chưa có)
ALTER TABLE `notifications` 
ADD COLUMN IF NOT EXISTS `email_sent_count` INT DEFAULT 0 AFTER `is_email`,
ADD COLUMN IF NOT EXISTS `email_sent_at` TIMESTAMP NULL AFTER `email_sent_count`,
ADD COLUMN IF NOT EXISTS `email_template` VARCHAR(100) DEFAULT NULL AFTER `email_sent_at`;

-- Thêm column cho users để cho phép tắt email notifications
ALTER TABLE `users` 
ADD COLUMN IF NOT EXISTS `email_notifications` BOOLEAN DEFAULT TRUE COMMENT 'Cho phép nhận email thông báo',
ADD COLUMN IF NOT EXISTS `email_verified_at` TIMESTAMP NULL AFTER `email_verified`,
ADD COLUMN IF NOT EXISTS `unsubscribe_token` VARCHAR(100) DEFAULT NULL COMMENT 'Token để unsubscribe email';

-- Tạo bảng email_queue cho việc gửi email hàng loạt
CREATE TABLE IF NOT EXISTS `email_queue` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` VARCHAR(36) NOT NULL,
    `notification_id` INT DEFAULT NULL,
    `to_email` VARCHAR(255) NOT NULL,
    `to_name` VARCHAR(255) DEFAULT NULL,
    `subject` VARCHAR(255) NOT NULL,
    `body` TEXT NOT NULL,
    `priority` INT DEFAULT 5 COMMENT '1-10, 1 là cao nhất',
    `attempts` INT DEFAULT 0,
    `max_attempts` INT DEFAULT 3,
    `status` ENUM('pending', 'processing', 'sent', 'failed') DEFAULT 'pending',
    `scheduled_at` TIMESTAMP NULL DEFAULT NULL,
    `sent_at` TIMESTAMP NULL DEFAULT NULL,
    `error_message` TEXT DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_status_priority` (`status`, `priority`),
    INDEX `idx_scheduled_at` (`scheduled_at`),
    INDEX `idx_user_id` (`user_id`),
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`notification_id`) REFERENCES `notifications`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tạo bảng email_templates để lưu mẫu email
CREATE TABLE IF NOT EXISTS `email_templates` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) UNIQUE NOT NULL,
    `subject` VARCHAR(255) NOT NULL,
    `body_html` TEXT NOT NULL,
    `body_text` TEXT DEFAULT NULL,
    `variables` JSON DEFAULT NULL COMMENT 'Danh sách biến có thể dùng',
    `category` VARCHAR(50) DEFAULT 'general',
    `is_active` BOOLEAN DEFAULT TRUE,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert mẫu email templates
INSERT INTO `email_templates` (`name`, `subject`, `body_html`, `category`) VALUES
('notification_default', '[AIboost.vn] {title}', 
'<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: Arial, sans-serif; }
        .container { max-width: 600px; margin: 0 auto; }
        .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 20px; text-align: center; }
        .content { padding: 20px; background: #f7f7f7; }
        .footer { text-align: center; padding: 20px; color: #666; font-size: 12px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>AIboost.vn</h1>
        </div>
        <div class="content">
            <h2>{title}</h2>
            <p>{content}</p>
            {action_button}
        </div>
        <div class="footer">
            <p>© 2025 AIboost.vn - Nền tảng AI hàng đầu Việt Nam</p>
            <p><a href="{unsubscribe_link}">Hủy đăng ký nhận email</a></p>
        </div>
    </div>
</body>
</html>', 'notification'),

('welcome', 'Chào mừng bạn đến với AIboost.vn!', 
'<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
</head>
<body>
    <div style="max-width: 600px; margin: 0 auto; font-family: Arial, sans-serif;">
        <div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0;">
            <h1>Chào mừng đến với AIboost.vn!</h1>
        </div>
        <div style="padding: 30px; background: white; border: 1px solid #ddd; border-top: none;">
            <p>Xin chào {full_name},</p>
            <p>Cảm ơn bạn đã đăng ký tài khoản tại AIboost.vn!</p>
            <p>Tài khoản của bạn đã được tặng <strong>500 XU miễn phí</strong> để trải nghiệm các dịch vụ AI của chúng tôi.</p>
            <div style="text-align: center; margin: 30px 0;">
                <a href="https://aiboost.vn/dashboard" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 15px 30px; text-decoration: none; border-radius: 5px; display: inline-block;">Bắt đầu ngay</a>
            </div>
            <p>Nếu bạn cần hỗ trợ, vui lòng liên hệ với chúng tôi.</p>
            <p>Trân trọng,<br>Đội ngũ AIboost.vn</p>
        </div>
    </div>
</body>
</html>', 'user'),

('payment_success', 'Thanh toán thành công - AIboost.vn',
'<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
</head>
<body>
    <div style="max-width: 600px; margin: 0 auto;">
        <h2>Thanh toán thành công!</h2>
        <p>Xin chào {full_name},</p>
        <p>Bạn đã nạp thành công <strong>{amount} VND</strong> vào tài khoản.</p>
        <p>Số dư hiện tại: <strong>{balance} XU</strong></p>
        <p>Mã giao dịch: {transaction_id}</p>
        <p>Cảm ơn bạn đã sử dụng dịch vụ!</p>
    </div>
</body>
</html>', 'transaction'),

('promotion', 'Khuyến mãi đặc biệt từ AIboost.vn',
'<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
</head>
<body>
    <div style="max-width: 600px; margin: 0 auto;">
        <div style="background: #ff6b6b; color: white; padding: 20px; text-align: center;">
            <h1>🎁 KHUYẾN MÃI ĐẶC BIỆT</h1>
        </div>
        <div style="padding: 20px;">
            <h2>{title}</h2>
            <p>{content}</p>
            <p>Thời hạn: {expires_at}</p>
            <div style="text-align: center;">
                <a href="{action_url}" style="background: #ff6b6b; color: white; padding: 15px 30px; text-decoration: none; border-radius: 5px;">Nhận ưu đãi ngay</a>
            </div>
        </div>
    </div>
</body>
</html>', 'promotion');

-- Tạo bảng email_settings để lưu cấu hình SMTP
CREATE TABLE IF NOT EXISTS `email_settings` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `key` VARCHAR(100) UNIQUE NOT NULL,
    `value` TEXT,
    `description` VARCHAR(255),
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert cấu hình email mặc định
INSERT INTO `email_settings` (`key`, `value`, `description`) VALUES
('smtp_host', 'smtp.gmail.com', 'SMTP server host'),
('smtp_port', '587', 'SMTP port'),
('smtp_encryption', 'tls', 'Encryption method (tls/ssl)'),
('smtp_username', '', 'SMTP username/email'),
('smtp_password', '', 'SMTP password (encrypted)'),
('from_email', 'noreply@aiboost.vn', 'Default from email'),
('from_name', 'AIboost.vn', 'Default from name'),
('daily_limit', '5000', 'Giới hạn email mỗi ngày'),
('hourly_limit', '500', 'Giới hạn email mỗi giờ'),
('debug_mode', '0', 'Debug mode (1=on, 0=off)'),
('test_email', '', 'Email nhận test');

-- Tạo bảng để tracking email bounce và complaints
CREATE TABLE IF NOT EXISTS `email_blacklist` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `email` VARCHAR(255) UNIQUE NOT NULL,
    `reason` ENUM('bounce', 'complaint', 'unsubscribe', 'manual') NOT NULL,
    `details` TEXT,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tạo bảng tracking email stats
CREATE TABLE IF NOT EXISTS `email_stats` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `date` DATE UNIQUE NOT NULL,
    `total_sent` INT DEFAULT 0,
    `total_opened` INT DEFAULT 0,
    `total_clicked` INT DEFAULT 0,
    `total_bounced` INT DEFAULT 0,
    `total_failed` INT DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_date` (`date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add indexes for better performance
ALTER TABLE `email_logs` 
ADD INDEX `idx_email` (`email`),
ADD INDEX `idx_message_id` (`message_id`);

ALTER TABLE `email_queue`
ADD INDEX `idx_attempts` (`attempts`),
ADD INDEX `idx_created_at` (`created_at`);

-- Create stored procedure to clean old email logs
DELIMITER $$
CREATE PROCEDURE IF NOT EXISTS `clean_old_email_logs`()
BEGIN
    -- Delete email logs older than 90 days
    DELETE FROM `email_logs` WHERE `sent_at` < DATE_SUB(NOW(), INTERVAL 90 DAY);
    
    -- Delete failed queue items older than 30 days
    DELETE FROM `email_queue` WHERE `status` = 'failed' AND `created_at` < DATE_SUB(NOW(), INTERVAL 30 DAY);
    
    -- Delete sent queue items older than 7 days
    DELETE FROM `email_queue` WHERE `status` = 'sent' AND `created_at` < DATE_SUB(NOW(), INTERVAL 7 DAY);
END$$
DELIMITER ;

-- Grant permissions (adjust as needed)
-- GRANT SELECT, INSERT, UPDATE, DELETE ON `email_logs` TO 'your_user'@'localhost';
-- GRANT SELECT, INSERT, UPDATE, DELETE ON `email_queue` TO 'your_user'@'localhost';
-- GRANT SELECT, INSERT, UPDATE, DELETE ON `email_templates` TO 'your_user'@'localhost';
-- GRANT SELECT, INSERT, UPDATE, DELETE ON `email_settings` TO 'your_user'@'localhost';
-- GRANT SELECT, INSERT, UPDATE, DELETE ON `email_blacklist` TO 'your_user'@'localhost';
-- GRANT SELECT, INSERT, UPDATE, DELETE ON `email_stats` TO 'your_user'@'localhost';