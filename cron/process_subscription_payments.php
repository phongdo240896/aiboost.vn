<?php
/**
 * Cron job: Xử lý thanh toán nâng cấp gói subscription
 * Version fixed - kiểm tra cột tồn tại trước khi update
 */

require_once __DIR__ . '/../app/config.php';
require_once __DIR__ . '/../app/db.php';

// Enable error reporting for testing
if (isset($_GET['test'])) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
    echo "<pre>";
}

// Log start
$startTime = microtime(true);
error_log("=== SUBSCRIPTION PAYMENT CRON START - " . date('Y-m-d H:i:s') . " ===");

// Counters
$processedCount = 0;
$errorCount = 0;
$matchedCount = 0;

// Exchange rate
$EXCHANGE_RATE = 100; // 100 VND = 1 XU

// Check if processed_at column exists
function hasProcessedAtColumn($db) {
    $stmt = $db->getPdo()->query("SHOW COLUMNS FROM bank_logs LIKE 'processed_at'");
    return $stmt->rowCount() > 0;
}

$hasProcessedAt = hasProcessedAtColumn($db);

// Helper function to add XU directly
function addXUToWallet($db, $userId, $xuAmount, $vndAmount, $transactionId, $description) {
    global $EXCHANGE_RATE;
    
    // Get wallet
    $walletStmt = $db->getPdo()->prepare("
        SELECT * FROM wallets WHERE user_id = ?
    ");
    $walletStmt->execute([$userId]);
    $wallet = $walletStmt->fetch(PDO::FETCH_ASSOC);
    
    $balanceBefore = 0;
    
    if (!$wallet) {
        // Create wallet
        $createStmt = $db->getPdo()->prepare("
            INSERT INTO wallets (user_id, balance, created_at) 
            VALUES (?, ?, NOW())
        ");
        $createStmt->execute([$userId, $xuAmount]);
        $balanceAfter = $xuAmount;
    } else {
        // Update wallet
        $balanceBefore = $wallet['balance'];
        $balanceAfter = $balanceBefore + $xuAmount;
        
        $updateStmt = $db->getPdo()->prepare("
            UPDATE wallets SET 
            balance = balance + ?,
            updated_at = NOW()
            WHERE user_id = ?
        ");
        $updateStmt->execute([$xuAmount, $userId]);
    }
    
    // Generate transaction ID
    $walletTxId = 'WTX_' . time() . '_' . uniqid();
    
    // Record transaction
    $txStmt = $db->getPdo()->prepare("
        INSERT INTO wallet_transactions 
        (transaction_id, user_id, type, amount_vnd, amount_xu, exchange_rate,
         balance_before, balance_after, reference_id, description, status, created_at)
        VALUES (?, ?, 'deposit', ?, ?, ?, ?, ?, ?, ?, 'completed', NOW())
    ");
    
    $txStmt->execute([
        $walletTxId,
        $userId,
        $vndAmount,
        $xuAmount,
        $EXCHANGE_RATE,
        $balanceBefore,
        $balanceAfter,
        $transactionId,
        $description
    ]);
    
    return [
        'success' => true,
        'balance_before' => $balanceBefore,
        'balance_after' => $balanceAfter,
        'transaction_id' => $walletTxId
    ];
}

try {
    // Create subscriptions table if not exists
    $db->getPdo()->exec("
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
            INDEX idx_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    
    // Step 1: Find unprocessed bank_logs
    $unprocessedBankLogsStmt = $db->getPdo()->prepare("
        SELECT * FROM bank_logs 
        WHERE (description LIKE '%SUB%' OR description REGEXP 'SUB[0-9A-Z]+')
        AND (user_id IS NULL OR status IS NULL OR status = 'pending' OR status = 'manual_review')
        AND amount > 0
        AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        ORDER BY created_at ASC
        LIMIT 50
    ");
    
    $unprocessedBankLogsStmt->execute();
    $unprocessedLogs = $unprocessedBankLogsStmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (!empty($unprocessedLogs)) {
        error_log("Found " . count($unprocessedLogs) . " unprocessed SUB transactions in bank_logs");
        if (isset($_GET['test'])) echo "Found " . count($unprocessedLogs) . " unprocessed SUB transactions\n\n";
        
        foreach ($unprocessedLogs as $bankLog) {
            try {
                // Extract SUB code
                if (preg_match('/SUB([0-9A-Z]+)/i', $bankLog['description'], $matches)) {
                    $orderId = 'SUB' . strtoupper($matches[1]);
                    
                    error_log("Processing bank_log ID {$bankLog['id']}: Order $orderId");
                    if (isset($_GET['test'])) {
                        echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
                        echo "Bank Log ID: {$bankLog['id']}\n";
                        echo "Transaction: {$bankLog['transaction_id']}\n";
                        echo "Amount: " . number_format($bankLog['amount']) . " VND\n";
                        echo "Description: {$bankLog['description']}\n";
                        echo "Order ID extracted: $orderId\n";
                    }
                    
                    // Find matching order
                    $orderStmt = $db->getPdo()->prepare("
                        SELECT so.*, u.id as user_id, u.email, u.full_name
                        FROM subscription_orders so
                        JOIN users u ON so.user_id = u.id
                        WHERE so.order_id = ?
                        AND so.amount <= ?
                        LIMIT 1
                    ");
                    
                    $orderStmt->execute([$orderId, $bankLog['amount'] + 1000]);
                    $order = $orderStmt->fetch(PDO::FETCH_ASSOC);
                    
                    if ($order) {
                        $matchedCount++;
                        
                        error_log("✅ Matched with order for user: {$order['email']}");
                        if (isset($_GET['test'])) {
                            echo "✅ MATCHED ORDER!\n";
                            echo "User: {$order['email']} (ID: {$order['user_id']})\n";
                            echo "Plan: {$order['plan_name']}\n";
                            echo "Credits to add: {$order['credits']} XU\n";
                        }
                        
                        // Begin transaction
                        $db->getPdo()->beginTransaction();
                        
                        try {
                            // Only process if not completed
                            if ($order['status'] !== 'completed') {
                                // 1. Add credits
                                $walletResult = addXUToWallet(
                                    $db,
                                    $order['user_id'],
                                    $order['credits'],
                                    $bankLog['amount'],
                                    $bankLog['transaction_id'],
                                    "Nâng cấp gói {$order['plan_name']} - {$order['credits']} XU"
                                );
                                
                                if (!$walletResult['success']) {
                                    throw new Exception('Failed to add credits to wallet');
                                }
                                
                                if (isset($_GET['test'])) {
                                    echo "💰 Added {$order['credits']} XU to wallet\n";
                                    echo "   Balance: {$walletResult['balance_before']} → {$walletResult['balance_after']} XU\n";
                                    echo "   Transaction ID: {$walletResult['transaction_id']}\n";
                                }
                                
                                // 2. Create/Update subscription
                                $endDate = date('Y-m-d H:i:s', strtotime("+{$order['duration']} days"));
                                
                                // Check for active subscription
                                $activeSubStmt = $db->getPdo()->prepare("
                                    SELECT * FROM subscriptions 
                                    WHERE user_id = ? 
                                    AND status = 'active'
                                    AND end_date > NOW()
                                    ORDER BY end_date DESC 
                                    LIMIT 1
                                ");
                                
                                $activeSubStmt->execute([$order['user_id']]);
                                $activeSub = $activeSubStmt->fetch(PDO::FETCH_ASSOC);
                                
                                if ($activeSub) {
                                    // Extend existing
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
                                    
                                    if (isset($_GET['test'])) echo "📅 Extended subscription until: $newEndDate\n";
                                    
                                } else {
                                    // Create new
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
                                        $bankLog['transaction_id']
                                    ]);
                                    
                                    if (isset($_GET['test'])) echo "📅 Created subscription until: $endDate\n";
                                }
                                
                                // 3. Update order status
                                $updateOrderStmt = $db->getPdo()->prepare("
                                    UPDATE subscription_orders SET 
                                    status = 'completed',
                                    transaction_id = ?,
                                    processed_at = NOW(),
                                    updated_at = NOW()
                                    WHERE order_id = ?
                                ");
                                
                                $updateOrderStmt->execute([$bankLog['transaction_id'], $orderId]);
                                
                                if (isset($_GET['test'])) echo "✅ Updated order status to completed\n";
                            } else {
                                if (isset($_GET['test'])) echo "ℹ️ Order already completed\n";
                            }
                            
                            // 4. Update bank_logs - build dynamic query based on columns
                            if ($hasProcessedAt) {
                                $updateBankLogStmt = $db->getPdo()->prepare("
                                    UPDATE bank_logs SET 
                                    user_id = ?,
                                    status = 'processed',
                                    processed_at = NOW()
                                    WHERE id = ?
                                ");
                            } else {
                                $updateBankLogStmt = $db->getPdo()->prepare("
                                    UPDATE bank_logs SET 
                                    user_id = ?,
                                    status = 'processed'
                                    WHERE id = ?
                                ");
                            }
                            
                            $updateBankLogStmt->execute([$order['user_id'], $bankLog['id']]);
                            
                            if (isset($_GET['test'])) echo "✅ Updated bank_log (ID: {$bankLog['id']}) with user_id and status\n";
                            
                            // Commit
                            $db->getPdo()->commit();
                            
                            $processedCount++;
                            
                            error_log("✅ Successfully processed subscription payment for order: $orderId");
                            if (isset($_GET['test'])) {
                                echo "✅ SUBSCRIPTION ACTIVATED SUCCESSFULLY!\n";
                                echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
                            }
                            
                        } catch (Exception $e) {
                            $db->getPdo()->rollBack();
                            throw $e;
                        }
                        
                    } else {
                        // Mark for manual review
                        $updateBankLogStmt = $db->getPdo()->prepare("
                            UPDATE bank_logs SET 
                            status = 'manual_review'
                            WHERE id = ?
                        ");
                        
                        $updateBankLogStmt->execute([$bankLog['id']]);
                        
                        error_log("⚠️ No matching order found for $orderId");
                        if (isset($_GET['test'])) {
                            echo "⚠️ No matching order found - marked for manual review\n";
                            echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
                        }
                    }
                    
                } else {
                    error_log("Could not extract SUB code from: {$bankLog['description']}");
                }
                
            } catch (Exception $e) {
                $errorCount++;
                error_log("❌ Error processing bank_log ID {$bankLog['id']}: " . $e->getMessage());
                if (isset($_GET['test'])) {
                    echo "❌ ERROR: " . $e->getMessage() . "\n";
                    echo "Line: " . $e->getLine() . "\n";
                    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
                }
            }
        }
    } else {
        if (isset($_GET['test'])) echo "No unprocessed SUB transactions found\n";
    }
    
    // Check pending orders
    $pendingOrdersStmt = $db->getPdo()->prepare("
        SELECT so.*, u.id as user_id, u.email
        FROM subscription_orders so
        JOIN users u ON so.user_id = u.id
        WHERE so.status = 'pending'
        AND so.created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
        LIMIT 20
    ");
    
    $pendingOrdersStmt->execute();
    $pendingOrders = $pendingOrdersStmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (!empty($pendingOrders)) {
        if (isset($_GET['test'])) echo "\nChecking " . count($pendingOrders) . " pending orders for payments...\n";
        
        foreach ($pendingOrders as $order) {
            $bankLogStmt = $db->getPdo()->prepare("
                SELECT * FROM bank_logs 
                WHERE description LIKE ?
                AND amount >= ?
                AND (status IS NULL OR status != 'processed')
                AND created_at >= ?
                LIMIT 1
            ");
            
            $bankLogStmt->execute([
                '%' . $order['order_id'] . '%',
                $order['amount'] - 1000,
                $order['created_at']
            ]);
            
            $bankLog = $bankLogStmt->fetch(PDO::FETCH_ASSOC);
            
            if ($bankLog) {
                if (isset($_GET['test'])) {
                    echo "Found unprocessed payment for order {$order['order_id']}\n";
                    echo "Bank Log ID: {$bankLog['id']}, Amount: " . number_format($bankLog['amount']) . " VND\n";
                }
            }
        }
    }
    
    // Clean up old orders
    $cleanupStmt = $db->getPdo()->prepare("
        UPDATE subscription_orders 
        SET status = 'cancelled'
        WHERE status = 'pending'
        AND created_at < DATE_SUB(NOW(), INTERVAL 7 DAY)
    ");
    $cleanupStmt->execute();
    $cleanedUp = $cleanupStmt->rowCount();
    
    if ($cleanedUp > 0) {
        error_log("Cancelled $cleanedUp old pending orders");
        if (isset($_GET['test'])) echo "\nCleaned up $cleanedUp old orders\n";
    }
    
} catch (Exception $e) {
    error_log("❌ FATAL CRON ERROR: " . $e->getMessage());
    if (isset($_GET['test'])) {
        echo "FATAL ERROR: " . $e->getMessage() . "\n";
        echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    }
}

// Summary
$duration = round(microtime(true) - $startTime, 2);
$summary = "=== SUBSCRIPTION PAYMENT CRON COMPLETE ===\n";
$summary .= "Time: " . date('Y-m-d H:i:s') . " (Duration: {$duration}s)\n";
$summary .= "Matched: {$matchedCount} orders\n";
$summary .= "Processed: {$processedCount} orders\n";
$summary .= "Errors: {$errorCount}\n";

error_log($summary);
if (isset($_GET['test'])) {
    echo "\n$summary";
    echo "</pre>";
} else {
    echo "OK - Processed: $processedCount";
}