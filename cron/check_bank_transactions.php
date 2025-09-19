<?php
/**
 * CRON JOB: TỰ ĐỘNG XỬ LÝ THANH TOÁN NÂNG CẤP GÓI
 * Version 6.1 - Fix yearly plan detection
 */

require_once __DIR__ . '/../app/config.php';
require_once __DIR__ . '/../app/db.php';

// Cấu hình
define('EXCHANGE_RATE', 100);
define('DEBUG_MODE', isset($_GET['test']));

if (DEBUG_MODE) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
    echo "<pre style='font-family: monospace; background: #1e1e1e; color: #d4d4d4; padding: 20px;'>";
}

$stats = [
    'processed' => 0,
    'skipped' => 0,
    'errors' => 0,
    'subscriptions_created' => 0,
    'subscriptions_extended' => 0
];

$startTime = microtime(true);
logMessage("╔══════════════════════════════════════════════════════════╗");
logMessage("║     CRON SUBSCRIPTION PAYMENT - " . date('Y-m-d H:i:s') . "     ║");
logMessage("╚══════════════════════════════════════════════════════════╝\n");

function logMessage($message, $type = 'info') {
    $prefix = [
        'info' => '📌',
        'success' => '✅', 
        'warning' => '⚠️',
        'error' => '❌',
        'bank' => '🏦',
        'money' => '💰',
        'user' => '👤',
        'plan' => '📦'
    ];
    
    $icon = $prefix[$type] ?? '▸';
    $logMsg = "$icon $message";
    
    error_log($logMsg);
    if (DEBUG_MODE) {
        $color = [
            'success' => '#4caf50',
            'error' => '#f44336',
            'warning' => '#ff9800',
            'money' => '#ffc107',
            'bank' => '#2196f3'
        ];
        $style = isset($color[$type]) ? "color: {$color[$type]};" : "";
        echo "<span style='$style'>$logMsg</span>\n";
    }
}

/**
 * UPDATED: Correct plan mapping with actual prices and credits
 */
function getPlanByAmount($amount) {
    $plans = [
        // MONTHLY PLANS (giá gốc)
        2000 => ['name' => 'Standard', 'credits' => 2000, 'duration' => 30, 'type' => 'monthly'],
        5000 => ['name' => 'Pro', 'credits' => 6000, 'duration' => 30, 'type' => 'monthly'],
        10000 => ['name' => 'Ultra', 'credits' => 13000, 'duration' => 30, 'type' => 'monthly'],
        
        // YEARLY PLANS (giảm 20%, +10% xu bonus)
        19200 => ['name' => 'Standard', 'credits' => 26400, 'duration' => 365, 'type' => 'yearly'],  
        48000 => ['name' => 'Pro', 'credits' => 79200, 'duration' => 365, 'type' => 'yearly'],
        96000 => ['name' => 'Ultra', 'credits' => 171600, 'duration' => 365, 'type' => 'yearly'], // <-- FIX THIS
    ];
    
    // Check exact match first
    if (isset($plans[$amount])) {
        logMessage("✓ Exact match found for amount: " . number_format($amount) . " VND", 'success');
        return $plans[$amount];
    }
    
    // Allow 5% tolerance
    foreach ($plans as $planAmount => $planInfo) {
        if (abs($amount - $planAmount) <= $planAmount * 0.05) {
            logMessage("✓ Fuzzy match found for amount: " . number_format($amount) . " VND (~" . number_format($planAmount) . ")", 'success');
            return $planInfo;
        }
    }
    
    logMessage("⚠️ No plan match for amount: " . number_format($amount) . " VND", 'warning');
    return null;
}

class SubscriptionProcessor {
    private $db;
    private $stats;
    
    public function __construct($db, &$stats) {
        $this->db = $db;
        $this->stats = &$stats;
    }
    
    public function getActiveBanks() {
        $sql = "SELECT * FROM bank_settings 
                WHERE status = 'active' 
                AND api_token IS NOT NULL 
                AND api_token != ''";
        
        $banks = $this->db->query($sql);
        
        if (empty($banks)) {
            throw new Exception("Không tìm thấy ngân hàng hoạt động");
        }
        
        logMessage("Tìm thấy " . count($banks) . " ngân hàng hoạt động", 'bank');
        return $banks;
    }
    
