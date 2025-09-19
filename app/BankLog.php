<?php
class BankLog {
    private $db;
    
    public function __construct($db) {
        $this->db = $db;
        $this->createTableIfNotExists();
    }
    
    /**
     * Tạo bảng bank_logs nếu chưa tồn tại
     */
    private function createTableIfNotExists() {
        $sql = "CREATE TABLE IF NOT EXISTS `bank_logs` (
            `id` INT(11) NOT NULL AUTO_INCREMENT,
            `transaction_id` VARCHAR(100) UNIQUE,
            `user_id` VARCHAR(36),
            `bank_code` VARCHAR(20),
            `account_number` VARCHAR(50),
            `amount` DECIMAL(15,2),
            `description` TEXT,
            `transaction_date` DATETIME,
            `reference_number` VARCHAR(100),
            `balance_after` DECIMAL(15,2),
            `raw_data` JSON,
            `status` ENUM('pending', 'processed', 'failed', 'duplicate') DEFAULT 'pending',
            `processed_at` DATETIME NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            INDEX `idx_user_id` (`user_id`),
            INDEX `idx_transaction_id` (`transaction_id`),
            INDEX `idx_status` (`status`),
            INDEX `idx_created_at` (`created_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        try {
            $this->db->query($sql);
        } catch (Exception $e) {
            error_log('Error creating bank_logs table: ' . $e->getMessage());
        }
    }
    
    /**
     * Ghi log giao dịch ngân hàng
     */
    public function logTransaction($data) {
        try {
            // VALIDATE DATA - Chỉ lưu giao dịch hợp lệ
            if (!isset($data['amount']) || floatval($data['amount']) <= 0) {
                return [
                    'success' => false,
                    'message' => 'Invalid amount: must be greater than 0'
                ];
            }
            
            if (empty($data['description'])) {
                return [
                    'success' => false,
                    'message' => 'Description is required'
                ];
            }
            
            // Check duplicate
            $checkSql = "SELECT id FROM bank_logs WHERE transaction_id = ?";
            $existing = $this->db->query($checkSql, [$data['transaction_id']]);
            
            if (!empty($existing)) {
                return [
                    'success' => false,
                    'message' => 'Transaction already exists',
                    'duplicate' => true
                ];
            }
            
            // Insert new log
            $insertSql = "INSERT INTO bank_logs (
                transaction_id, user_id, bank_code, account_number, 
                amount, description, transaction_date, reference_number, 
                balance_after, raw_data, status
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            
            $params = [
                $data['transaction_id'],
                $data['user_id'] ?? null,
                $data['bank_code'],
                $data['account_number'],
                floatval($data['amount']), // Ensure numeric
                $data['description'],
                $data['transaction_date'],
                $data['reference_number'] ?? null,
                isset($data['balance_after']) ? floatval($data['balance_after']) : null,
                json_encode($data['raw_data'] ?? []),
                $data['status'] ?? 'pending'
            ];
            
            $this->db->query($insertSql, $params);
            $lastId = $this->db->getPdo()->lastInsertId();
            
            error_log("Bank log saved: ID={$lastId}, Amount={$data['amount']}, Desc={$data['description']}");
            
            return [
                'success' => true,
                'message' => 'Transaction logged successfully',
                'log_id' => $lastId
            ];
            
        } catch (Exception $e) {
            error_log('BankLog error: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Database error: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Lấy log hợp lệ
     */
    public function getValidLogs($limit = 100) {
        try {
            // Chỉ lấy logs với amount > 0
            $sql = "SELECT bl.*, u.email as user_email, u.full_name as user_name
                    FROM bank_logs bl
                    LEFT JOIN users u ON bl.user_id = u.id
                    WHERE bl.amount > 0
                    ORDER BY bl.created_at DESC
                    LIMIT ?";
            return $this->db->query($sql, [$limit]);
        } catch (Exception $e) {
            error_log('Get logs error: ' . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Lấy log chưa xử lý
     */
    public function getPendingLogs($limit = 50) {
        try {
            // Chỉ lấy pending logs với amount > 0
            $sql = "SELECT * FROM bank_logs 
                    WHERE status = 'pending' AND amount > 0 
                    ORDER BY created_at DESC 
                    LIMIT ?";
            return $this->db->query($sql, [$limit]);
        } catch (Exception $e) {
            error_log('Get pending error: ' . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Cập nhật trạng thái log
     */
    public function updateStatus($transactionId, $status, $userId = null) {
        try {
            $sql = "UPDATE bank_logs SET status = ?, processed_at = ?";
            $params = [$status, date('Y-m-d H:i:s')];
            
            if ($userId) {
                $sql .= ", user_id = ?";
                $params[] = $userId;
            }
            
            $sql .= " WHERE transaction_id = ?";
            $params[] = $transactionId;
            
            $this->db->query($sql, $params);
            return true;
            
        } catch (Exception $e) {
            error_log('Update status error: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Lấy log theo user
     */
    public function getUserLogs($userId, $limit = 20) {
        try {
            return $this->db->select('bank_logs', '*', [
                'user_id' => $userId
            ], 'created_at DESC', $limit);
        } catch (Exception $e) {
            error_log('BankLog get user logs error: ' . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Kiểm tra giao dịch trùng lặp
     */
    public function isDuplicate($transactionId) {
        try {
            $existing = $this->db->select('bank_logs', 'id', [
                'transaction_id' => $transactionId
            ]);
            return !empty($existing);
        } catch (Exception $e) {
            error_log('BankLog check duplicate error: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Thống kê log
     */
    public function getStats($dateFrom = null, $dateTo = null) {
        try {
            $where = "1=1";
            $params = [];
            
            if ($dateFrom) {
                $where .= " AND created_at >= ?";
                $params[] = $dateFrom;
            }
            if ($dateTo) {
                $where .= " AND created_at <= ?";
                $params[] = $dateTo;
            }
            
            // Count by status
            $sql = "SELECT 
                    COUNT(*) as total,
                    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
                    SUM(CASE WHEN status = 'processed' THEN 1 ELSE 0 END) as processed,
                    SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed,
                    SUM(CASE WHEN status = 'duplicate' THEN 1 ELSE 0 END) as duplicate,
                    SUM(CASE WHEN status = 'processed' THEN amount ELSE 0 END) as total_amount
                FROM bank_logs WHERE {$where}";
            
            $result = $this->db->query($sql, $params);
            
            if (!empty($result)) {
                return $result[0];
            }
            
            return [
                'total' => 0,
                'pending' => 0,
                'processed' => 0,
                'failed' => 0,
                'duplicate' => 0,
                'total_amount' => 0
            ];
            
        } catch (Exception $e) {
            error_log('BankLog stats error: ' . $e->getMessage());
            return [
                'total' => 0,
                'pending' => 0,
                'processed' => 0,
                'failed' => 0,
                'duplicate' => 0,
                'total_amount' => 0
            ];
        }
    }
}