<?php
/**
 * Process topup API endpoint
 * Xử lý webhook từ ngân hàng hoặc cron job
 */

require_once __DIR__ . '/../app/config.php';
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/BankLog.php';

// Initialize BankLog
$bankLog = new BankLog($db);

// Set JSON header
header('Content-Type: application/json');

// Handle webhook từ API ngân hàng
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = file_get_contents('php://input');
    
    // Try to decode JSON, nếu không phải JSON thì parse form data
    $data = json_decode($input, true);
    if (!$data) {
        $data = $_POST;
    }
    
    // Log incoming data for debugging
    error_log('Incoming bank webhook: ' . print_r($data, true));
    
    try {
        // Prepare bank log data
        $logData = [
            'transaction_id' => $data['transaction_id'] ?? $data['id'] ?? uniqid('TXN_'),
            'bank_code' => $data['bank_code'] ?? $data['bankName'] ?? 'ACB',
            'account_number' => $data['account_number'] ?? $data['accountNumber'] ?? '',
            'amount' => $data['amount'] ?? 0,
            'description' => $data['description'] ?? $data['content'] ?? '',
            'transaction_date' => $data['transaction_date'] ?? $data['transactionDate'] ?? date('Y-m-d H:i:s'),
            'reference_number' => $data['reference_number'] ?? $data['referenceNumber'] ?? '',
            'balance_after' => $data['balance_after'] ?? $data['balance'] ?? null,
            'raw_data' => $data,
            'status' => 'pending'
        ];
        
        // Extract user from description
        $userId = null;
        $description = $logData['description'];
        
        // Pattern 1: PAY[4 digits][date] [amount]
        // Example: PAY39170915 2000
        if (preg_match('/PAY(\d{4})/i', $description, $matches)) {
            $payCodeDigits = $matches[1];
            
            // Find user with matching last 4 digits of ID
            $users = $db->select('users', ['id', 'email', 'full_name'], [], 'created_at DESC');
            foreach ($users as $user) {
                $userLast4 = substr($user['id'], -4);
                if ($userLast4 === $payCodeDigits) {
                    $userId = $user['id'];
                    error_log("Found user by pay code: " . $user['email']);
                    break;
                }
            }
        }
        
        // Set user_id if found
        if ($userId) {
            $logData['user_id'] = $userId;
        }
        
        // Save to bank_logs
        $logResult = $bankLog->logTransaction($logData);
        
        if ($logResult['success']) {
            error_log('Bank log saved successfully: ID=' . $logResult['log_id']);
            
            // Process if user found
            if ($userId) {
                // Check for pending transaction
                $pendingTx = $db->select('transactions', '*', [
                    'user_id' => $userId,
                    'status' => 'pending',
                    'type' => 'credit'
                ], 'created_at DESC', 1);
                
                if (!empty($pendingTx)) {
                    $tx = $pendingTx[0];
                    
                    // Update transaction status
                    $db->update('transactions', [
                        'status' => 'completed',
                        'reference_id' => $logData['transaction_id'],
                        'updated_at' => date('Y-m-d H:i:s')
                    ], ['id' => $tx['id']]);
                    
                    // Update user balance
                    $updateBalanceQuery = "UPDATE users SET balance = balance + ? WHERE id = ?";
                    $db->query($updateBalanceQuery, [$tx['amount'], $userId]);
                    
                    // Update bank log status
                    $bankLog->updateStatus($logData['transaction_id'], 'processed', $userId);
                    
                    error_log("✅ Transaction processed: User {$userId}, Amount: {$tx['amount']}");
                    
                    echo json_encode([
                        'success' => true,
                        'message' => 'Transaction processed successfully',
                        'user_id' => $userId,
                        'amount' => $tx['amount']
                    ]);
                } else {
                    // No pending transaction but still log it
                    error_log("⚠️ No pending transaction for user: {$userId}");
                    
                    // Create new transaction directly
                    $xuAmount = floor($logData['amount'] / 1000); // 1000 VND = 1 XU
                    
                    $newTxId = 'TOPUP_' . time() . '_' . rand(1000, 9999);
                    $db->insert('transactions', [
                        'id' => $newTxId,
                        'user_id' => $userId,
                        'type' => 'credit',
                        'amount' => $xuAmount,
                        'description' => "Nạp tiền qua {$logData['bank_code']} - " . number_format($logData['amount']) . " VND",
                        'status' => 'completed',
                        'reference_id' => $logData['transaction_id'],
                        'bank_code' => $logData['bank_code'],
                        'created_at' => date('Y-m-d H:i:s')
                    ]);
                    
                    // Update user balance
                    $updateBalanceQuery = "UPDATE users SET balance = balance + ? WHERE id = ?";
                    $db->query($updateBalanceQuery, [$xuAmount, $userId]);
                    
                    // Update bank log status
                    $bankLog->updateStatus($logData['transaction_id'], 'processed', $userId);
                    
                    echo json_encode([
                        'success' => true,
                        'message' => 'Transaction created and processed',
                        'user_id' => $userId,
                        'amount' => $xuAmount
                    ]);
                }
            } else {
                // User not found but still save log
                error_log("⚠️ User not found for transaction: " . $description);
                
                echo json_encode([
                    'success' => true,
                    'message' => 'Transaction logged but user not found',
                    'log_id' => $logResult['log_id']
                ]);
            }
        } else {
            if ($logResult['duplicate']) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Duplicate transaction',
                    'error' => 'DUPLICATE'
                ]);
            } else {
                echo json_encode([
                    'success' => false,
                    'message' => 'Failed to log transaction',
                    'error' => $logResult['message']
                ]);
            }
        }
        
    } catch (Exception $e) {
        error_log('Process topup error: ' . $e->getMessage());
        echo json_encode([
            'success' => false,
            'message' => 'System error: ' . $e->getMessage()
        ]);
    }
    
    exit;
}

// Handle GET request - kiểm tra status
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    echo json_encode([
        'success' => true,
        'message' => 'Topup processor is running',
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    exit;
}
?>