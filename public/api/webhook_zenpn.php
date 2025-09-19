<?php
/**
 * Webhook handler for api.zenpn.com
 * Version using tokens from bank_settings
 */

require_once __DIR__ . '/../../app/config.php';
require_once __DIR__ . '/../../app/db.php';
require_once __DIR__ . '/../../app/BankLog.php';
require_once __DIR__ . '/../../app/WalletManager.php';

// Log webhook
error_log("=== ZENPN WEBHOOK START ===");
error_log("Method: " . $_SERVER['REQUEST_METHOD']);

// Get headers
$headers = getallheaders();
error_log("Headers: " . json_encode($headers));

// Initialize managers
$bankLog = new BankLog($db);
$walletManager = new WalletManager($db);

// Set JSON header
header('Content-Type: application/json');

// Verify webhook authenticity (optional)
$webhookToken = $_SERVER['HTTP_X_WEBHOOK_TOKEN'] ?? $headers['X-Webhook-Token'] ?? '';
$webhookSignature = $_SERVER['HTTP_X_SIGNATURE'] ?? $headers['X-Signature'] ?? '';

// Get valid API tokens from bank_settings for verification
$validTokens = [];
$bankTokens = $db->query("SELECT api_token, bank_code FROM bank_settings WHERE status = 'active' AND api_token IS NOT NULL");
foreach ($bankTokens as $bank) {
    $validTokens[$bank['bank_code']] = $bank['api_token'];
}

error_log("Active banks with tokens: " . implode(', ', array_keys($validTokens)));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = file_get_contents('php://input');
    error_log("Raw input: " . substr($input, 0, 1000));
    
    $data = json_decode($input, true);
    if (!$data) {
        echo json_encode(['success' => false, 'message' => 'Invalid JSON']);
        exit;
    }
    
    try {
        // Identify bank from data
        $bankCode = $data['bankName'] ?? $data['bank'] ?? $data['bank_code'] ?? 'UNKNOWN';
        
        // Parse Zenpn transaction data
        $transactionData = [
            'transaction_id' => $data['transactionNumber'] ?? $data['id'] ?? uniqid('WEBHOOK_'),
            'amount' => floatval($data['amount'] ?? 0),
            'description' => $data['description'] ?? $data['content'] ?? '',
            'bank_code' => $bankCode,
            'account_number' => $data['accountNumber'] ?? $data['accountName'] ?? '',
            'transaction_date' => $data['time'] ?? $data['transactionDate'] ?? date('Y-m-d H:i:s'),
            'reference_number' => $data['referenceNumber'] ?? '',
            'receiver_name' => $data['receiverName'] ?? '',
            'status' => 'success'
        ];
        
        error_log("Transaction parsed: " . json_encode($transactionData));
        
        // Validate amount
        if ($transactionData['amount'] <= 0) {
            throw new Exception('Invalid amount: ' . $transactionData['amount']);
        }
        
        // Check duplicate
        $existing = $db->query(
            "SELECT id FROM bank_logs WHERE transaction_id = ?",
            [$transactionData['transaction_id']]
        );
        
        if (!empty($existing)) {
            echo json_encode(['success' => false, 'message' => 'Duplicate transaction']);
            exit;
        }
        
        // Extract user from description
        $userId = null;
        $userEmail = null;
        $description = $transactionData['description'];
        
        // PAY code format
        if (preg_match('/PAY(\d{4})/i', $description, $matches)) {
            $payCode = $matches[1];
            
            $userResult = $db->query(
                "SELECT id, email FROM users WHERE RIGHT(id, 4) = ?",
                [$payCode]
            );
            
            if (!empty($userResult)) {
                $userId = $userResult[0]['id'];
                $userEmail = $userResult[0]['email'];
                error_log("✅ Found user by PAY{$payCode}: {$userEmail}");
            } else {
                error_log("⚠️ No user found for PAY{$payCode}");
            }
        }
        
        // Save to bank_logs
        $stmt = $db->getPdo()->prepare(
            "INSERT INTO bank_logs (
                transaction_id, user_id, bank_code, account_number,
                amount, description, transaction_date, reference_number,
                raw_data, status, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())"
        );
        
        $stmt->execute([
            $transactionData['transaction_id'],
            $userId,
            $transactionData['bank_code'],
            $transactionData['account_number'],
            $transactionData['amount'],
            $transactionData['description'],
            $transactionData['transaction_date'],
            $transactionData['reference_number'],
            json_encode($data),
            $userId ? 'pending' : 'manual_review'
        ]);
        
        $logId = $db->getPdo()->lastInsertId();
        error_log("✅ Bank log saved with ID: {$logId}");
        
        // Process wallet deposit if user found
        if ($userId) {
            $depositResult = $walletManager->deposit(
                $userId,
                $transactionData['amount'],
                $transactionData['transaction_id'],
                "Nạp tiền qua {$transactionData['bank_code']} - {$transactionData['description']}"
            );
            
            if ($depositResult['success']) {
                // Update bank log status
                $db->query(
                    "UPDATE bank_logs SET status = 'processed', processed_at = NOW() WHERE id = ?",
                    [$logId]
                );
                
                error_log("✅ Wallet deposit successful: User {$userEmail} received {$depositResult['amount_xu']} XU");
                
                echo json_encode([
                    'success' => true,
                    'message' => 'Transaction processed successfully',
                    'transaction_id' => $transactionData['transaction_id'],
                    'user_id' => $userId,
                    'amount_vnd' => $transactionData['amount'],
                    'amount_xu' => $depositResult['amount_xu'],
                    'balance_after' => $depositResult['balance_after']
                ]);
            } else {
                throw new Exception('Wallet deposit failed: ' . ($depositResult['error'] ?? 'Unknown'));
            }
        } else {
            // No user found - saved for manual review
            echo json_encode([
                'success' => true,
                'message' => 'Transaction saved for manual review',
                'transaction_id' => $transactionData['transaction_id'],
                'log_id' => $logId,
                'status' => 'manual_review'
            ]);
        }
        
    } catch (Exception $e) {
        error_log("❌ Webhook error: " . $e->getMessage());
        
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
    }
    
    error_log("=== ZENPN WEBHOOK END ===");
    exit;
}

// GET request - webhook info
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    echo json_encode([
        'success' => true,
        'service' => 'Zenpn Bank Webhook',
        'version' => '2.0',
        'endpoint' => url('api/webhook_zenpn.php'),
        'timestamp' => date('Y-m-d H:i:s'),
        'banks_configured' => count($validTokens),
        'status' => 'ready'
    ]);
    exit;
}

// Other methods not allowed
http_response_code(405);
echo json_encode(['success' => false, 'message' => 'Method not allowed']);
?>