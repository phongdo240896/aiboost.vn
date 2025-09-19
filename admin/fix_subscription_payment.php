<?php
require_once __DIR__ . '/../app/config.php';
require_once __DIR__ . '/../app/db.php';

// Transaction details
$bankLogId = 29;
$transactionId = '3143';
$amount = 200000;
$description = 'SUB3917BBC7 GD 833618-091625 11:34:27';

echo "<pre>";
echo "=== FIXING SUBSCRIPTION PAYMENT ===\n\n";

try {
    // Extract order ID
    if (!preg_match('/SUB([0-9A-Z]+)/i', $description, $matches)) {
        die("Could not extract SUB code from description\n");
    }
    
    $orderId = 'SUB' . strtoupper($matches[1]);
    echo "Order ID: $orderId\n";
    
    // Find subscription order
    $orderStmt = $db->getPdo()->prepare("
        SELECT so.*, u.id as user_id, u.email, u.full_name
        FROM subscription_orders so
        JOIN users u ON so.user_id = u.id
        WHERE so.order_id = ?
        LIMIT 1
    ");
    
    $orderStmt->execute([$orderId]);
    $order = $orderStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$order) {
        die("❌ Order not found: $orderId\n");
    }
    
    echo "✅ Found order:\n";
    echo "   User: {$order['email']} (ID: {$order['user_id']})\n";
    echo "   Plan: {$order['plan_name']}\n";
    echo "   Amount: " . number_format($order['amount']) . " VND\n";
    echo "   Credits: {$order['credits']} XU\n";
    echo "   Status: {$order['status']}\n\n";
    
    // Start database transaction
    $db->getPdo()->beginTransaction();
    
    try {
        // Only process if not already completed
        if ($order['status'] !== 'completed') {
            echo "Processing payment...\n";
            
            $xuToAdd = $order['credits'];
            $userId = $order['user_id'];
            $exchangeRate = 100; // 1 VND = 0.01 XU, so 100 VND = 1 XU
            
            // 1. Get current wallet balance
            $walletStmt = $db->getPdo()->prepare("SELECT * FROM wallets WHERE user_id = ?");
            $walletStmt->execute([$userId]);
            $wallet = $walletStmt->fetch(PDO::FETCH_ASSOC);
            
            $balanceBefore = 0;
            
            if (!$wallet) {
                // Create wallet if not exists
                $createWalletStmt = $db->getPdo()->prepare("
                    INSERT INTO wallets (user_id, balance, created_at) 
                    VALUES (?, ?, NOW())
                ");
                $createWalletStmt->execute([$userId, $xuToAdd]);
                $balanceAfter = $xuToAdd;
                echo "✅ Created wallet with {$xuToAdd} XU\n";
            } else {
                // Update existing wallet
                $balanceBefore = $wallet['balance'];
                $balanceAfter = $balanceBefore + $xuToAdd;
                
                $updateWalletStmt = $db->getPdo()->prepare("
                    UPDATE wallets SET 
                    balance = balance + ?,
                    updated_at = NOW()
                    WHERE user_id = ?
                ");
                $updateWalletStmt->execute([$xuToAdd, $userId]);
                echo "✅ Added {$xuToAdd} XU to existing wallet\n";
            }
            
            echo "   Balance: {$balanceBefore} → {$balanceAfter} XU\n";
            
            // 2. Generate unique transaction ID
            $walletTxId = 'WTX_' . time() . '_' . uniqid();
            
            // 3. Record transaction with correct column names
            $insertTxStmt = $db->getPdo()->prepare("
                INSERT INTO wallet_transactions 
                (transaction_id, user_id, type, amount_vnd, amount_xu, exchange_rate,
                 balance_before, balance_after, reference_id, description, status, created_at)
                VALUES (?, ?, 'deposit', ?, ?, ?, ?, ?, ?, ?, 'completed', NOW())
            ");
            
            $insertTxStmt->execute([
                $walletTxId,
                $userId,
                $amount,           // amount_vnd
                $xuToAdd,          // amount_xu
                $exchangeRate,     // exchange_rate (100)
                $balanceBefore,    // balance_before
                $balanceAfter,     // balance_after
                $transactionId,    // reference_id (bank transaction ID)
                "Nâng cấp gói {$order['plan_name']} - {$xuToAdd} XU", // description
            ]);
            
            echo "✅ Recorded transaction: {$walletTxId}\n";
            
            // 4. Create/Update subscription
            $endDate = date('Y-m-d H:i:s', strtotime("+{$order['duration']} days"));
            
            // Check if subscriptions table exists, if not create it
            $createSubTableStmt = $db->getPdo()->exec("
                CREATE TABLE IF NOT EXISTS `subscriptions` (
                    `id` INT AUTO_INCREMENT PRIMARY KEY,
                    `user_id` VARCHAR(36) NOT NULL,
                    `plan_id` INT,
                    `plan_name` VARCHAR(100),
                    `start_date` DATETIME NOT NULL,
                    `end_date` DATETIME NOT NULL,
                    `credits_total` INT DEFAULT 0,
                    `credits_remaining` INT DEFAULT 0,
                    `status` ENUM('active', 'expired', 'cancelled') DEFAULT 'active',
                    `transaction_id` VARCHAR(100),
                    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    INDEX idx_user_id (user_id),
                    INDEX idx_status (status),
                    INDEX idx_end_date (end_date)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");
            
            // Check for active subscription
            $activeSubStmt = $db->getPdo()->prepare("
                SELECT * FROM subscriptions 
                WHERE user_id = ? 
                AND status = 'active'
                AND end_date > NOW()
                ORDER BY end_date DESC 
                LIMIT 1
            ");
            
            $activeSubStmt->execute([$userId]);
            $activeSub = $activeSubStmt->fetch(PDO::FETCH_ASSOC);
            
            if ($activeSub) {
                // Extend existing subscription
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
                    $xuToAdd,
                    $xuToAdd,
                    $activeSub['id']
                ]);
                
                echo "✅ Extended subscription until: $newEndDate\n";
                
            } else {
                // Create new subscription
                $createSubStmt = $db->getPdo()->prepare("
                    INSERT INTO subscriptions 
                    (user_id, plan_id, plan_name, start_date, end_date, 
                     credits_total, credits_remaining, status, transaction_id)
                    VALUES (?, ?, ?, NOW(), ?, ?, ?, 'active', ?)
                ");
                
                $createSubStmt->execute([
                    $userId,
                    $order['plan_id'],
                    $order['plan_name'],
                    $endDate,
                    $xuToAdd,
                    $xuToAdd,
                    $transactionId
                ]);
                
                echo "✅ Created new subscription until: $endDate\n";
            }
            
            // 5. Update order status
            $updateOrderStmt = $db->getPdo()->prepare("
                UPDATE subscription_orders SET 
                status = 'completed',
                transaction_id = ?,
                processed_at = NOW()
                WHERE order_id = ?
            ");
            
            $updateOrderStmt->execute([$transactionId, $orderId]);
            echo "✅ Updated order status to completed\n";
            
        } else {
            echo "ℹ️ Order already completed\n";
        }
        
        // 6. Update bank_logs
        $updateBankLogStmt = $db->getPdo()->prepare("
            UPDATE bank_logs SET 
            user_id = ?,
            status = 'processed',
            processed_at = NOW()
            WHERE id = ?
        ");
        
        $updateBankLogStmt->execute([$order['user_id'], $bankLogId]);
        echo "✅ Updated bank_log status\n";
        
        // Commit all changes
        $db->getPdo()->commit();
        
        echo "\n🎉 SUCCESSFULLY FIXED SUBSCRIPTION PAYMENT!\n";
        echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
        echo "User: {$order['email']}\n";
        echo "Credits added: {$order['credits']} XU\n";
        echo "Subscription: {$order['duration']} days\n";
        echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
        
    } catch (Exception $e) {
        $db->getPdo()->rollBack();
        throw $e;
    }
    
} catch (Exception $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
    echo "Line: " . $e->getLine() . "\n";
    echo "File: " . $e->getFile() . "\n";
}

echo "</pre>";