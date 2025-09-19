<?php
/**
 * Helper functions for user management
 */

function isUserFree($userId) {
    global $db;
    
    // Kiểm tra có subscription active không
    $sql = "SELECT id FROM subscriptions 
            WHERE user_id = ? 
            AND status = 'active' 
            AND end_date > NOW()";
    
    $result = $db->query($sql, [$userId]);
    
    return empty($result); // True nếu không có subscription
}

function getUserFreeCreditsInfo($userId) {
    global $db;
    
    if (!isUserFree($userId)) {
        return [
            'is_free' => false,
            'message' => 'User có gói cước'
        ];
    }
    
    // Lấy thông tin reset gần nhất
    $sql = "SELECT 
                DATE(created_at) as last_reset_date,
                balance_after as credits_received
            FROM wallet_transactions 
            WHERE user_id = ? 
            AND type = 'system_gift'
            AND description LIKE '%Tặng XU miễn phí hàng tháng%'
            ORDER BY created_at DESC 
            LIMIT 1";
    
    $lastReset = $db->query($sql, [$userId]);
    
    // Tính ngày reset tiếp theo
    $sql = "SELECT 
                created_at as register_date,
                DAY(created_at) as register_day
            FROM users 
            WHERE id = ?";
    
    $userInfo = $db->query($sql, [$userId]);
    
    if (empty($userInfo)) {
        return ['is_free' => false, 'message' => 'User không tồn tại'];
    }
    
    $registerDay = $userInfo[0]['register_day'];
    $currentDay = date('j');
    $currentMonth = date('n');
    $currentYear = date('Y');
    
    // Tính ngày reset tiếp theo
    if ($currentDay >= $registerDay) {
        // Reset của tháng sau
        $nextResetDate = date('Y-m-d', mktime(0, 0, 0, $currentMonth + 1, $registerDay, $currentYear));
    } else {
        // Reset của tháng này
        $nextResetDate = date('Y-m-d', mktime(0, 0, 0, $currentMonth, $registerDay, $currentYear));
    }
    
    return [
        'is_free' => true,
        'register_day' => $registerDay,
        'last_reset' => $lastReset ? $lastReset[0] : null,
        'next_reset_date' => $nextResetDate,
        'days_until_reset' => ceil((strtotime($nextResetDate) - time()) / 86400)
    ];
}

function getFreeUserCreditsRemaining($userId) {
    global $db;
    
    $sql = "SELECT balance FROM wallets WHERE user_id = ?";
    $result = $db->query($sql, [$userId]);
    
    return $result ? (int)$result[0]['balance'] : 0;
}
?>