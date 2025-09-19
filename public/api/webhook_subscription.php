<?php
/**
 * Webhook handler specifically for subscription payments
 * Separate from regular topup webhook
 */

require_once __DIR__ . '/../../app/config.php';
require_once __DIR__ . '/../../app/db.php';
require_once __DIR__ . '/../../app/WalletManager.php';

// Log webhook call
error_log("=== SUBSCRIPTION WEBHOOK START ===");
error_log("Method: " . $_SERVER['REQUEST_METHOD']);

// Initialize WalletManager
$walletManager = new WalletManager($db);

// Set JSON response header
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = file_get_contents('php://input');
    error_log("Raw input: " . substr($input, 0, 500));
    
    $data = json_decode($input, true);
    
    if (!$data) {
        echo json_encode(['success' => false, 'message' => 'Invalid JSON']);
        exit;
    }
    
    try {
        // Parse transaction data from webhook
        $transactionId = $data['transactionNumber'] ?? $data['id'] ?? uniqid('WH_');
        $amount = floatval($data['amount'] ?? 0);
        $description = $data['description'] ?? '';
        $bankCode = $data['bankName'] ?? $data['bank'] ?? 'UNKNOWN';
        $accountNumber = $data['accountNumber'] ?? '';
        $transactionDate = $data['activeDateTime'] ?? $data['time'] ?? date('Y-m-d H:i:s');
        
        error_log("Transaction: $transactionId, Amount: $amount, Description: $description");
        
        // Validate amount
        if ($amount <= 0) {
            throw new Exception('Invalid amount');
        }
        
        // Check if it's a subscription payment (starts with SUB)
        if (!preg_match('/SUB/i', $description)) {
            echo json_encode(['success' => false, 'message' => 'Not a subscription payment']);
            exit;
        }
        
        // Extract order ID from description
        preg_match('/SUB[A-Z0-9]+/i', $description, $matches);
        $orderId = $matches[0] ?? null;
        
        if (!$orderId) {
            throw new Exception('Could not extract order ID from description');
        }
        
        error_log("Found subscription order ID: $orderId");
        
        // Find the subscription order
        $orderStmt = $db->getPdo()->prepare("
            SELECT so.*, u.id as user_id, u.email, u.full_name
            FROM subscription_orders so
            JOIN users u ON so.user_id = u.id
            WHERE so.order_id = ?
            AND so.status = 'pending'
            AND so.amount <= ?
            LIMIT 1
        ");
        
        $orderStmt->execute([$orderId, $amount + 1000]); // Allow small variance
        $order = $orderStmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$order) {
            // Save to bank_logs for manual review
            $stmt = $db->getPdo()->prepare("
                INSERT INTO bank_logs 
                (transaction_id, bank_code, account_number, amount, description, 
                 transaction_date, raw_data, status, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, 'manual_review', NOW())
            ");
            
            $stmt->execute([
                $transactionId,
                $bankCode,
                $accountNumber,
                $amount,
                $description,
                $transactionDate,
                json_encode($data)
            ]);
            
            echo json_encode([
                'success' => true,
                'message' => 'Transaction saved for manual review',
                'order_not_found' => true
            ]);
            exit;
        }
        
        // Check for duplicate transaction
        $duplicateCheck = $db->query(
            "SELECT id FROM bank_logs WHERE transaction_id = ?",
            [$transactionId]
        );
        
        if (!empty($duplicateCheck)) {
            echo json_encode(['success' => false, 'message' => 'Duplicate transaction']);
            exit;
        }
        
        // Begin transaction processing
        $db->getPdo()->beginTransaction();
        
        try {
            // 1. Save to bank_logs
            $bankLogStmt = $db->getPdo()->prepare("
                INSERT INTO bank_logs 
                (transaction_id, user_id, bank_code, account_number, amount, 
                 description, transaction_date, raw_data, status, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'processing', NOW())
            ");
            
            $bankLogStmt->execute([
                $transactionId,
                $order['user_id'],
                $bankCode,
                $accountNumber,
                $amount,
                $description,
                $transactionDate,
                json_encode($data)
            ]);
            
            $bankLogId = $db->getPdo()->lastInsertId();
            
            // 2. Add credits to wallet
            $depositResult = $walletManager->deposit(
                $order['user_id'],
                $order['amount'],
                $transactionId,
                "Nâng cấp gói {$order['plan_name']} - {$order['credits']} XU"
            );
            
            if (!$depositResult['success']) {
                throw new Exception('Failed to add credits');
            }
            
            // 3. Create/Update subscription
            $endDate = date('Y-m-d H:i:s', strtotime("+{$order['duration']} days"));
            
            // Check for active subscription
            $activeSubStmt = $db->getPdo()->prepare("
                SELECT * FROM subscriptions 
                WHERE user_id = ? AND status = 'active' AND end_date > NOW()
                ORDER BY end_date DESC LIMIT 1
            ");
            
            $activeSubStmt->execute([$order['user_id']]);
            $activeSub = $activeSubStmt->fetch(PDO::FETCH_ASSOC);
            
            if ($activeSub) {
                // Extend subscription
                $newEndDate = date('Y-m-d H:i:s', 
                    strtotime($activeSub['end_date']) + ($order['duration'] * 86400)
                );
                
                $updateSubStmt = $db->getPdo()->prepare("
                    UPDATE subscriptions SET 
                    end_date = ?,
                    credits_total = credits_total + ?,
                    credits_remaining = credits_remaining + ?,
                    updated_at = NOW()
                    WHERE id = ?
                ");
                
                $updateSubStmt->execute([
                    $newEndDate,
                    $order['credits'],
                    $order['credits'],
                    $activeSub['id']
                ]);
            } else {
                // Create new subscription
                $createSubStmt = $db->getPdo()->prepare("
                    INSERT INTO subscriptions 
                    (user_id, plan_id, plan_name, start_date, end_date, 
                     credits_total, credits_remaining, status, transaction_id)
                    VALUES (?, ?, ?, NOW(), ?, ?, ?, 'active', ?)
                ");
                
                $createSubStmt->execute([
                    $order['user_id'],
                    $order['plan_id'],
                    $order['plan_name'],
                    $endDate,
                    $order['credits'],
                    $order['credits'],
                    $transactionId
                ]);
            }
            
            // 4. Update order status
            $updateOrderStmt = $db->getPdo()->prepare("
                UPDATE subscription_orders SET 
                status = 'completed',
                transaction_id = ?,
                processed_at = NOW()
                WHERE order_id = ?
            ");
            
            $updateOrderStmt->execute([$transactionId, $orderId]);
            
            // 5. Update bank_logs status
            $updateBankLogStmt = $db->getPdo()->prepare("
                UPDATE bank_logs SET status = 'processed', processed_at = NOW() WHERE id = ?
            ");
            
            $updateBankLogStmt->execute([$bankLogId]);
            
            // Commit all changes
            $db->getPdo()->commit();
            
            error_log("✅ Subscription activated successfully for user: {$order['email']}");
            
            echo json_encode([
                'success' => true,
                'message' => 'Subscription activated successfully',
                'order_id' => $orderId,
                'user_id' => $order['user_id'],
                'plan' => $order['plan_name'],
                'credits_added' => $order['credits']
            ]);
            
        } catch (Exception $e) {
            $db->getPdo()->rollBack();
            throw $e;
        }
        
    } catch (Exception $e) {
        error_log("❌ Webhook error: " . $e->getMessage());
        
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
    }
    
    error_log("=== SUBSCRIPTION WEBHOOK END ===");
    exit;
}

// GET request - webhook info
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    echo json_encode([
        'success' => true,
        'service' => 'Subscription Payment Webhook',
        'version' => '1.0',
        'endpoint' => url('api/webhook_subscription.php'),
        'timestamp' => date('Y-m-d H:i:s'),
        'status' => 'ready',
        'accepts' => 'SUB order payments only'
    ]);
    exit;
}

// Other methods not allowed
http_response_code(405);
echo json_encode(['success' => false, 'message' => 'Method not allowed']);