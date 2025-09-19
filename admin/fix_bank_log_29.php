<?php
require_once __DIR__ . '/../app/config.php';
require_once __DIR__ . '/../app/db.php';

echo "<pre>";
echo "=== FIXING BANK_LOG ID 29 ===\n\n";

try {
    // Get order info
    $orderStmt = $db->getPdo()->prepare("
        SELECT so.*, u.id as user_id, u.email
        FROM subscription_orders so
        JOIN users u ON so.user_id = u.id
        WHERE so.order_id = 'SUB3917BBC7'
    ");
    $orderStmt->execute();
    $order = $orderStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$order) {
        die("Order not found!\n");
    }
    
    echo "Order found:\n";
    echo "- User ID: {$order['user_id']}\n";
    echo "- Email: {$order['email']}\n";
    echo "- Status: {$order['status']}\n\n";
    
    // Update bank_logs
    $updateStmt = $db->getPdo()->prepare("
        UPDATE bank_logs SET 
        user_id = ?,
        status = 'processed'
        WHERE id = 29
    ");
    
    $updateStmt->execute([$order['user_id']]);
    
    echo "✅ Updated bank_log ID 29:\n";
    echo "- Set user_id = {$order['user_id']}\n";
    echo "- Set status = 'processed'\n\n";
    
    // Verify update
    $checkStmt = $db->getPdo()->prepare("SELECT * FROM bank_logs WHERE id = 29");
    $checkStmt->execute();
    $bankLog = $checkStmt->fetch(PDO::FETCH_ASSOC);
    
    echo "Verification:\n";
    echo "- user_id: " . ($bankLog['user_id'] ?: 'NULL') . "\n";
    echo "- status: " . ($bankLog['status'] ?: 'NULL') . "\n";
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}

echo "</pre>";