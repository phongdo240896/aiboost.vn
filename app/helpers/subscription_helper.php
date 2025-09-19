<?php
/**
 * Helper functions for subscription display
 */

function getUserCurrentPlan($userId) {
    global $db;
    
    // Get active subscription
    $sql = "SELECT * FROM subscriptions 
            WHERE user_id = ? 
            AND status = 'active' 
            AND end_date > NOW()
            ORDER BY credits_total DESC, end_date DESC
            LIMIT 1";
    
    $result = $db->query($sql, [$userId]);
    
    if (!empty($result)) {
        return [
            'name' => $result[0]['plan_name'],
            'end_date' => $result[0]['end_date'],
            'credits_remaining' => $result[0]['credits_remaining'],
            'credits_total' => $result[0]['credits_total']
        ];
    }
    
    return [
        'name' => 'Free',
        'end_date' => null,
        'credits_remaining' => 0,
        'credits_total' => 0
    ];
}
?>