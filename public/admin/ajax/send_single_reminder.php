<?php
session_start();
require_once '../../../app/config.php';
require_once '../../../app/db.php';
require_once '../../../app/auth.php';
require_once '../../../app/EmailService.php';

use App\EmailService;

// Set header JSON
header('Content-Type: application/json');

Auth::init($db);

if (!Auth::isAdmin()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$subscriptionId = $data['subscription_id'] ?? 0;

if (!$subscriptionId) {
    echo json_encode(['success' => false, 'message' => 'Invalid subscription ID']);
    exit;
}

try {
    // Get subscription details
    $subscription = $db->query(
        "SELECT s.*, u.email, u.full_name, u.username, s.plan_name
         FROM subscriptions s 
         LEFT JOIN users u ON s.user_id = u.id 
         WHERE s.id = ?",
        [$subscriptionId]
    );
    
    if (empty($subscription)) {
        throw new Exception('Subscription not found');
    }
    
    $sub = $subscription[0];
    $daysLeft = ceil((strtotime($sub['end_date']) - time()) / 86400);
    
    // Send reminder email
    $emailService = EmailService::getInstance();
    
    // Check if email service is configured
    if (!$emailService->isConfigured()) {
        echo json_encode([
            'success' => false,
            'message' => 'Email service chưa được cấu hình. Vui lòng cấu hình SMTP trước.'
        ]);
        exit;
    }
    
    $userName = $sub['full_name'] ?: $sub['username'] ?: 'Khách hàng';
    $subject = "⏰ [AIboost.vn] Gói {$sub['plan_name']} sắp hết hạn - Còn {$daysLeft} ngày";
    
    $content = "Xin chào {$userName}!\n\n";
    $content .= "Gói {$sub['plan_name']} của bạn sẽ hết hạn vào ngày " . date('d/m/Y', strtotime($sub['end_date'])) . " (còn {$daysLeft} ngày).\n\n";
    $content .= "Credits còn lại: " . number_format($sub['credits_remaining'], 0, ',', '.') . "\n\n";
    $content .= "Gia hạn ngay để tiếp tục sử dụng dịch vụ không bị gián đoạn.\n\n";
    $content .= "Truy cập: https://aiboost.vn/pricing";
    
    $result = $emailService->sendSubscriptionEmail(
        $sub['email'],
        $userName,
        $subject,
        $content,
        $daysLeft <= 0 ? 'error' : ($daysLeft <= 5 ? 'warning' : 'info'),
        $sub
    );
    
    echo json_encode([
        'success' => $result['success'],
        'message' => $result['success'] ? 'Đã gửi email nhắc nhở thành công!' : 'Lỗi: ' . $result['message']
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Lỗi: ' . $e->getMessage()
    ]);
}
?>