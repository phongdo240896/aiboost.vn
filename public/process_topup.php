<?php
/**
 * Process topup API endpoint
 * Xử lý webhook từ ngân hàng - CHỈ GHI NHẬN GIAO DỊCH THỰC
 */

require_once __DIR__ . '/../../app/config.php';
require_once __DIR__ . '/../../app/db.php';
require_once __DIR__ . '/../../app/BankLog.php';

// Log để debug
error_log("=== PROCESS TOPUP START ===");
error_log("Request method: " . $_SERVER['REQUEST_METHOD']);

// Initialize BankLog
$bankLog = new BankLog($db);

// Set JSON header
header('Content-Type: application/json');

// Handle webhook từ API ngân hàng
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = file_get_contents('php://input');
    error_log("Raw input: " . $input);
    
    // Try to decode JSON
    $data = json_decode($input, true);
    if (!$data) {
        $data = $_POST;
    }
    
    // VALIDATION - Kiểm tra dữ liệu hợp lệ
    if (empty($data) || !isset($data['amount']) || floatval($data['amount']) <= 0) {
        error_log("❌ Invalid data: amount missing or zero");
        echo json_encode([
            'success' => false,
            'message' => 'Invalid transaction data: amount must be greater than 0'
        ]);
        exit;
    }
    
    try {
        // Prepare bank log data với validation
        $amount = floatval($data['amount']);
        $description = trim($data['description'] ?? $data['content'] ?? '');
        
        // IMPORTANT: Chỉ xử lý nếu có amount > 0 và description hợp lệ
        if ($amount <= 0) {
            throw new Exception('Amount must be greater than 0');
        }
        
        if (empty($description)) {
            throw new Exception('Description is required');
        }
        
        $logData = [
            'transaction_id' => $data['transaction_id'] ?? uniqid('TXN_'),
            'bank_code' => $data['bank_code'] ?? 'ACB',
            'account_number' => $data['account_number'] ?? '',
            'amount' => $amount,
            'description' => $description,
            'transaction_date' => $data['transaction_date'] ?? date('Y-m-d H:i:s'),
            'reference_number' => $data['reference_number'] ?? '',
            'balance_after' => isset($data['balance_after']) ? floatval($data['balance_after']) : null,
            'raw_data' => $data,
            'status' => 'pending'
        ];
        
        error_log("Processing transaction: Amount = {$amount}, Description = {$description}");
        
        // Extract user from description - TÌM USER CHÍNH XÁC
        $userId = null;
        $userEmail = null;
        
        // Pattern: PAY[4 digits] - phải match chính xác
        if (preg_match('/PAY(\d{4})/i', $description, $matches)) {
            $payCodeDigits = $matches[1];
            error_log("Found pay code: PAY{$payCodeDigits}");
            
            // Tìm user với 4 số cuối của ID khớp
            $sql = "SELECT id, email, full_name FROM users WHERE RIGHT(id, 4) = ? AND role = 'user' LIMIT 1";
            $result = $db->query($sql, [$payCodeDigits]);
            
            if (!empty($result)) {
                $user = $result[0];
                $userId = $user['id'];
                $userEmail = $user['email'];
                error_log("✅ Found user: {$userEmail} (ID: {$userId})");
            } else {
                error_log("⚠️ No user found with pay code: PAY{$payCodeDigits}");
            }
        } else {
            error_log("⚠️ No valid pay code in description: {$description}");
        }
        
        // Set user_id nếu tìm thấy
        if ($userId) {
            $logData['user_id'] = $userId;
        }
        
        // CHỈ LƯU BANK LOG NẾU CÓ USER HOẶC LÀ GIAO DỊCH THỰC
        // (không lưu test transaction với amount = 0)
        if ($amount > 0) {
            // Check duplicate
            $checkDuplicate = "SELECT id FROM bank_logs WHERE transaction_id = ?";
            $existing = $db->query($checkDuplicate, [$logData['transaction_id']]);
            
            if (!empty($existing)) {
                error_log("⚠️ Duplicate transaction: " . $logData['transaction_id']);
                echo json_encode([
                    'success' => false,
                    'message' => 'Duplicate transaction',
                    'error' => 'DUPLICATE'
                ]);
                exit;
            }
            
            // Save to bank_logs
            $logResult = $bankLog->logTransaction($logData);
            
            if ($logResult['success']) {
                error_log('✅ Bank log saved: ID=' . $logResult['log_id']);
                
                // NẾU TÌM THẤY USER -> XỬ LÝ NẠP TIỀN
                if ($userId) {
                    // Convert VND to XU (1000 VND = 1 XU)
                    $xuAmount = floor($amount / 1000);
                    
                    if ($xuAmount > 0) {
                        // Create transaction record
                        $newTxId = 'TOPUP_' . time() . '_' . rand(1000, 9999);
                        
                        $txInserted = $db->insert('transactions', [
                            'id' => $newTxId,
                            'user_id' => $userId,
                            'type' => 'credit',
                            'amount' => $xuAmount,
                            'description' => "Nạp tiền qua {$logData['bank_code']} - " . number_format($amount) . " VND",
                            'status' => 'completed',
                            'reference_id' => $logData['transaction_id'],
                            'bank_code' => $logData['bank_code'],
                            'created_at' => date('Y-m-d H:i:s')
                        ]);
                        
                        if ($txInserted) {
                            // Update user balance
                            $updateBalanceQuery = "UPDATE users SET balance = balance + ? WHERE id = ?";
                            $db->query($updateBalanceQuery, [$xuAmount, $userId]);
                            
                            // Update bank log status to processed
                            $updateLogQuery = "UPDATE bank_logs SET status = 'processed', processed_at = NOW(), user_id = ? WHERE transaction_id = ?";
                            $db->query($updateLogQuery, [$userId, $logData['transaction_id']]);
                            
                            error_log("✅ Transaction completed: User {$userEmail}, +{$xuAmount} XU");
                            
                            echo json_encode([
                                'success' => true,
                                'message' => 'Transaction processed successfully',
                                'user_id' => $userId,
                                'user_email' => $userEmail,
                                'amount_vnd' => $amount,
                                'amount_xu' => $xuAmount,
                                'log_id' => $logResult['log_id']
                            ]);
                        } else {
                            throw new Exception('Failed to create transaction record');
                        }
                    } else {
                        echo json_encode([
                            'success' => true,
                            'message' => 'Amount too small to convert to XU (< 1000 VND)',
                            'log_id' => $logResult['log_id']
                        ]);
                    }
                } else {
                    // Không tìm thấy user nhưng vẫn lưu log để admin xử lý
                    echo json_encode([
                        'success' => true,
                        'message' => 'Transaction logged for manual processing (user not found)',
                        'log_id' => $logResult['log_id'],
                        'description' => $description
                    ]);
                }
            } else {
                throw new Exception('Failed to save bank log: ' . ($logResult['message'] ?? 'Unknown error'));
            }
        } else {
            throw new Exception('Invalid amount: must be greater than 0');
        }
        
    } catch (Exception $e) {
        error_log('❌ Process topup error: ' . $e->getMessage());
        
        echo json_encode([
            'success' => false,
            'message' => 'Error: ' . $e->getMessage()
        ]);
    }
    
    error_log("=== PROCESS TOPUP END ===");
    exit;
}

