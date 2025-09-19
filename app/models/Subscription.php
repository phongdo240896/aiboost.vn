<?php
/**
 * Model quản lý bảng subscriptions
 */

class Subscription {
    
    /**
     * Tạo subscription mới
     * @param array $data
     * @return bool
     */
    public static function create(array $data) {
        try {
            global $db;
            
            // Validate required fields
            $requiredFields = ['user_id', 'plan_id', 'start_date', 'end_date'];
            foreach ($requiredFields as $field) {
                if (!isset($data[$field])) {
                    throw new Exception("Thiếu trường bắt buộc: {$field}");
                }
            }
            
            $sql = "INSERT INTO subscriptions (user_id, plan_id, start_date, end_date, status, created_at) 
                    VALUES (:user_id, :plan_id, :start_date, :end_date, :status, :created_at)";
            
            $stmt = $db->getPdo()->prepare($sql);
            
            // Bind parameters
            $stmt->bindParam(':user_id', $data['user_id'], PDO::PARAM_STR);
            $stmt->bindParam(':plan_id', $data['plan_id'], PDO::PARAM_INT);
            $stmt->bindParam(':start_date', $data['start_date'], PDO::PARAM_STR);
            $stmt->bindParam(':end_date', $data['end_date'], PDO::PARAM_STR);
            
            $status = $data['status'] ?? 'active';
            $stmt->bindParam(':status', $status, PDO::PARAM_STR);
            
            $createdAt = date('Y-m-d H:i:s');
            $stmt->bindParam(':created_at', $createdAt, PDO::PARAM_STR);
            
            return $stmt->execute();
            
        } catch (PDOException $e) {
            throw new Exception("Lỗi khi tạo subscription: " . $e->getMessage());
        }
    }
    
    /**
     * Lấy gói đang hoạt động của user
     * @param string $userId
     * @return array|null
     */
    public static function getActiveByUser(string $userId) {
        try {
            global $db;
            
            $sql = "SELECT s.*, p.name as plan_name, p.credits, p.price 
                    FROM subscriptions s 
                    LEFT JOIN subscription_plans p ON s.plan_id = p.id
                    WHERE s.user_id = :user_id 
                    AND s.status = :status 
                    AND s.end_date >= :current_date
                    ORDER BY s.end_date DESC
                    LIMIT 1";
            
            $stmt = $db->getPdo()->prepare($sql);
            
            $stmt->bindParam(':user_id', $userId, PDO::PARAM_STR);
            
            $status = 'active';
            $stmt->bindParam(':status', $status, PDO::PARAM_STR);
            
            $currentDate = date('Y-m-d H:i:s');
            $stmt->bindParam(':current_date', $currentDate, PDO::PARAM_STR);
            
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            return $result ?: null;
            
        } catch (PDOException $e) {
            throw new Exception("Lỗi khi lấy subscription hoạt động: " . $e->getMessage());
        }
    }
    
    /**
     * Hủy subscription
     * @param int $id
     * @return bool
     */
    public static function cancel(int $id) {
        try {
            global $db;
            
            // Check if subscription exists
            $checkSql = "SELECT id FROM subscriptions WHERE id = :id LIMIT 1";
            $checkStmt = $db->getPdo()->prepare($checkSql);
            $checkStmt->bindParam(':id', $id, PDO::PARAM_INT);
            $checkStmt->execute();
            
            if (!$checkStmt->fetch()) {
                throw new Exception("Không tìm thấy subscription với ID: {$id}");
            }
            
            $sql = "UPDATE subscriptions SET status = :status, updated_at = :updated_at WHERE id = :id";
            $stmt = $db->getPdo()->prepare($sql);
            
            $status = 'cancelled';
            $stmt->bindParam(':status', $status, PDO::PARAM_STR);
            
            $updatedAt = date('Y-m-d H:i:s');
            $stmt->bindParam(':updated_at', $updatedAt, PDO::PARAM_STR);
            
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            
            return $stmt->execute();
            
        } catch (PDOException $e) {
            throw new Exception("Lỗi khi hủy subscription: " . $e->getMessage());
        }
    }
    
    /**
     * Tự động hết hạn các subscription cũ
     * @return int Số lượng subscription đã được cập nhật
     */
    public static function expireOld() {
        try {
            global $db;
            
            $sql = "UPDATE subscriptions 
                    SET status = :new_status, updated_at = :updated_at 
                    WHERE end_date < :current_date 
                    AND status = :current_status";
            
            $stmt = $db->getPdo()->prepare($sql);
            
            $newStatus = 'expired';
            $stmt->bindParam(':new_status', $newStatus, PDO::PARAM_STR);
            
            $updatedAt = date('Y-m-d H:i:s');
            $stmt->bindParam(':updated_at', $updatedAt, PDO::PARAM_STR);
            
            $currentDate = date('Y-m-d H:i:s');
            $stmt->bindParam(':current_date', $currentDate, PDO::PARAM_STR);
            
            $currentStatus = 'active';
            $stmt->bindParam(':current_status', $currentStatus, PDO::PARAM_STR);
            
            $stmt->execute();
            
            return $stmt->rowCount();
            
        } catch (PDOException $e) {
            throw new Exception("Lỗi khi hết hạn subscription cũ: " . $e->getMessage());
        }
    }
    
