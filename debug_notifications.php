<?php
// debug_notifications.php
require_once __DIR__ . '/app/config.php';
require_once __DIR__ . '/app/db.php';

$pdo = $db->getPdo();

echo "<h2>🔍 DEBUG NOTIFICATIONS</h2>";

// 1. Count notifications table
$stmt1 = $pdo->query("SELECT COUNT(*) as count FROM notifications");
$notifCount = $stmt1->fetch()['count'];
echo "<p>📋 Tổng notifications: <strong>{$notifCount}</strong></p>";

// 2. List all notifications
$stmt2 = $pdo->query("SELECT id, title, target_type, created_at FROM notifications ORDER BY created_at DESC");
$notifs = $stmt2->fetchAll();
echo "<h3>Danh sách notifications:</h3>";
foreach ($notifs as $n) {
    echo "<div>ID: {$n['id']} - {$n['title']} - {$n['target_type']} - {$n['created_at']}</div>";
}

// 3. Count user_notifications
$stmt3 = $pdo->query("SELECT COUNT(*) as count FROM user_notifications");
$userNotifCount = $stmt3->fetch()['count'];
echo "<p>👥 Tổng user_notifications: <strong>{$userNotifCount}</strong></p>";

// 4. Group by notification_id
$stmt4 = $pdo->query("
    SELECT notification_id, COUNT(*) as recipient_count 
    FROM user_notifications 
    GROUP BY notification_id 
    ORDER BY notification_id DESC
");
$groups = $stmt4->fetchAll();
echo "<h3>User notifications theo notification_id:</h3>";
foreach ($groups as $g) {
    echo "<div>Notification ID: {$g['notification_id']} có {$g['recipient_count']} người nhận</div>";
}
?>