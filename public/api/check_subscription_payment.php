<?php
require_once __DIR__ . '/../../app/config.php';
require_once __DIR__ . '/../../app/db.php';

header('Content-Type: application/json');

if (!isset($_GET['order_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Missing order ID']);
    exit;
}

$orderId = trim($_GET['order_id']);

try {
    // Check subscription order status
    $orderStmt = $db->getPdo()->prepare("
        SELECT so.*, u.email, u.full_name 
        FROM subscription_orders so
        JOIN users u ON so.user_id = u.id
        WHERE so.order_id = ?
        LIMIT 1
    ");
    
    $orderStmt->execute([$orderId]);
    $order = $orderStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$order) {
        echo json_encode(['status' => 'error', 'message' => 'Order not found']);
        exit;
    }
    
    // Check if already completed
    if ($order['status'] === 'completed') {
        echo json_encode([
            'status' => 'completed',
            'message' => 'Subscription activated successfully',
            'plan' => $order['plan_name'],
            'credits' => $order['credits']
        ]);
        exit;
    }
    
    // Check for payment in bank_logs
    $bankLogStmt = $db->getPdo()->prepare("
        SELECT * FROM bank_logs 
        WHERE description LIKE ? 
        AND amount >= ?
        AND status IN ('processed', 'pending')
        ORDER BY created_at DESC 
        LIMIT 1
    ");
    
    $bankLogStmt->execute(['%' . $orderId . '%', $order['amount']]);
    $bankLog = $bankLogStmt->fetch(PDO::FETCH_ASSOC);
    
    if ($bankLog) {
        if ($bankLog['status'] === 'processed') {
            echo json_encode([
                'status' => 'completed',
                'message' => 'Payment received and processed',
                'transaction_id' => $bankLog['transaction_id']
            ]);
        } else {
            echo json_encode([
                'status' => 'processing',
                'message' => 'Payment received, processing...'
            ]);
        }
    } else {
        echo json_encode([
            'status' => $order['status'],
            'message' => 'Waiting for payment'
        ]);
    }
    
} catch (Exception $e) {
    error_log('Check subscription payment error: ' . $e->getMessage());
    echo json_encode([
        'status' => 'error',
        'message' => 'System error'
    ]);
}