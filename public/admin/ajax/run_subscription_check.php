<?php
// Start output buffering
ob_start();

session_start();

// Set header JSON
header('Content-Type: application/json; charset=utf-8');

// Disable error display
ini_set('display_errors', 0);
error_reporting(E_ALL);

try {
    // QUAN TRỌNG: Include composer autoload TRƯỚC
    $autoloadPath = __DIR__ . '/../../../vendor/autoload.php';
    if (!file_exists($autoloadPath)) {
        ob_clean();
        echo json_encode([
            'success' => false,
            'message' => 'Composer autoload không tồn tại. Vui lòng chạy: composer install'
        ]);
        exit;
    }
    
    require_once $autoloadPath;
    
    // Now include other files
    require_once __DIR__ . '/../../../app/config.php';
    require_once __DIR__ . '/../../../app/db.php';
    require_once __DIR__ . '/../../../app/auth.php';
    require_once __DIR__ . '/../../../app/EmailService.php';
    
    // Initialize Auth
    Auth::init($db);
    
    if (!Auth::isAdmin()) {
        ob_clean();
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        exit;
    }
    
    // Check EmailService class
    if (!class_exists('App\EmailService')) {
        ob_clean();
        echo json_encode([
            'success' => false,
            'message' => 'Class App\EmailService không tồn tại'
        ]);
        exit;
    }
    
    $emailService = \App\EmailService::getInstance();
    
    // Check if email service is configured
    if (!$emailService->isConfigured()) {
        ob_clean();
        echo json_encode([
            'success' => false,
            'message' => 'Email service chưa được cấu hình. Vui lòng cấu hình SMTP trong Email Settings.'
        ]);
        exit;
    }
    
    $output = [];
    $totalSent = 0;
    $totalFailed = 0;
    
    // 1. Check subscriptions expiring in 5 days
    try {
        $expiring5Days = $db->query("
            SELECT 
                s.*,
                u.email,
                u.full_name,
                u.username,
                s.plan_name
            FROM subscriptions s
            LEFT JOIN users u ON s.user_id = u.id
            WHERE s.status = 'active'
            AND DATE(s.end_date) = DATE_ADD(CURDATE(), INTERVAL 5 DAY)
        ");
        
        if ($expiring5Days === false) {
            $expiring5Days = [];
        }
    } catch (Exception $e) {
        $expiring5Days = [];
        $output[] = "Lỗi query expiring subscriptions: " . $e->getMessage();
    }
    
    if (!empty($expiring5Days) && is_array($expiring5Days)) {
        $output[] = "Tìm thấy " . count($expiring5Days) . " subscription sắp hết hạn trong 5 ngày";
        
        foreach ($expiring5Days as $subscription) {
            if (empty($subscription['email'])) {
                $output[] = "✗ Bỏ qua user ID {$subscription['user_id']} - không có email";
                continue;
            }
            
            $userName = $subscription['full_name'] ?: $subscription['username'] ?: 'Khách hàng';
            $endDate = date('d/m/Y', strtotime($subscription['end_date']));
            
            $subject = "⏰ [AIboost.vn] Gói {$subscription['plan_name']} sắp hết hạn - Còn 5 ngày";
            
            $content = "Xin chào {$userName}!\n\n";
            $content .= "Gói cước {$subscription['plan_name']} của bạn sẽ hết hạn vào ngày {$endDate} (còn 5 ngày).\n\n";
            $content .= "Credits còn lại: " . number_format($subscription['credits_remaining'] ?? 0, 0, ',', '.') . "\n\n";
            $content .= "Gia hạn ngay để tiếp tục sử dụng không bị gián đoạn.\n\n";
            $content .= "Truy cập: https://aiboost.vn/pricing";
            
            // Check if already sent today
            try {
                $alreadySent = $db->query(
                    "SELECT id FROM email_logs 
                    WHERE user_id = ? 
                    AND subject LIKE '%sắp hết hạn%' 
                    AND DATE(sent_at) = CURDATE() 
                    LIMIT 1",
                    [$subscription['user_id']]
                );
            } catch (Exception $e) {
                $alreadySent = [];
            }
            
            if (empty($alreadySent)) {
                try {
                    $result = $emailService->sendSubscriptionEmail(
                        $subscription['email'],
                        $userName,
                        $subject,
                        $content,
                        'warning',
                        $subscription
                    );
                    
                    if ($result['success']) {
                        $output[] = "✓ Đã gửi email đến {$subscription['email']}";
                        $totalSent++;
                    } else {
                        $output[] = "✗ Lỗi gửi đến {$subscription['email']}: {$result['message']}";
                        $totalFailed++;
                    }
                } catch (Exception $e) {
                    $output[] = "✗ Exception khi gửi đến {$subscription['email']}: " . $e->getMessage();
                    $totalFailed++;
                }
            } else {
                $output[] = "- Đã gửi trước đó cho {$subscription['email']}";
            }
        }
    } else {
        $output[] = "Không có subscription nào sắp hết hạn trong 5 ngày";
    }
    
    // 2. Check expired subscriptions today
    try {
        $expiredToday = $db->query("
            SELECT 
                s.*,
                u.email,
                u.full_name,
                u.username,
                s.plan_name
            FROM subscriptions s
            LEFT JOIN users u ON s.user_id = u.id
            WHERE s.status = 'active'
            AND DATE(s.end_date) <= CURDATE()
        ");
        
        if ($expiredToday === false) {
            $expiredToday = [];
        }
    } catch (Exception $e) {
        $expiredToday = [];
        $output[] = "Lỗi query expired subscriptions: " . $e->getMessage();
    }
    
    if (!empty($expiredToday) && is_array($expiredToday)) {
        $output[] = "\nTìm thấy " . count($expiredToday) . " subscription đã hết hạn";
        
        foreach ($expiredToday as $subscription) {
            if (empty($subscription['email'])) {
                $output[] = "✗ Bỏ qua user ID {$subscription['user_id']} - không có email";
                continue;
            }
            
            // Update status to expired
            try {
                $db->query(
                    "UPDATE subscriptions SET status = 'expired', updated_at = NOW() WHERE id = ?",
                    [$subscription['id']]
                );
            } catch (Exception $e) {
                $output[] = "Lỗi update status: " . $e->getMessage();
            }
            
            $userName = $subscription['full_name'] ?: $subscription['username'] ?: 'Khách hàng';
            
            $subject = "❌ [AIboost.vn] Gói {$subscription['plan_name']} đã hết hạn";
            
            $content = "Xin chào {$userName}!\n\n";
            $content .= "Gói cước {$subscription['plan_name']} của bạn đã hết hạn.\n\n";
            $content .= "Credits hiện tại: " . number_format($subscription['credits_remaining'] ?? 0, 0, ',', '.') . " (đã bị đóng băng)\n\n";
            $content .= "Gia hạn ngay để kích hoạt lại toàn bộ tính năng.\n\n";
            $content .= "Truy cập: https://aiboost.vn/pricing";
            
            // Check if already sent
            try {
                $alreadySent = $db->query(
                    "SELECT id FROM email_logs 
                    WHERE user_id = ? 
                    AND subject LIKE '%đã hết hạn%' 
                    AND DATE(sent_at) >= DATE_SUB(CURDATE(), INTERVAL 3 DAY)
                    LIMIT 1",
                    [$subscription['user_id']]
                );
            } catch (Exception $e) {
                $alreadySent = [];
            }
            
            if (empty($alreadySent)) {
                try {
                    $result = $emailService->sendSubscriptionEmail(
                        $subscription['email'],
                        $userName,
                        $subject,
                        $content,
                        'error',
                        $subscription
                    );
                    
                    if ($result['success']) {
                        $output[] = "✓ Đã gửi thông báo hết hạn đến {$subscription['email']}";
                        $totalSent++;
                    } else {
                        $output[] = "✗ Lỗi gửi đến {$subscription['email']}: {$result['message']}";
                        $totalFailed++;
                    }
                } catch (Exception $e) {
                    $output[] = "✗ Exception khi gửi đến {$subscription['email']}: " . $e->getMessage();
                    $totalFailed++;
                }
            } else {
                $output[] = "- Đã gửi thông báo hết hạn cho {$subscription['email']}";
            }
        }
    } else {
        $output[] = "Không có subscription nào hết hạn hôm nay";
    }
    
    // Build summary message
    $message = "Hoàn thành kiểm tra subscription.";
    if ($totalSent > 0) {
        $message .= " Đã gửi {$totalSent} email thành công.";
    }
    if ($totalFailed > 0) {
        $message .= " {$totalFailed} email thất bại.";
    }
    
    // Clear any buffered output
    ob_clean();
    
    // Send JSON response
    echo json_encode([
        'success' => true,
        'message' => $message,
        'output' => implode("\n", $output),
        'stats' => [
            'sent' => $totalSent,
            'failed' => $totalFailed
        ]
    ]);
    
} catch (Exception $e) {
    ob_clean();
    echo json_encode([
        'success' => false,
        'message' => 'Lỗi: ' . $e->getMessage()
    ]);
}

ob_end_flush();
?>