    public function fetchTransactionsFromAPI($bank) {
        $bankCode = $bank['bank_code'];
        $apiToken = $bank['api_token'];
        
        $endpoints = [
            'ACB' => 'https://api.zenpn.com/api/historyacb/',
            'VCB' => 'https://api.zenpn.com/api/historyvcb/',
            'MBBANK' => 'https://api.zenpn.com/api/historymb/'
        ];
        
        if (!isset($endpoints[$bankCode])) {
            logMessage("Không hỗ trợ bank code: $bankCode", 'warning');
            return [];
        }
        
        $apiUrl = $endpoints[$bankCode] . $apiToken;
        logMessage("Gọi API: $bankCode", 'bank');
        
        $ch = curl_init($apiUrl);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_TIMEOUT => 30
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode !== 200 || !$response) {
            logMessage("API trả về lỗi (HTTP $httpCode)", 'error');
            return [];
        }
        
        $data = json_decode($response, true);
        $transactions = $data['data'] ?? [];
        
        $incomingTx = array_filter($transactions, function($tx) {
            return ($tx['type'] ?? '') === 'IN' && ($tx['amount'] ?? 0) > 0;
        });
        
        logMessage("Tìm thấy " . count($incomingTx) . " giao dịch vào", 'success');
        return $incomingTx;
    }
    
    public function processTransaction($txData, $bankCode) {
        $transactionId = $txData['transactionNumber'] ?? uniqid($bankCode . '_');
        $amount = floatval($txData['amount'] ?? 0);
        $description = trim($txData['description'] ?? '');
        
        logMessage("\n┌─ Giao dịch: $transactionId", 'money');
        logMessage("├─ Số tiền: " . number_format($amount) . " VND", 'money');
        logMessage("├─ Nội dung: $description", 'info');
        
        if ($this->isTransactionProcessed($transactionId)) {
            logMessage("└─ ⏭️ Đã xử lý rồi, bỏ qua", 'warning');
            $this->stats['skipped']++;
            return;
        }
        
        $analysis = $this->analyzeDescription($description);
        
        if ($analysis['type'] === 'subscription') {
            $this->processSubscriptionPayment($transactionId, $amount, $description, $analysis, $bankCode, $txData);
        } elseif ($analysis['type'] === 'topup') {
            $this->processTopupPayment($transactionId, $amount, $description, $analysis, $bankCode, $txData);
        } else {
            $this->saveUnmatchedTransaction($transactionId, $amount, $description, $bankCode, $txData);
        }
    }
    
    private function isTransactionProcessed($transactionId) {
        $sql = "SELECT id, status FROM bank_logs WHERE transaction_id = ?";
        $result = $this->db->query($sql, [$transactionId]);
        
        if (!empty($result)) {
            return $result[0]['status'] === 'processed';
        }
        return false;
    }
    
    private function analyzeDescription($description) {
        if (preg_match('/(SUB|PLAN)([0-9A-Z]{6,})/i', $description, $matches)) {
            return [
                'type' => 'subscription',
                'code' => strtoupper($matches[0]),
                'prefix' => strtoupper($matches[1])
            ];
        }
        
        if (preg_match('/PAY(\d{4})/i', $description, $matches)) {
            return [
                'type' => 'topup',
                'code' => 'PAY' . $matches[1]
            ];
        }
        
        return ['type' => 'unknown'];
    }
    
    private function processSubscriptionPayment($transactionId, $amount, $description, $analysis, $bankCode, $rawData) {
        $orderCode = $analysis['code'];
        logMessage("├─ Loại: NÂNG CẤP GÓI ($orderCode)", 'plan');
        
        // Get order details
        $sql = "SELECT 
                    so.*,
                    u.id as user_id,
                    u.email,
                    u.full_name
                FROM subscription_orders so
                JOIN users u ON so.user_id = u.id
                WHERE so.order_id = ?
                LIMIT 1";
        
        $orderData = $this->db->query($sql, [$orderCode]);
        
        if (empty($orderData)) {
            logMessage("├─ ⚠️ Không tìm thấy đơn hàng $orderCode", 'warning');
            $this->saveUnmatchedTransaction($transactionId, $amount, $description, $bankCode, $rawData);
            return;
        }
        
        $order = $orderData[0];
        
        // IMPORTANT: Determine plan based on ACTUAL AMOUNT PAID
        $planInfo = getPlanByAmount($amount);
        
        if (!$planInfo) {
            logMessage("├─ ⚠️ Không xác định được gói từ số tiền: " . number_format($amount) . " VND", 'warning');
            
            // Fallback to order data if available
            if ($order['plan_id']) {
                $sql = "SELECT * FROM subscription_plans WHERE id = ?";
                $planData = $this->db->query($sql, [$order['plan_id']]);
                
                if (!empty($planData)) {
                    $plan = $planData[0];
                    // Guess if yearly based on amount
                    $isYearly = $amount > 50000; // If > 50k, likely yearly
                    
                    $planInfo = [
                        'name' => $plan['name'],
                        'credits' => $isYearly ? ($plan['credits'] * 12 * 1.1) : $plan['credits'], // 12 months + 10% bonus
                        'duration' => $isYearly ? 365 : 30,
                        'type' => $isYearly ? 'yearly' : 'monthly'
                    ];
                } else {
                    $this->saveUnmatchedTransaction($transactionId, $amount, $description, $bankCode, $rawData);
                    return;
                }
            } else {
                $this->saveUnmatchedTransaction($transactionId, $amount, $description, $bankCode, $rawData);
                return;
            }
        }
        
        logMessage("├─ User: {$order['email']}", 'user');
        logMessage("├─ Gói: {$planInfo['name']} ({$planInfo['type']})", 'plan');
        logMessage("├─ Credits: " . number_format($planInfo['credits']) . " XU", 'plan');
        logMessage("├─ Thời hạn: {$planInfo['duration']} ngày", 'plan');
        
        $this->db->getPdo()->beginTransaction();
        
        try {
            // 1. Save bank log
            $bankLogId = $this->saveBankLog($transactionId, $amount, $description, $bankCode, $rawData, $order['user_id'], 'processing');
            
            // 2. Add XU to wallet - USE PLAN CREDITS NOT CONVERTED
            $xuToAdd = (int)$planInfo['credits'];
            $walletResult = $this->addXUToWallet(
                $order['user_id'], 
                $xuToAdd, 
                $amount, 
                $transactionId, 
                "Nâng cấp gói {$planInfo['name']} ({$planInfo['type']}) - " . number_format($xuToAdd) . " XU"
            );
            
            logMessage("├─ 💰 Đã cộng " . number_format($xuToAdd) . " XU vào ví", 'success');
            logMessage("│  └─ Số dư: " . number_format($walletResult['before']) . " → " . number_format($walletResult['after']) . " XU", 'money');
            
            // 3. Create/extend subscription
            $subResult = $this->createOrExtendSubscription(
                $order['user_id'],
                $order['plan_id'] ?? 3, // Default to Ultra (id=3) if not set
                $planInfo['name'],
                $planInfo['credits'],
                $planInfo['duration'],
                $orderCode,
                $transactionId
            );
            
            if ($subResult['action'] === 'created') {
                logMessage("├─ 📅 Đã tạo gói mới, hết hạn: {$subResult['end_date']}", 'success');
                $this->stats['subscriptions_created']++;
            } else {
                logMessage("├─ 📅 Đã gia hạn gói, hết hạn mới: {$subResult['end_date']}", 'success');
                $this->stats['subscriptions_extended']++;
            }
            
            // 4. Update order status
            $this->updateOrderStatus($orderCode, $transactionId);
            
            // 5. Update bank log status
            $this->updateBankLogStatus($bankLogId, 'processed');
            
            $this->db->getPdo()->commit();
            
            logMessage("└─ ✅ XỬ LÝ THÀNH CÔNG!", 'success');
            $this->stats['processed']++;
            
        } catch (Exception $e) {
            $this->db->getPdo()->rollBack();
            logMessage("└─ ❌ LỖI: " . $e->getMessage(), 'error');
            $this->stats['errors']++;
        }
    }
    
    private function processTopupPayment($transactionId, $amount, $description, $analysis, $bankCode, $rawData) {
        $payCode = substr($analysis['code'], -4);
        logMessage("├─ Loại: NẠP TIỀN (Code: {$analysis['code']})", 'money');
        
        $sql = "SELECT id, email, full_name FROM users WHERE RIGHT(id, 4) = ?";
        $userData = $this->db->query($sql, [$payCode]);
        
        if (empty($userData)) {
            logMessage("├─ ⚠️ Không tìm thấy user với mã PAY$payCode", 'warning');
            $this->saveUnmatchedTransaction($transactionId, $amount, $description, $bankCode, $rawData);
            return;
        }
        
        $user = $userData[0];
        logMessage("├─ User: {$user['email']}", 'user');
        
        $this->db->getPdo()->beginTransaction();
        
        try {
            $bankLogId = $this->saveBankLog($transactionId, $amount, $description, $bankCode, $rawData, $user['id'], 'processing');
            
            $xuToAdd = floor($amount / EXCHANGE_RATE);
            $walletResult = $this->addXUToWallet($user['id'], $xuToAdd, $amount, $transactionId,
                "Nạp tiền qua $bankCode - PAY$payCode");
            
            logMessage("├─ 💰 Đã nạp {$xuToAdd} XU (rate: " . EXCHANGE_RATE . " VND/XU)", 'success');
            logMessage("│  └─ Số dư: {$walletResult['before']} → {$walletResult['after']} XU", 'money');
            
            $this->updateBankLogStatus($bankLogId, 'processed');
            
            $this->db->getPdo()->commit();
            
            logMessage("└─ ✅ NẠP TIỀN THÀNH CÔNG!", 'success');
            $this->stats['processed']++;
            
        } catch (Exception $e) {
            $this->db->getPdo()->rollBack();
            logMessage("└─ ❌ LỖI: " . $e->getMessage(), 'error');
            $this->stats['errors']++;
        }
    }
    
    private function addXUToWallet($userId, $xuAmount, $vndAmount, $transactionId, $description) {
        $sql = "SELECT * FROM wallets WHERE user_id = ?";
        $wallet = $this->db->query($sql, [$userId]);
        
        $balanceBefore = 0;
        $balanceAfter = $xuAmount;
        
        if (empty($wallet)) {
            $sql = "INSERT INTO wallets (user_id, balance, created_at) VALUES (?, ?, NOW())";
            $stmt = $this->db->getPdo()->prepare($sql);
            $stmt->execute([$userId, $xuAmount]);
        } else {
            $balanceBefore = $wallet[0]['balance'];
            $balanceAfter = $balanceBefore + $xuAmount;
            
            $sql = "UPDATE wallets SET balance = balance + ?, updated_at = NOW() WHERE user_id = ?";
            $stmt = $this->db->getPdo()->prepare($sql);
            $stmt->execute([$xuAmount, $userId]);
        }
        
        $walletTxId = 'WTX_' . time() . '_' . uniqid();
        
        $sql = "INSERT INTO wallet_transactions 
                (transaction_id, user_id, type, amount_vnd, amount_xu, exchange_rate,
                 balance_before, balance_after, reference_id, description, status, created_at)
                VALUES (?, ?, 'deposit', ?, ?, ?, ?, ?, ?, ?, 'completed', NOW())";
        
        $stmt = $this->db->getPdo()->prepare($sql);
        $stmt->execute([
            $walletTxId, $userId, $vndAmount, $xuAmount, EXCHANGE_RATE,
            $balanceBefore, $balanceAfter, $transactionId, $description
        ]);
        
        return ['before' => $balanceBefore, 'after' => $balanceAfter];
    }
    
    private function createOrExtendSubscription($userId, $planId, $planName, $credits, $duration, $orderId, $transactionId) {
        // Check for active subscription
        $sql = "SELECT * FROM subscriptions 
                WHERE user_id = ? 
                AND status = 'active'
                AND end_date > NOW()
                ORDER BY end_date DESC 
                LIMIT 1";
        
        $activeSub = $this->db->query($sql, [$userId]);
        
        if (!empty($activeSub)) {
            // UPGRADE/EXTEND existing subscription
            $currentEndDate = $activeSub[0]['end_date'];
            
            // If upgrading plan, use new end date from today
            if ($activeSub[0]['plan_name'] !== $planName) {
                $newEndDate = date('Y-m-d H:i:s', strtotime("+$duration days"));
                logMessage("📈 Upgrading from {$activeSub[0]['plan_name']} to $planName", 'info');
            } else {
                // Extending same plan
                $newEndDate = date('Y-m-d H:i:s', strtotime($currentEndDate) + ($duration * 86400));
            }
            
            $sql = "UPDATE subscriptions SET 
                    plan_id = ?,
                    plan_name = ?,
                    end_date = ?,
                    credits_total = ?,
                    credits_remaining = ?,
                    updated_at = NOW()
                    WHERE id = ?";
            
            $stmt = $this->db->getPdo()->prepare($sql);
            $stmt->execute([$planId, $planName, $newEndDate, $credits, $credits, $activeSub[0]['id']]);
            
            return ['action' => 'extended', 'end_date' => $newEndDate];
            
        } else {
            // Create new subscription
            $startDate = date('Y-m-d H:i:s');
            $endDate = date('Y-m-d H:i:s', strtotime("+$duration days"));
            
            $sql = "INSERT INTO subscriptions 
                    (user_id, plan_id, plan_name, start_date, end_date,
                     credits_total, credits_remaining, status, transaction_id)
                    VALUES (?, ?, ?, ?, ?, ?, ?, 'active', ?)";
            
            $stmt = $this->db->getPdo()->prepare($sql);
            $stmt->execute([
                $userId, $planId, $planName, $startDate, $endDate,
                $credits, $credits, $transactionId
            ]);
            
            return ['action' => 'created', 'end_date' => $endDate];
        }
    }
    
    private function saveBankLog($transactionId, $amount, $description, $bankCode, $rawData, $userId = null, $status = 'pending') {
        $sql = "INSERT INTO bank_logs 
                (transaction_id, user_id, bank_code, account_number, amount, 
                 description, transaction_date, reference_number, raw_data, status, created_at)
                VALUES (?, ?, ?, ?, ?, ?, NOW(), ?, ?, ?, NOW())";
        
        $stmt = $this->db->getPdo()->prepare($sql);
        $stmt->execute([
            $transactionId,
            $userId,
            $bankCode,
            $rawData['accountNumber'] ?? '',
            $amount,
            $description,
            $transactionId,
            json_encode($rawData),
            $status
        ]);
        
        return $this->db->getPdo()->lastInsertId();
    }
    
    private function updateBankLogStatus($bankLogId, $status) {
        $sql = "UPDATE bank_logs SET status = ?, processed_at = NOW() WHERE id = ?";
        $stmt = $this->db->getPdo()->prepare($sql);
        $stmt->execute([$status, $bankLogId]);
    }
    
    private function updateOrderStatus($orderCode, $transactionId) {
        $sql = "UPDATE subscription_orders SET 
                status = 'completed',
                transaction_id = ?
                WHERE order_id = ?";
        
        $stmt = $this->db->getPdo()->prepare($sql);
        $stmt->execute([$transactionId, $orderCode]);
    }
    
    private function saveUnmatchedTransaction($transactionId, $amount, $description, $bankCode, $rawData) {
        logMessage("├─ 📝 Lưu để xem xét thủ công", 'warning');
        $this->saveBankLog($transactionId, $amount, $description, $bankCode, $rawData, null, 'manual_review');
        logMessage("└─ Đã lưu vào bank_logs với status 'manual_review'", 'info');
    }
}

