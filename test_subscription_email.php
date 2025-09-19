<?php
require_once 'vendor/autoload.php';
require_once 'app/config.php';
require_once 'app/db.php';
require_once 'app/EmailService.php';

use App\EmailService;

echo "<pre>"; // Để hiển thị đẹp trên browser
echo "==============================================\n";
echo "TEST SUBSCRIPTION EMAIL\n";
echo "==============================================\n\n";

try {
    // First, find the correct subscription
    $findSql = "
        SELECT 
            s.*,
            u.email,
            u.full_name,
            u.username
        FROM subscriptions s
        LEFT JOIN users u ON s.user_id = u.id
        WHERE u.email = 'doquocphong2408@gmail.com'
        AND s.status = 'active'
        ORDER BY s.created_at DESC
        LIMIT 1
    ";
    
    $result = $db->query($findSql);
    
    if (empty($result)) {
        echo "❌ No active subscription found for doquocphong2408@gmail.com\n\n";
        
        // Try to find any subscription
        $anySql = "
            SELECT 
                s.*,
                u.email
            FROM subscriptions s
            LEFT JOIN users u ON s.user_id = u.id
            WHERE u.email = 'doquocphong2408@gmail.com'
            ORDER BY s.created_at DESC
        ";
        
        $anyResult = $db->query($anySql);
        
        if (!empty($anyResult)) {
            echo "Found subscriptions but not active:\n";
            foreach ($anyResult as $sub) {
                echo "- ID: {$sub['id']}, Status: {$sub['status']}, End: {$sub['end_date']}\n";
            }
        } else {
            echo "No subscriptions at all for this email\n";
        }
        
        exit;
    }
    
    $subscription = $result[0];
    
    echo "✅ Found subscription:\n";
    echo "- ID: {$subscription['id']}\n";
    echo "- User ID: {$subscription['user_id']}\n";
    echo "- Email: {$subscription['email']}\n";
    echo "- Plan: {$subscription['plan_name']}\n";
    echo "- Status: {$subscription['status']}\n";
    echo "- End date: {$subscription['end_date']}\n";
    echo "- Credits: {$subscription['credits_remaining']}\n\n";
    
    // Calculate days left
    $daysLeft = (int)((strtotime($subscription['end_date']) - time()) / 86400);
    echo "- Days left: {$daysLeft}\n\n";
    
    // Prepare email content
    $userName = $subscription['full_name'] ?: $subscription['username'] ?: 'Khách hàng';
    $endDate = date('d/m/Y', strtotime($subscription['end_date']));
    $planName = $subscription['plan_name'] ?: 'Premium';
    
    // Adjust subject based on days left
    if ($daysLeft <= 0) {
        $subject = "❌ [AIboost.vn] Gói {$planName} đã hết hạn";
        $type = 'error';
    } elseif ($daysLeft == 1) {
        $subject = "🚨 [AIboost.vn] Gói {$planName} sắp hết hạn - Còn 1 ngày - KHẨN CẤP";
        $type = 'error';
    } elseif ($daysLeft <= 3) {
        $subject = "⚠️ [AIboost.vn] Gói {$planName} sắp hết hạn - Còn {$daysLeft} ngày";
        $type = 'warning';
    } else {
        $subject = "⏰ [AIboost.vn] Gói {$planName} sắp hết hạn - Còn {$daysLeft} ngày";
        $type = 'warning';
    }
    
    $content = "Xin chào {$userName}!\n\n";
    
    if ($daysLeft <= 0) {
        $content .= "Gói cước {$planName} của bạn đã hết hạn.\n\n";
        $content .= "⚠️ Tài khoản của bạn đã bị giới hạn.\n\n";
    } elseif ($daysLeft == 1) {
        $content .= "🚨 CHỈ CÒN 1 NGÀY! Gói cước {$planName} của bạn sẽ hết hạn vào ngày mai ({$endDate}).\n\n";
    } else {
        $content .= "Gói cước {$planName} của bạn sẽ hết hạn vào ngày {$endDate} (còn {$daysLeft} ngày).\n\n";
    }
    
    $content .= "⚡ Hãy gia hạn ngay để:\n";
    $content .= "• Tiếp tục sử dụng không giới hạn các công cụ AI\n";
    $content .= "• Giữ nguyên " . number_format($subscription['credits_remaining'], 0, ',', '.') . " credits còn lại\n";
    $content .= "• Không bị gián đoạn dịch vụ\n\n";
    $content .= "🎁 ƯU ĐÃI: Gia hạn trong hôm nay để nhận thêm 10% credits bonus!\n\n";
    $content .= "👉 Gia hạn ngay: https://aiboost.vn/pricing\n\n";
    $content .= "Nếu cần hỗ trợ, vui lòng liên hệ support@aiboost.vn";
    
    echo "📧 Sending email to: {$subscription['email']}\n";
    echo "📝 Subject: {$subject}\n\n";
    
    // Send email
    $emailService = EmailService::getInstance();
    
    // Check if configured
    if (!$emailService->isConfigured()) {
        echo "❌ Email service not configured\n";
        exit;
    }
    
    // Check if method exists
    if (!method_exists($emailService, 'sendSubscriptionEmail')) {
        echo "❌ Method sendSubscriptionEmail does not exist\n";
        echo "Available methods: " . implode(', ', get_class_methods($emailService)) . "\n";
        exit;
    }
    
    echo "Calling sendSubscriptionEmail()...\n";
    
    // Send the email
    $result = $emailService->sendSubscriptionEmail(
        $subscription['email'],
        $userName,
        $subject,
        $content,
        $type,
        $subscription
    );
    
    echo "\nResult: " . json_encode($result, JSON_PRETTY_PRINT) . "\n\n";
    
    if ($result['success']) {
        echo "✅ Email sent successfully!\n";
        echo "📬 Check your inbox at: {$subscription['email']}\n";
        
        // Log to database
        try {
            $db->query(
                "INSERT INTO email_logs (user_id, email, subject, status, sent_at) 
                 VALUES (?, ?, ?, 'sent', NOW())",
                [$subscription['user_id'], $subscription['email'], $subject]
            );
            echo "✅ Logged to database\n";
        } catch (Exception $e) {
            echo "⚠️ Failed to log: " . $e->getMessage() . "\n";
        }
    } else {
        echo "❌ Failed to send email: {$result['message']}\n";
    }
    
} catch (Exception $e) {
    echo "❌ EXCEPTION: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}

echo "\n==============================================\n";
echo "</pre>";
?>