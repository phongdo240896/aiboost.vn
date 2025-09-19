<?php
require_once 'vendor/autoload.php';
require_once 'app/config.php';
require_once 'app/db.php';

use App\EmailService;

// Thay đổi email nhận test ở đây
$testEmail = 'doquocphong2408@gmail.com'; // <-- THAY ĐỔI EMAIL CỦA BẠN

echo "Sending test email to: $testEmail\n\n";

try {
    $emailService = EmailService::getInstance();
    
    $result = $emailService->sendTestEmail(
        $testEmail,
        'Chúc mừng! Hệ thống email AIboost.vn đã hoạt động thành công. 🎉'
    );
    
    if ($result['success']) {
        echo "✅ SUCCESS: " . $result['message'] . "\n";
        echo "\nVui lòng kiểm tra hộp thư (và cả thư mục Spam) của: $testEmail\n";
    } else {
        echo "❌ FAILED: " . $result['message'] . "\n";
        echo "\nGợi ý:\n";
        echo "1. Kiểm tra đã tạo App Password chưa\n";
        echo "2. Kiểm tra cấu hình SMTP trong admin panel\n";
        echo "3. Đảm bảo tài khoản Gmail cho phép 'Less secure apps'\n";
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}