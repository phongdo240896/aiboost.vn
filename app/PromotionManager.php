<?php
class PromotionManager {
    private $db;
    
    public function __construct($database) {
        $this->db = $database;
    }
    
    /**
     * Lấy khuyến mãi đang hoạt động cho một giao dịch nạp tiền
     */
    public function getActivePromotion($depositAmount, $userId = null) {
        try {
            $promotions = $this->db->query("
                SELECT * FROM promotions 
                WHERE status = 'active' 
                AND start_date <= NOW() 
                AND end_date >= NOW()
                AND min_deposit <= ?
                AND (total_usage_limit IS NULL OR 
                     (SELECT COUNT(*) FROM promotion_usage WHERE promotion_id = promotions.id) < total_usage_limit)
                ORDER BY value DESC
            ", [$depositAmount]);
            
            if (empty($promotions)) {
                return null;
            }
            
            // Kiểm tra giới hạn per user nếu có userId
            if ($userId) {
                $validPromotions = [];
                foreach ($promotions as $promo) {
                    if ($this->canUserUsePromotion($userId, $promo['id'], $promo['usage_limit_per_user'])) {
                        $validPromotions[] = $promo;
                    }
                }
                return !empty($validPromotions) ? $validPromotions[0] : null;
            }
            
            return $promotions[0];
        } catch (Exception $e) {
            error_log('Error getting active promotion: ' . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Kiểm tra user có thể sử dụng khuyến mãi không
     */
    private function canUserUsePromotion($userId, $promotionId, $limitPerUser) {
        if ($limitPerUser === null || $limitPerUser <= 0) {
            return true; // Không giới hạn
        }
        
        try {
            $usageCount = $this->db->query("
                SELECT COUNT(*) as count 
                FROM promotion_usage 
                WHERE user_id = ? AND promotion_id = ?
            ", [$userId, $promotionId]);
            
            return ($usageCount[0]['count'] ?? 0) < $limitPerUser;
        } catch (Exception $e) {
            error_log('Error checking user promotion usage: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Tính toán bonus từ khuyến mãi
     */
    public function calculateBonus($promotion, $depositAmount) {
        if (!$promotion) return 0;
        
        $bonusAmount = 0;
        
        switch ($promotion['type']) {
            case 'percentage':
                $bonusAmount = $depositAmount * ($promotion['value'] / 100);
                break;
                
            case 'fixed_amount':
                $bonusAmount = $promotion['value'];
                break;
                
            case 'bonus_xu':
                return floatval($promotion['value']); // Trả về XU trực tiếp
        }
        
        // Áp dụng giới hạn bonus tối đa nếu có
        if ($promotion['max_bonus'] && $bonusAmount > $promotion['max_bonus']) {
            $bonusAmount = $promotion['max_bonus'];
        }
        
        return $bonusAmount;
    }
    
    /**
     * Áp dụng khuyến mãi cho giao dịch nạp tiền
     */
    public function applyPromotion($userId, $transactionId, $depositAmount) {
        try {
            // Tìm khuyến mãi phù hợp (có kiểm tra userId)
            $promotion = $this->getActivePromotion($depositAmount, $userId);
            if (!$promotion) {
                return ['success' => false, 'message' => 'Không có khuyến mãi phù hợp hoặc đã hết lượt sử dụng'];
            }
            
            // Tính toán bonus
            $bonusAmount = $this->calculateBonus($promotion, $depositAmount);
            if ($bonusAmount <= 0) {
                return ['success' => false, 'message' => 'Bonus không hợp lệ'];
            }
            
            // Chuyển đổi bonus VND sang XU (nếu không phải loại bonus_xu)
            $bonusXu = $bonusAmount;
            if ($promotion['type'] !== 'bonus_xu') {
                // Lấy tỷ giá từ settings
                $exchangeRate = $this->getExchangeRate();
                $bonusXu = $bonusAmount / $exchangeRate;
            }
            
            $this->db->beginTransaction();
            
            // Cập nhật số dư ví
            $wallet = $this->db->query("SELECT balance FROM wallets WHERE user_id = ?", [$userId]);
            if (empty($wallet)) {
                throw new Exception('Không tìm thấy ví người dùng');
            }
            
            $currentBalance = $wallet[0]['balance'];
            $newBalance = $currentBalance + $bonusXu;
            
            $this->db->query(
                "UPDATE wallets SET balance = ?, updated_at = NOW() WHERE user_id = ?",
                [$newBalance, $userId]
            );
            
            // ⭐ SỬA: Tạo giao dịch bonus KHÔNG GHI amount_vnd để không ảnh hưởng báo cáo
            $bonusTransactionId = 'PROMO_' . time() . '_' . uniqid();
            $this->db->query("
                INSERT INTO wallet_transactions (
                    transaction_id, user_id, type, amount_vnd, amount_xu, 
                    exchange_rate, balance_before, balance_after, 
                    reference_id, description, status, created_at
                ) VALUES (?, ?, 'deposit', NULL, ?, 0, ?, ?, ?, ?, 'completed', NOW())
            ", [
                $bonusTransactionId,
                $userId,
                $bonusXu,  // Chỉ ghi XU, không ghi VND
                $currentBalance,
                $newBalance,
                $transactionId,
                "🎁 Khuyến mãi: {$promotion['name']} - Tặng {$bonusXu} XU"
            ]);
            
            // Ghi lại việc sử dụng khuyến mãi
            $this->db->query("
                INSERT INTO promotion_usage (
                    user_id, promotion_id, transaction_id, 
                    deposit_amount, bonus_amount, bonus_xu, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, NOW())
            ", [
                $userId,
                $promotion['id'],
                $transactionId,
                $depositAmount,
                $bonusAmount,
                $bonusXu
            ]);
            
            $this->db->commit();
            
            return [
                'success' => true,
                'bonus_xu' => $bonusXu,
                'bonus_amount' => $bonusAmount,
                'promotion' => $promotion
            ];
            
        } catch (Exception $e) {
            $this->db->rollback();
            error_log('Error applying promotion: ' . $e->getMessage());
            return ['success' => false, 'message' => 'Lỗi khi áp dụng khuyến mãi: ' . $e->getMessage()];
        }
    }
    
    /**
     * Lấy tỷ giá hiện tại
     */
    private function getExchangeRate() {
        try {
            $setting = $this->db->query("SELECT value FROM settings WHERE name = 'exchange_rate' LIMIT 1");
            return !empty($setting) ? floatval($setting[0]['value']) : 100;
        } catch (Exception $e) {
            return 100; // Default rate
        }
    }
    
    /**
     * Kiểm tra và cập nhật trạng thái khuyến mãi hết hạn
     */
    public function updateExpiredPromotions() {
        try {
            $this->db->query("
                UPDATE promotions 
                SET status = 'expired', updated_at = NOW() 
                WHERE status = 'active' AND end_date < NOW()
            ");
            
            return true;
        } catch (Exception $e) {
            error_log('Error updating expired promotions: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Lấy thống kê khuyến mãi
     */
    public function getPromotionStats($promotionId = null) {
        try {
            $whereClause = $promotionId ? "WHERE promotion_id = ?" : "";
            $params = $promotionId ? [$promotionId] : [];
            
            $stats = $this->db->query("
                SELECT 
                    COUNT(*) as total_usage,
                    SUM(deposit_amount) as total_deposit,
                    SUM(bonus_amount) as total_bonus_amount,
                    SUM(bonus_xu) as total_bonus_xu,
                    COUNT(DISTINCT user_id) as unique_users
                FROM promotion_usage
                $whereClause
            ", $params);
            
            return !empty($stats) ? $stats[0] : null;
        } catch (Exception $e) {
            error_log('Error getting promotion stats: ' . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Lấy lịch sử sử dụng khuyến mãi của user
     */
    public function getUserPromotionHistory($userId, $limit = 10) {
        try {
            return $this->db->query("
                SELECT 
                    pu.*,
                    p.name as promotion_name,
                    p.type as promotion_type
                FROM promotion_usage pu
                JOIN promotions p ON pu.promotion_id = p.id
                WHERE pu.user_id = ?
                ORDER BY pu.created_at DESC
                LIMIT ?
            ", [$userId, $limit]);
        } catch (Exception $e) {
            error_log('Error getting user promotion history: ' . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Lấy số lần user đã sử dụng khuyến mãi
     */
    public function getUserPromotionUsageCount($userId, $promotionId) {
        try {
            $result = $this->db->query("
                SELECT COUNT(*) as count 
                FROM promotion_usage 
                WHERE user_id = ? AND promotion_id = ?
            ", [$userId, $promotionId]);
            
            return $result[0]['count'] ?? 0;
        } catch (Exception $e) {
            error_log('Error getting user promotion usage count: ' . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * Kiểm tra user có thể sử dụng khuyến mãi cụ thể không
     */
    public function canUserUseSpecificPromotion($userId, $promotionId) {
        try {
            $promotion = $this->db->query("SELECT * FROM promotions WHERE id = ?", [$promotionId]);
            if (empty($promotion)) {
                return false;
            }
            
            $promo = $promotion[0];
            
            // Kiểm tra trạng thái và thời gian
            if ($promo['status'] !== 'active') {
                return false;
            }
            
            $now = new DateTime();
            $startDate = new DateTime($promo['start_date']);
            $endDate = new DateTime($promo['end_date']);
            
            if ($now < $startDate || $now > $endDate) {
                return false;
            }
            
            // Kiểm tra giới hạn per user
            return $this->canUserUsePromotion($userId, $promotionId, $promo['usage_limit_per_user']);
            
        } catch (Exception $e) {
            error_log('Error checking if user can use promotion: ' . $e->getMessage());
            return false;
        }
    }
}
?>