// Handle GET request - kiểm tra status
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        // Get stats
        $stats = [
            'total' => 0,
            'pending' => 0,
            'processed' => 0,
            'today' => 0
        ];
        
        // Total logs
        $totalQuery = "SELECT COUNT(*) as count FROM bank_logs WHERE amount > 0";
        $totalResult = $db->query($totalQuery);
        $stats['total'] = $totalResult[0]['count'] ?? 0;
        
        // Pending logs
        $pendingQuery = "SELECT COUNT(*) as count FROM bank_logs WHERE status = 'pending' AND amount > 0";
        $pendingResult = $db->query($pendingQuery);
        $stats['pending'] = $pendingResult[0]['count'] ?? 0;
        
        // Processed logs
        $processedQuery = "SELECT COUNT(*) as count FROM bank_logs WHERE status = 'processed' AND amount > 0";
        $processedResult = $db->query($processedQuery);
        $stats['processed'] = $processedResult[0]['count'] ?? 0;
        
        // Today's logs
        $todayQuery = "SELECT COUNT(*) as count FROM bank_logs WHERE DATE(created_at) = CURDATE() AND amount > 0";
        $todayResult = $db->query($todayQuery);
        $stats['today'] = $todayResult[0]['count'] ?? 0;
        
        echo json_encode([
            'success' => true,
            'message' => 'Topup processor is running',
            'timestamp' => date('Y-m-d H:i:s'),
            'stats' => $stats,
            'status' => 'OK'
        ]);
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => 'Database error',
            'error' => $e->getMessage()
        ]);
    }
    exit;
}
?>