// MAIN EXECUTION
try {
    $processor = new SubscriptionProcessor($db, $stats);
    $banks = $processor->getActiveBanks();
    
    foreach ($banks as $bank) {
        logMessage("\n" . str_repeat("─", 60), 'info');
        logMessage("Ngân hàng: {$bank['bank_name']} ({$bank['bank_code']})", 'bank');
        logMessage(str_repeat("─", 60), 'info');
        
        $transactions = $processor->fetchTransactionsFromAPI($bank);
        
        foreach ($transactions as $tx) {
            $processor->processTransaction($tx, $bank['bank_code']);
        }
    }
    
} catch (Exception $e) {
    logMessage("\n❌ LỖI NGHIÊM TRỌNG: " . $e->getMessage(), 'error');
    $stats['errors']++;
}

// Summary
$duration = round(microtime(true) - $startTime, 2);
logMessage("\n" . str_repeat("═", 60), 'info');
logMessage("TỔNG KẾT", 'info');
logMessage(str_repeat("═", 60), 'info');
logMessage("✓ Xử lý thành công: {$stats['processed']} giao dịch", 'success');
logMessage("✓ Tạo gói mới: {$stats['subscriptions_created']}", 'success');
logMessage("✓ Gia hạn gói: {$stats['subscriptions_extended']}", 'success');
logMessage("⚠ Bỏ qua: {$stats['skipped']}", 'warning');
logMessage("✗ Lỗi: {$stats['errors']}", 'error');
logMessage("⏱ Thời gian: {$duration}s", 'info');

if (DEBUG_MODE) {
    echo "</pre>";
}
?>