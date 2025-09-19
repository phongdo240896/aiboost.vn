<?php
require_once __DIR__ . '/PromotionManager.php';

class WalletManager {
    private $db;
    
    public function __construct($database) {
        $this->db = $database;
    }
    
    public function deposit($userId, $amountVnd, $transactionId = null, $description = 'Nạp tiền') {
        try {
            $this->db->beginTransaction();
            
            // Validate input
            if (!$userId || $amountVnd <= 0) {
                throw new Exception('Dữ liệu không hợp lệ');
            }
            
            // Get exchange rate
            $exchangeRate = $this->getExchangeRate();
            $amountXu = $amountVnd / $exchangeRate;
            
            // Generate transaction ID if not provided
            if (!$transactionId) {
                $transactionId = 'DEP_' . time() . '_' . uniqid();
            }
            
            // Get or create wallet
            $wallet = $this->db->query("SELECT * FROM wallets WHERE user_id = ?", [$userId]);
            if (empty($wallet)) {
                // Create wallet if doesn't exist
                $this->db->query(
                    "INSERT INTO wallets (user_id, balance, created_at) VALUES (?, 0, NOW())",
                    [$userId]
                );
                $currentBalance = 0;
            } else {
                $currentBalance = $wallet[0]['balance'];
            }
            
            $newBalance = $currentBalance + $amountXu;
            
            // Update wallet balance
            $this->db->query(
                "UPDATE wallets SET balance = ?, updated_at = NOW() WHERE user_id = ?",
                [$newBalance, $userId]
            );
            
            // Create transaction record
            $this->db->query("
                INSERT INTO wallet_transactions (
                    transaction_id, user_id, type, amount_vnd, amount_xu, 
                    exchange_rate, balance_before, balance_after, 
                    description, status, created_at
                ) VALUES (?, ?, 'deposit', ?, ?, ?, ?, ?, ?, 'completed', NOW())
            ", [
                $transactionId,
                $userId,
                $amountVnd,
                $amountXu,
                $exchangeRate,
                $currentBalance,
                $newBalance,
                $description
            ]);
            
            $this->db->commit();
            
            // ====== ÁP DỤNG KHUYẾN MÃI SAU KHI GIAO DỊCH THÀNH CÔNG ======
            try {
                $promotionManager = new PromotionManager($this->db);
                $promotionResult = $promotionManager->applyPromotion($userId, $transactionId, $amountVnd);
                
                if ($promotionResult['success']) {
                    error_log("🎁 Promotion applied for user {$userId}: +{$promotionResult['bonus_xu']} XU from promotion '{$promotionResult['promotion']['name']}'");
                    
                    // Return với thông tin khuyến mãi
                    return [
                        'success' => true,
                        'transaction_id' => $transactionId,
                        'xu_received' => $amountXu,
                        'new_balance' => $newBalance + $promotionResult['bonus_xu'], // Balance sau khi có bonus
                        'promotion' => [
                            'applied' => true,
                            'name' => $promotionResult['promotion']['name'],
                            'bonus_xu' => $promotionResult['bonus_xu'],
                            'bonus_amount' => $promotionResult['bonus_amount']
                        ]
                    ];
                }
            } catch (Exception $e) {
                error_log("Promotion error for user {$userId}: " . $e->getMessage());
                // Không làm fail giao dịch chính nếu khuyến mãi lỗi
            }
            
            return [
                'success' => true,
                'transaction_id' => $transactionId,
                'xu_received' => $amountXu,
                'new_balance' => $newBalance,
                'promotion' => ['applied' => false]
            ];
            
        } catch (Exception $e) {
            $this->db->rollback();
            error_log('Deposit error: ' . $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    public function withdraw($userId, $amountXu, $description = 'Rút XU') {
        try {
            $this->db->beginTransaction();
            
            if (!$userId || $amountXu <= 0) {
                throw new Exception('Dữ liệu không hợp lệ');
            }
            
            // Get current balance
            $wallet = $this->db->query("SELECT balance FROM wallets WHERE user_id = ?", [$userId]);
            if (empty($wallet)) {
                throw new Exception('Không tìm thấy ví');
            }
            
            $currentBalance = $wallet[0]['balance'];
            if ($currentBalance < $amountXu) {
                throw new Exception('Số dư không đủ');
            }
            
            $newBalance = $currentBalance - $amountXu;
            
            // Update wallet
            $this->db->query(
                "UPDATE wallets SET balance = ?, updated_at = NOW() WHERE user_id = ?",
                [$newBalance, $userId]
            );
            
            // Create transaction
            $transactionId = 'WTH_' . time() . '_' . uniqid();
            $this->db->query("
                INSERT INTO wallet_transactions (
                    transaction_id, user_id, type, amount_xu, 
                    balance_before, balance_after, 
                    description, status, created_at
                ) VALUES (?, ?, 'withdraw', ?, ?, ?, ?, 'completed', NOW())
            ", [
                $transactionId,
                $userId,
                $amountXu,
                $currentBalance,
                $newBalance,
                $description
            ]);
            
            $this->db->commit();
            
            return [
                'success' => true,
                'transaction_id' => $transactionId,
                'xu_withdrawn' => $amountXu,
                'new_balance' => $newBalance
            ];
            
        } catch (Exception $e) {
            $this->db->rollback();
            error_log('Withdraw error: ' . $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    /**
     * Lấy tỷ giá hiện tại - SỬA LẠI ĐỂ QUERY ĐÚNG CỘT
     */
    public function getExchangeRate() {
        try {
            // Sửa lại: query cột 'name' thay vì 'setting_key'
            $setting = $this->db->query("SELECT value FROM settings WHERE name = 'exchange_rate' LIMIT 1");
            return !empty($setting) ? floatval($setting[0]['value']) : 100; // Default rate 100
        } catch (Exception $e) {
            error_log('Error getting exchange rate: ' . $e->getMessage());
            return 100; // Default rate
        }
    }
    
    /**
     * Cập nhật tỷ giá - SỬA LẠI ĐỂ UPDATE ĐÚNG CỘT
     */
    public function updateExchangeRate($newRate) {
        try {
            // Sửa lại: dùng cột 'name' thay vì 'setting_key'
            $this->db->query("
                INSERT INTO settings (name, value, updated_at) 
                VALUES ('exchange_rate', ?, NOW())
                ON DUPLICATE KEY UPDATE value = VALUES(value), updated_at = NOW()
            ", [$newRate]);
            
            return true;
        } catch (Exception $e) {
            error_log('Error updating exchange rate: ' . $e->getMessage());
            return false;
        }
    }
    
    public function getBalance($userId) {
        try {
            $wallet = $this->db->query("SELECT balance FROM wallets WHERE user_id = ?", [$userId]);
            return !empty($wallet) ? floatval($wallet[0]['balance']) : 0;
        } catch (Exception $e) {
            return 0;
        }
    }
    
    /**
     * Lấy danh sách giao dịch của user
     */
    public function getTransactions($userId, $limit = 10) {
        try {
            return $this->db->query("
                SELECT 
                    transaction_id,
                    type,
                    amount_vnd,
                    amount_xu,
                    exchange_rate,
                    balance_before,
                    balance_after,
                    reference_id,
                    description,
                    status,
                    created_at
                FROM wallet_transactions 
                WHERE user_id = ? 
                ORDER BY created_at DESC 
                LIMIT ?
            ", [$userId, $limit]);
        } catch (Exception $e) {
            error_log('Error getting transactions: ' . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Lấy lịch sử giao dịch (alias cho getTransactions để tương thích)
     */
    public function getTransactionHistory($userId, $limit = 10) {
        return $this->getTransactions($userId, $limit);
    }
}
?>