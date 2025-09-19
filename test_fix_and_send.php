<?php
require_once 'vendor/autoload.php';
require_once 'app/config.php';
require_once 'app/db.php';
require_once 'app/EmailService.php';

use App\EmailService;

echo "<pre>";
echo "==============================================\n";
echo "CREATE TEST USER AND SUBSCRIPTION\n";
echo "==============================================\n\n";

try {
    $testEmail = 'doquocphong2408@gmail.com';
    $subscriptionId = null;
    
    // 1. Check if user with this email exists
    echo "1. CHECKING USER:\n";
    echo "-----------------------------\n";
    
    $userSql = "SELECT * FROM users WHERE email = ?";
    $users = $db->query($userSql, [$testEmail]);
    
    if (empty($users)) {
        echo "❌ No user found with email: $testEmail\n";
        
        // Create a test user
        echo "\nCreating test user...\n";
        
        $newUserId = 'user_test_' . time();
        $insertUserSql = "
            INSERT INTO users (id, email, password, full_name, role, status, balance, created_at) 
            VALUES (?, ?, ?, ?, 'user', 'active', 500, NOW())
        ";
        
        $db->query($insertUserSql, [
            $newUserId,
            $testEmail,
            password_hash('password123', PASSWORD_DEFAULT),
            'Test User'
        ]);
        
        echo "✅ Created user with ID: $newUserId\n";
        $userId = $newUserId;
        
        // Create wallet
        $db->query("INSERT INTO wallets (user_id, balance) VALUES (?, 500)", [$newUserId]);
        echo "✅ Created wallet\n";
        
        // Create test subscription
        echo "\nCreating test subscription...\n";
        $endDate = date('Y-m-d H:i:s', strtotime('+5 days')); // Expires in 5 days
        
        $insertSubSql = "
            INSERT INTO subscriptions 
            (user_id, plan_id, plan_name, start_date, end_date, credits_total, credits_remaining, status)
            VALUES (?, 2, 'Pro', NOW(), ?, 6000, 6000, 'active')
        ";
        
        $db->query($insertSubSql, [$newUserId, $endDate]);
        echo "✅ Created subscription expiring in 5 days\n";
        
    } else {
        $user = $users[0];
        $userId = $user['id'];
        echo "✅ Found user:\n";
        echo "- ID: {$user['id']}\n";
        echo "- Email: {$user['email']}\n";
        echo "- Name: {$user['full_name']}\n\n";
        
        // Check subscriptions
        echo "2. CHECKING SUBSCRIPTIONS:\n";
        echo "-----------------------------\n";
        
        $subSql = "
            SELECT * FROM subscriptions 
            WHERE user_id = ? 
            ORDER BY created_at DESC
        ";
        
        $subs = $db->query($subSql, [$user['id']]);
        
        if (empty($subs)) {
            echo "No subscriptions found, creating one...\n";
            
            $endDate = date('Y-m-d H:i:s', strtotime('+3 days'));
            $insertSubSql = "
                INSERT INTO subscriptions 
                (user_id, plan_id, plan_name, start_date, end_date, credits_total, credits_remaining, status)
                VALUES (?, 2, 'Pro', NOW(), ?, 6000, 6000, 'active')
            ";
            
            $db->query($insertSubSql, [$user['id'], $endDate]);
            echo "✅ Created subscription expiring in 3 days\n";
        } else {
            foreach ($subs as $sub) {
                $daysLeft = ceil((strtotime($sub['end_date']) - time()) / 86400);
                echo "- Subscription ID: {$sub['id']}\n";
                echo "  Plan: {$sub['plan_name']}\n";
                echo "  Status: {$sub['status']}\n";
                echo "  End date: {$sub['end_date']}\n";
                echo "  Days left: {$daysLeft}\n";
                
                // Update subscription if it's expiring today or expired
                if ($daysLeft <= 0 && $sub['status'] == 'active') {
                    echo "  ⚠️ Subscription expiring/expired, updating end date...\n";
                    
                    // Update to expire in 3 days from now
                    $newEndDate = date('Y-m-d H:i:s', strtotime('+3 days'));
                    $updateSql = "UPDATE subscriptions SET end_date = ? WHERE id = ?";
                    $db->query($updateSql, [$newEndDate, $sub['id']]);
                    echo "  ✅ Updated end date to: $newEndDate\n";
                    $subscriptionId = $sub['id']; // Save the updated subscription ID
                }
                echo "-----------------------------\n";
            }
        }
    }
    
    // 3. Now test email sending
    echo "\n3. TESTING EMAIL:\n";
    echo "-----------------------------\n";
    
    // If we updated a subscription, fetch it directly
    if ($subscriptionId) {
        $testSql = "
            SELECT 
                s.*,
                u.email,
                u.full_name,
                u.username,
                DATEDIFF(DATE(s.end_date), CURDATE()) as days_left
            FROM subscriptions s
            LEFT JOIN users u ON s.user_id = u.id
            WHERE s.id = ?
        ";
        $result = $db->query($testSql, [$subscriptionId]);
    } else {
        // Otherwise, fetch by email
        $testSql = "
            SELECT 
                s.*,
                u.email,
                u.full_name,
                u.username,
                DATEDIFF(DATE(s.end_date), CURDATE()) as days_left
            FROM subscriptions s
            LEFT JOIN users u ON s.user_id = u.id
            WHERE u.id = ?
            AND s.status = 'active'
            ORDER BY s.end_date DESC
            LIMIT 1
        ";
        $result = $db->query($testSql, [$userId]);
    }
    
    if (!empty($result)) {
        $subscription = $result[0];
        
        echo "Found active subscription:\n";
        echo "- ID: {$subscription['id']}\n";
        echo "- Plan: {$subscription['plan_name']}\n";
        echo "- End date: {$subscription['end_date']}\n";
        echo "- Days left: {$subscription['days_left']}\n\n";
        
        // Send test email
        $emailService = EmailService::getInstance();
        
        if (!$emailService->isConfigured()) {
            echo "❌ Email service not configured\n";
            echo "Please check EMAIL_* settings in config.php\n";
        } else {
            $userName = $subscription['full_name'] ?: 'Khách hàng';
            $daysLeft = max($subscription['days_left'], 1); // Ensure at least 1 day
            $planName = $subscription['plan_name'];
            
            $subject = "⏰ [AIboost.vn] Gói {$planName} sắp hết hạn - Còn {$daysLeft} ngày";
            
            $content = "
<html>
<body style='font-family: Arial, sans-serif; line-height: 1.6; color: #333;'>
    <div style='max-width: 600px; margin: 0 auto; padding: 20px;'>
        <h2 style='color: #ff6b6b;'>⏰ Thông báo quan trọng về gói cước của bạn</h2>
        
        <p>Xin chào <strong>{$userName}</strong>!</p>
        
        <div style='background: #fff3cd; border-left: 4px solid #ffc107; padding: 15px; margin: 20px 0;'>
            <p style='margin: 0;'><strong>Gói {$planName}</strong> của bạn sẽ hết hạn sau <strong>{$daysLeft} ngày</strong></p>
            <p style='margin: 10px 0 0 0;'>Ngày hết hạn: <strong>{$subscription['end_date']}</strong></p>
        </div>
        
        <p>Để tiếp tục sử dụng dịch vụ không bị gián đoạn, vui lòng gia hạn gói cước.</p>
        
        <div style='text-align: center; margin: 30px 0;'>
            <a href='https://aiboost.vn/pricing' style='background: #007bff; color: white; padding: 12px 30px; text-decoration: none; border-radius: 5px; display: inline-block;'>
                👉 Gia hạn ngay
            </a>
        </div>
        
        <p>Cảm ơn bạn đã tin tưởng và sử dụng AIboost.vn!</p>
        
        <hr style='border: none; border-top: 1px solid #eee; margin: 30px 0;'>
        
        <p style='font-size: 12px; color: #666;'>
            AIboost.vn Team<br>
            📧 support@aiboost.vn
        </p>
    </div>
</body>
</html>
            ";
            
            echo "Sending email to: {$subscription['email']}\n";
            echo "Subject: $subject\n\n";
            
            $result = $emailService->sendSubscriptionEmail(
                $subscription['email'],
                $userName,
                $subject,
                $content,
                $daysLeft <= 3 ? 'warning' : 'info',
                $subscription
            );
            
            if ($result['success']) {
                echo "✅ EMAIL SENT SUCCESSFULLY!\n";
                echo "Check inbox for: {$subscription['email']}\n";
            } else {
                echo "❌ Failed to send email: {$result['message']}\n";
                if (isset($result['error'])) {
                    echo "Error details: {$result['error']}\n";
                }
            }
        }
    } else {
        echo "❌ No subscription found\n";
        echo "Please check the database manually\n";
    }
    
} catch (Exception $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
    echo "Trace:\n" . $e->getTraceAsString() . "\n";
}

echo "\n==============================================\n";
echo "</pre>";
?>