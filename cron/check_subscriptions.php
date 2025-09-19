<?php
/**
 * Subscription Reminder Cron Job - Final Working Version
 * Run daily at 9:00 AM
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Set content type for browser viewing
header('Content-Type: text/plain; charset=utf-8');

echo "==============================================\n";
echo "SUBSCRIPTION REMINDER CRON JOB\n";
echo "==============================================\n\n";

// Load required files
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../app/config.php';
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/EmailService.php';

echo "[" . date('Y-m-d H:i:s') . "] Starting...\n";
echo "Current date: " . date('Y-m-d') . "\n";

// Check test mode
$testMode = isset($_GET['test']) || (isset($argv[1]) && $argv[1] === '--test');
echo "Test mode: " . ($testMode ? "ON" : "OFF") . "\n\n";

// Check database
if (!isset($db)) {
    die("❌ Database not initialized\n");
}
echo "✅ Database connected\n";

// Check email service
$emailService = \App\EmailService::getInstance();
$emailConfigured = $emailService && $emailService->isConfigured();
if ($emailConfigured) {
    echo "✅ Email service configured\n\n";
} else {
    echo "⚠️ Email service not configured (will skip sending)\n\n";
}

// Function to send reminder email
function sendReminderEmail($db, $emailService, $sub, $daysLeft, $testMode) {
    // Validate email
    if (!filter_var($sub['email'], FILTER_VALIDATE_EMAIL)) {
        echo "      ❌ Invalid email address\n";
        return false;
    }
    
    $userName = $sub['full_name'] ?: 'Quý khách';
    $planName = $sub['plan_name'];
    $credits = $sub['credits_remaining'] ?? 0;
    $endDate = date('d/m/Y', strtotime($sub['end_date']));
    
    // Set subject and alert type based on days left
    if ($daysLeft <= 0) {
        $subject = "[AIboost.vn] Gói {$planName} đã hết hạn";
        $alertType = 'error';
        $statusText = "đã hết hạn vào ngày {$endDate}";
    } elseif ($daysLeft == 1) {
        $subject = "[AIboost.vn] Gói {$planName} hết hạn NGÀY MAI - KHẨN CẤP";
        $alertType = 'error';
        $statusText = "sẽ hết hạn NGÀY MAI ({$endDate})";
    } elseif ($daysLeft == 3) {
        $subject = "[AIboost.vn] Gói {$planName} sắp hết hạn - Còn 3 ngày";
        $alertType = 'warning';
        $statusText = "sẽ hết hạn sau 3 ngày ({$endDate})";
    } else {
        $subject = "[AIboost.vn] Gói {$planName} sắp hết hạn - Còn {$daysLeft} ngày";
        $alertType = 'info';
        $statusText = "sẽ hết hạn sau {$daysLeft} ngày ({$endDate})";
    }
    
    // Build email content
    $content = "Xin chào {$userName}!\n\n";
    $content .= "Gói {$planName} của bạn {$statusText}.\n\n";
    
    if ($daysLeft <= 0) {
        $content .= "⚠️ Tài khoản đã bị giới hạn:\n";
        $content .= "• Không thể sử dụng các công cụ AI\n";
        $content .= "• " . number_format($credits) . " credits đã bị tạm khóa\n";
        $content .= "• Không thể tạo nội dung mới\n\n";
        $content .= "👉 Gia hạn ngay để khôi phục toàn bộ tính năng!\n\n";
    } else {
        $content .= "📊 Thông tin tài khoản:\n";
        $content .= "• Credits hiện tại: " . number_format($credits) . "\n";
        $content .= "• Ngày hết hạn: {$endDate}\n\n";
        $content .= "💡 Gia hạn ngay để:\n";
        $content .= "• Tiếp tục sử dụng không giới hạn\n";
        $content .= "• Giữ nguyên toàn bộ credits\n";
        $content .= "• Không bị gián đoạn dịch vụ\n\n";
    }
    
    $content .= "🔗 Gia hạn tại: https://aiboost.vn/pricing\n\n";
    $content .= "Cảm ơn bạn đã sử dụng AIboost.vn!\n\n";
    $content .= "---\n";
    $content .= "AIboost.vn Team\n";
    $content .= "📧 support@aiboost.vn\n";
    
    echo "      📧 Sending to: {$sub['email']}\n";
    echo "      Subject: {$subject}\n";
    
    if (!$emailService) {
        echo "      ⏭️ Email service not available\n";
        return false;
    }
    
    try {
        $result = $emailService->sendSubscriptionEmail(
            $sub['email'],
            $userName,
            $subject,
            $content,
            $alertType,
            $sub
        );
        
        if ($result['success']) {
            echo "      ✅ Email sent successfully!\n";
            
            // REMOVED LOGGING HERE - EmailService already logs it
            // The sendSubscriptionEmail method in EmailService.php already inserts into email_logs
            // So we don't need to log again here to avoid duplicates
            
            return true;
        } else {
            echo "      ❌ Failed: " . ($result['message'] ?? 'Unknown error') . "\n";
            return false;
        }
    } catch (Exception $e) {
        echo "      ❌ Exception: " . $e->getMessage() . "\n";
        return false;
    }
}

// Array of days to check
$checkDays = [
    5 => 'info',
    3 => 'warning', 
    1 => 'urgent',
    0 => 'expired'
];

$totalSent = 0;
$totalFound = 0;

foreach ($checkDays as $days => $type) {
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    
    if ($days == 0) {
        echo "Checking EXPIRED subscriptions...\n";
        
        // Query for expired subscriptions
        $sql = "
            SELECT 
                s.*,
                u.email,
                u.full_name,
                DATEDIFF(CURDATE(), DATE(s.end_date)) as days_expired
            FROM subscriptions s
            INNER JOIN users u ON s.user_id = u.id
            WHERE s.status = 'active'
            AND DATE(s.end_date) <= CURDATE()
            AND u.email IS NOT NULL
            AND u.email != ''
        ";
        
        try {
            $result = $db->query($sql);
        } catch (Exception $e) {
            echo "❌ Query error: " . $e->getMessage() . "\n\n";
            continue;
        }
        
    } else {
        echo "Checking subscriptions expiring in {$days} day(s)...\n";
        
        // Calculate target date
        $targetDate = date('Y-m-d', strtotime("+{$days} days"));
        echo "Target date: {$targetDate}\n";
        
        // Query for subscriptions expiring on specific date
        $sql = "
            SELECT 
                s.*,
                u.email,
                u.full_name,
                {$days} as days_left
            FROM subscriptions s
            INNER JOIN users u ON s.user_id = u.id
            WHERE s.status = 'active'
            AND DATE(s.end_date) = ?
            AND u.email IS NOT NULL
            AND u.email != ''
        ";
        
        try {
            $result = $db->query($sql, [$targetDate]);
        } catch (Exception $e) {
            echo "❌ Query error: " . $e->getMessage() . "\n\n";
            continue;
        }
    }
    
    if ($result === false) {
        echo "❌ Query failed\n\n";
        continue;
    }
    
    if (empty($result)) {
        echo "ℹ️ No subscriptions found\n\n";
        continue;
    }
    
    $count = count($result);
    $totalFound += $count;
    echo "📊 Found {$count} subscription(s)\n\n";
    
    foreach ($result as $i => $sub) {
        $num = $i + 1;
        $daysLeft = $days == 0 ? (0 - ($sub['days_expired'] ?? 0)) : $days;
        
        echo "  [{$num}] {$sub['email']} - {$sub['plan_name']}\n";
        echo "      User ID: {$sub['user_id']}\n";
        echo "      Name: " . ($sub['full_name'] ?: 'N/A') . "\n";
        echo "      End date: {$sub['end_date']}\n";
        
        if ($days == 0 && isset($sub['days_expired'])) {
            echo "      Status: Expired {$sub['days_expired']} day(s) ago\n";
        } else {
            echo "      Days left: {$daysLeft}\n";
        }
        
        // Check if already sent today (skip in test mode)
        if (!$testMode) {
            // Build a unique identifier for today's reminder type
            $reminderType = "subscription_reminder_{$days}days";
            
            $checkSql = "
                SELECT id FROM email_logs 
                WHERE user_id = ? 
                AND DATE(sent_at) = CURDATE()
                AND status = 'sent'
                AND subject LIKE ?
                LIMIT 1
            ";
            
            // Check with subject pattern to avoid duplicate same type of reminder
            $subjectPattern = "%Gói%";
            if ($days == 0) {
                $subjectPattern = "%đã hết hạn%";
            } elseif ($days == 1) {
                $subjectPattern = "%NGÀY MAI%";
            } elseif ($days == 3) {
                $subjectPattern = "%Còn 3 ngày%";
            } elseif ($days == 5) {
                $subjectPattern = "%Còn 5 ngày%";
            }
            
            try {
                $existing = $db->query($checkSql, [$sub['user_id'], $subjectPattern]);
                if (!empty($existing)) {
                    echo "      ⏭️ Already sent today\n\n";
                    continue;
                }
            } catch (Exception $e) {
                // Continue anyway if can't check logs
            }
        }
        
        // Send email
        if ($emailConfigured) {
            if (sendReminderEmail($db, $emailService, $sub, $daysLeft, $testMode)) {
                $totalSent++;
            }
        } else {
            echo "      ⏭️ Email not configured\n";
        }
        echo "\n";
    }
}

// Update expired subscriptions
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "Updating expired subscriptions status...\n";

try {
    $updateSql = "
        UPDATE subscriptions 
        SET status = 'expired'
        WHERE status = 'active' 
        AND DATE(end_date) < CURDATE()
    ";
    $db->query($updateSql);
    echo "✅ Status update completed\n";
} catch (Exception $e) {
    echo "⚠️ Could not update status: " . $e->getMessage() . "\n";
}

// Summary
echo "\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "SUMMARY\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "Total subscriptions found: {$totalFound}\n";
echo "Total emails sent: {$totalSent}\n";
echo "Completed at: " . date('Y-m-d H:i:s') . "\n";

echo "\n==============================================\n";
echo "CRON JOB COMPLETED\n";
echo "==============================================\n";
?>