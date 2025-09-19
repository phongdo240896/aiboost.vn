<?php
require_once 'vendor/autoload.php';
require_once 'app/config.php';
require_once 'app/db.php';

echo "<pre>";
echo "==============================================\n";
echo "CHECK SUBSCRIPTIONS IN DATABASE\n";
echo "==============================================\n\n";

try {
    // 1. List all active subscriptions
    echo "1. ALL ACTIVE SUBSCRIPTIONS:\n";
    echo "-----------------------------\n";
    $sql = "
        SELECT 
            s.id,
            s.user_id,
            s.plan_name,
            s.status,
            DATE(s.end_date) as end_date,
            DATEDIFF(DATE(s.end_date), CURDATE()) as days_left,
            u.email,
            u.username
        FROM subscriptions s
        LEFT JOIN users u ON s.user_id = u.id
        WHERE s.status = 'active'
        ORDER BY s.end_date ASC
    ";
    
    $result = $db->query($sql);
    
    if (!empty($result)) {
        foreach ($result as $row) {
            echo "ID: {$row['id']}\n";
            echo "User ID: {$row['user_id']}\n";
            echo "Email: {$row['email']}\n";
            echo "Plan: {$row['plan_name']}\n";
            echo "End date: {$row['end_date']}\n";
            echo "Days left: {$row['days_left']}\n";
            echo "Status: {$row['status']}\n";
            echo "-----------------------------\n";
        }
    } else {
        echo "No active subscriptions found\n";
    }
    
    // 2. Check specific email
    echo "\n2. CHECK SUBSCRIPTION FOR doquocphong2408@gmail.com:\n";
    echo "-----------------------------\n";
    $sql2 = "
        SELECT 
            s.*,
            u.email,
            u.username
        FROM subscriptions s
        LEFT JOIN users u ON s.user_id = u.id
        WHERE u.email = 'doquocphong2408@gmail.com'
        ORDER BY s.created_at DESC
        LIMIT 1
    ";
    
    $result2 = $db->query($sql2);
    
    if (!empty($result2)) {
        $sub = $result2[0];
        echo "Found subscription:\n";
        echo "- ID: {$sub['id']}\n";
        echo "- User ID: {$sub['user_id']}\n";
        echo "- Email: {$sub['email']}\n";
        echo "- Plan: {$sub['plan_name']}\n";
        echo "- Status: {$sub['status']}\n";
        echo "- End date: {$sub['end_date']}\n";
        echo "- Credits: {$sub['credits_remaining']}\n";
    } else {
        echo "No subscription found for this email\n";
    }
    
    // 3. Check subscriptions expiring soon
    echo "\n3. SUBSCRIPTIONS EXPIRING IN NEXT 7 DAYS:\n";
    echo "-----------------------------\n";
    $sql3 = "
        SELECT 
            s.*,
            u.email,
            DATEDIFF(DATE(s.end_date), CURDATE()) as days_left
        FROM subscriptions s
        LEFT JOIN users u ON s.user_id = u.id
        WHERE s.status = 'active'
        AND DATEDIFF(DATE(s.end_date), CURDATE()) BETWEEN 0 AND 7
        ORDER BY days_left ASC
    ";
    
    $result3 = $db->query($sql3);
    
    if (!empty($result3)) {
        foreach ($result3 as $row) {
            echo "- {$row['email']}: {$row['plan_name']} expires in {$row['days_left']} days (User ID: {$row['user_id']})\n";
        }
    } else {
        echo "No subscriptions expiring soon\n";
    }
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}

echo "\n==============================================\n";
echo "</pre>";
?>