    /**
     * Lấy lịch sử subscription của user
     * @param string $userId
     * @return array
     */
    public static function history(string $userId) {
        try {
            global $db;
            
            $sql = "SELECT s.*, p.name as plan_name, p.credits, p.price 
                    FROM subscriptions s 
                    LEFT JOIN subscription_plans p ON s.plan_id = p.id
                    WHERE s.user_id = :user_id 
                    ORDER BY s.start_date DESC";
            
            $stmt = $db->getPdo()->prepare($sql);
            $stmt->bindParam(':user_id', $userId, PDO::PARAM_STR);
            
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (PDOException $e) {
            throw new Exception("Lỗi khi lấy lịch sử subscription: " . $e->getMessage());
        }
    }
    
    /**
     * Tìm subscription theo ID
     * @param int $id
     * @return array|null
     */
    public static function find(int $id) {
        try {
            global $db;
            
            $sql = "SELECT s.*, p.name as plan_name, p.credits, p.price 
                    FROM subscriptions s 
                    LEFT JOIN subscription_plans p ON s.plan_id = p.id
                    WHERE s.id = :id 
                    LIMIT 1";
            
            $stmt = $db->getPdo()->prepare($sql);
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            return $result ?: null;
            
        } catch (PDOException $e) {
            throw new Exception("Lỗi khi tìm subscription: " . $e->getMessage());
        }
    }
    
    /**
     * Kiểm tra user có subscription hoạt động không
     * @param string $userId
     * @return bool
     */
    public static function userHasActiveSubscription(string $userId) {
        try {
            $active = self::getActiveByUser($userId);
            return $active !== null;
            
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * Gia hạn subscription
     * @param int $id
     * @param int $additionalDays
     * @return bool
     */
    public static function extend(int $id, int $additionalDays) {
        try {
            global $db;
            
            // Get current subscription
            $subscription = self::find($id);
            if (!$subscription) {
                throw new Exception("Không tìm thấy subscription với ID: {$id}");
            }
            
            // Calculate new end date
            $currentEndDate = new DateTime($subscription['end_date']);
            $currentEndDate->add(new DateInterval("P{$additionalDays}D"));
            $newEndDate = $currentEndDate->format('Y-m-d H:i:s');
            
            $sql = "UPDATE subscriptions 
                    SET end_date = :end_date, updated_at = :updated_at 
                    WHERE id = :id";
            
            $stmt = $db->getPdo()->prepare($sql);
            
            $stmt->bindParam(':end_date', $newEndDate, PDO::PARAM_STR);
            
            $updatedAt = date('Y-m-d H:i:s');
            $stmt->bindParam(':updated_at', $updatedAt, PDO::PARAM_STR);
            
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            
            return $stmt->execute();
            
        } catch (PDOException $e) {
            throw new Exception("Lỗi khi gia hạn subscription: " . $e->getMessage());
        }
    }
    
    /**
     * Lấy thống kê subscription
     * @return array
     */
    public static function getStats() {
        try {
            global $db;
            
            $sql = "SELECT 
                        COUNT(*) as total,
                        SUM(CASE WHEN status = 'active' AND end_date >= NOW() THEN 1 ELSE 0 END) as active,
                        SUM(CASE WHEN status = 'expired' THEN 1 ELSE 0 END) as expired,
                        SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled
                    FROM subscriptions";
            
            $stmt = $db->getPdo()->prepare($sql);
            $stmt->execute();
            
            return $stmt->fetch(PDO::FETCH_ASSOC);
            
        } catch (PDOException $e) {
            throw new Exception("Lỗi khi lấy thống kê subscription: " . $e->getMessage());
        }
    }
    
    /**
     * Tạo bảng subscriptions nếu chưa tồn tại
     * @return bool
     */
    public static function createTableIfNotExists() {
        try {
            global $db;
            
            $sql = "
                CREATE TABLE IF NOT EXISTS subscriptions (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    user_id VARCHAR(50) NOT NULL,
                    plan_id INT NOT NULL,
                    start_date TIMESTAMP NOT NULL,
                    end_date TIMESTAMP NOT NULL,
                    status ENUM('active', 'expired', 'cancelled') DEFAULT 'active',
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    INDEX idx_user_id (user_id),
                    INDEX idx_status (status),
                    INDEX idx_end_date (end_date),
                    INDEX idx_user_status (user_id, status)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ";
            
            return $db->getPdo()->exec($sql) !== false;
            
        } catch (PDOException $e) {
            throw new Exception("Lỗi khi tạo bảng subscriptions: " . $e->getMessage());
        }
    }

}
?>