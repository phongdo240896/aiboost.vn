<?php
class Wallet {
    private $db;
    
    public function __construct($database) {
        $this->db = $database;
    }
    
    /**
     * Get current exchange rate from database - SỬA LẠI
     */
    public function getExchangeRate() {
        try {
            // Sửa lại: query cột 'name' thay vì column sai
            $rateQuery = "SELECT value FROM settings WHERE name = 'exchange_rate' LIMIT 1";
            $result = $this->db->query($rateQuery);
            return $result && count($result) > 0 ? (float)$result[0]['value'] : 100; // Default 100 VND = 1 XU
        } catch (Exception $e) {
            error_log('Exchange rate error: ' . $e->getMessage());
            return 100; // Default fallback
        }
    }
    
    /**
     * Convert VND to XU using current exchange rate
     */
    public function convertVndToXu($vndAmount) {
        $rate = $this->getExchangeRate();
        return round($vndAmount / $rate, 2);
    }
    
    /**
     * Convert XU to VND using current exchange rate
     */
    public function convertXuToVnd($xuAmount) {
        $rate = $this->getExchangeRate();
        return round($xuAmount * $rate, 2);
    }
    
    /**
     * Get user balance in XU
     */
    public function getBalance($userId) {
        try {
            $user = $this->db->select('users', ['balance'], ['id' => $userId]);
            return $user && count($user) > 0 ? (float)$user[0]['balance'] : 0;
        } catch (Exception $e) {
            error_log("Get balance error for user $userId: " . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * Add XU to user balance (from topup)
     */
    public function addXuFromTopup($userId, $vndAmount, $description = 'Nạp tiền từ VND') {
        try {
            $this->db->getPdo()->beginTransaction();
            
            // Convert VND to XU
            $xuAmount = $this->convertVndToXu($vndAmount);
            $rate = $this->getExchangeRate();
            
            // Get current balance
            $currentBalance = $this->getBalance($userId);
            $newBalance = $currentBalance + $xuAmount;
            
            // Update user balance
            $result = $this->db->update('users', 
                ['balance' => $newBalance, 'updated_at' => date('Y-m-d H:i:s')], 
                ['id' => $userId]
            );
            
            if ($result) {
                // Update/create wallet record
                $this->db->query(
                    "INSERT INTO wallets (user_id, balance, updated_at) VALUES (?, ?, NOW()) 
                     ON DUPLICATE KEY UPDATE balance = VALUES(balance), updated_at = NOW()",
                    [$userId, $newBalance]
                );
                
                // Record transaction
                $this->recordTransaction($userId, $xuAmount, 'credit', 
                    $description . " (Quy đổi: " . number_format($vndAmount) . " VND = " . number_format($xuAmount) . " XU tại tỷ giá " . number_format($rate) . ")");
                
                $this->db->getPdo()->commit();
                return [
                    'success' => true,
                    'vnd_amount' => $vndAmount,
                    'xu_amount' => $xuAmount,
                    'exchange_rate' => $rate,
                    'new_balance' => $newBalance
                ];
            } else {
                $this->db->getPdo()->rollback();
                return ['success' => false, 'message' => 'Không thể cập nhật số dư'];
            }
            
        } catch (Exception $e) {
            $this->db->getPdo()->rollback();
            error_log("Add XU from topup error for user $userId: " . $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    /**
     * Deduct XU from user balance (for services)
     */
    public function deductXu($userId, $xuAmount, $description = 'Sử dụng dịch vụ') {
        try {
            $this->db->getPdo()->beginTransaction();
            
            // Get current balance
            $currentBalance = $this->getBalance($userId);
            
            if ($currentBalance < $xuAmount) {
                $this->db->getPdo()->rollback();
                return ['success' => false, 'message' => 'Số dư XU không đủ'];
            }
            
            $newBalance = $currentBalance - $xuAmount;
            
            // Update user balance
            $result = $this->db->update('users', 
                ['balance' => $newBalance, 'updated_at' => date('Y-m-d H:i:s')], 
                ['id' => $userId]
            );
            
            if ($result) {
                // Update wallet record
                $this->db->query(
                    "INSERT INTO wallets (user_id, balance, updated_at) VALUES (?, ?, NOW()) 
                     ON DUPLICATE KEY UPDATE balance = VALUES(balance), updated_at = NOW()",
                    [$userId, $newBalance]
                );
                
                // Record transaction
                $this->recordTransaction($userId, $xuAmount, 'debit', $description);
                
                $this->db->getPdo()->commit();
                return [
                    'success' => true,
                    'xu_amount' => $xuAmount,
                    'new_balance' => $newBalance
                ];
            } else {
                $this->db->getPdo()->rollback();
                return ['success' => false, 'message' => 'Không thể cập nhật số dư'];
            }
            
        } catch (Exception $e) {
            $this->db->getPdo()->rollback();
            error_log("Deduct XU error for user $userId: " . $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    /**
     * Record transaction
     */
    private function recordTransaction($userId, $amount, $type, $description) {
        try {
            return $this->db->insert('transactions', [
                'user_id' => $userId,
                'amount' => $amount,
                'type' => $type,
                'description' => $description,
                'status' => 'completed',
                'created_at' => date('Y-m-d H:i:s')
            ]);
        } catch (Exception $e) {
            error_log("Record transaction error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get user transactions
     */
    public function getTransactions($userId, $limit = 50) {
        try {
            return $this->db->select('transactions', 
                '*', 
                ['user_id' => $userId], 
                'created_at DESC', 
                $limit
            );
        } catch (Exception $e) {
            error_log("Get transactions error for user $userId: " . $e->getMessage());
            return [];
        }
    }
}

// Initialize wallet instance
try {
    $wallet = new Wallet($db);
} catch (Exception $e) {
    error_log("Failed to initialize wallet: " . $e->getMessage());
    $wallet = null;
}
?>