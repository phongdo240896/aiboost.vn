<?php
require_once __DIR__ . '/../models/Plan.php';
require_once __DIR__ . '/../models/Subscription.php';

class SubscriptionController {

    const YEARLY_DISCOUNT_PERCENT = 20;   // giảm 20% so với 12 tháng cộng lại
    const YEARLY_BONUS_CREDITS    = 10;   // tặng thêm 10% Xu khi mua năm

    /**
     * Mua gói: tạo subscription + cộng Xu vào ví + ghi log
     */
    public static function subscribe(string $userId, int $planId, string $billing = 'monthly'): array {
        global $db;

        try {
            $pdo = $db->getPdo();
            $pdo->beginTransaction();

            // 1) Validate billing
            if (!in_array($billing, ['monthly', 'yearly'])) {
                $billing = 'monthly';
            }

            // 2) Lấy thông tin gói
            $plan = Plan::find($planId);
            if (!$plan) {
                throw new Exception("Không tìm thấy gói.");
            }

            // 3) Tính thời hạn & Xu
            if ($billing === 'yearly') {
                $months = 12;
                $durationDays = 365;
                $creditsToAdd = (int)$plan['credits'] * $months;
                $creditsToAdd = (int) floor($creditsToAdd * (1 + self::YEARLY_BONUS_CREDITS/100.0));
                $priceOriginal = (float)$plan['price'] * $months;
                $priceToPay = $priceOriginal * (1 - self::YEARLY_DISCOUNT_PERCENT/100.0);
            } else {
                $months = 1;
                $durationDays = (int)$plan['duration_days'];  // 30
                $creditsToAdd = (int)$plan['credits'];
                $priceOriginal = (float)$plan['price'];
                $priceToPay = $priceOriginal;
            }

            // 4) Tạo subscription mới cho user với duration days tùy chỉnh
            if (!Subscription::createForUser($userId, $planId, $durationDays)) {
                throw new Exception("Không thể tạo subscription.");
            }

            // 5) Đảm bảo user có ví; nếu chưa có thì tạo (balance=0, credits=0)
            $check = $pdo->prepare("SELECT id FROM wallets WHERE user_id = :uid LIMIT 1");
            $check->execute([':uid' => $userId]);
            if (!$check->fetch()) {
                $ins = $pdo->prepare("
                    INSERT INTO wallets (user_id, balance, credits, created_at, updated_at)
                    VALUES (:uid, 0, 0, NOW(), NOW())
                ");
                $ins->execute([':uid' => $userId]);
            }

            // 6) Cộng Xu vào ví
            $upd = $pdo->prepare("
                UPDATE wallets 
                   SET credits = credits + :credits, updated_at = NOW()
                 WHERE user_id = :uid
            ");
            $upd->execute([
                ':credits' => $creditsToAdd,
                ':uid'     => $userId
            ]);

            // 7) Ghi log giao dịch Xu
            $description = ($billing === 'yearly') 
                ? "Nạp Xu từ gói {$plan['name']} - Yearly (+" . self::YEARLY_BONUS_CREDITS . "% bonus)"
                : "Nạp Xu từ gói {$plan['name']} - Monthly";

            $log = $pdo->prepare("
                INSERT INTO credit_transactions (user_id, type, amount, description, created_at)
                VALUES (:uid, 'credit', :amount, :desc, NOW())
            ");
            $log->execute([
                ':uid'    => $userId,
                ':amount' => $creditsToAdd,
                ':desc'   => $description
            ]);

            $pdo->commit();

            return [
                'success' => true,
                'message' => "Đăng ký gói {$plan['name']} thành công. Đã cộng {$creditsToAdd} Xu.",
                'data' => [
                    'plan_name'        => $plan['name'],
                    'billing'          => $billing,
                    'credits_added'    => $creditsToAdd,
                    'price_original'   => $priceOriginal,
                    'price_to_pay'     => $priceToPay,
                    'discount_percent' => ($billing === 'yearly') ? self::YEARLY_DISCOUNT_PERCENT : 0,
                    'bonus_percent'    => ($billing === 'yearly') ? self::YEARLY_BONUS_CREDITS : 0,
                    'duration_days'    => $durationDays
                ]
            ];

        } catch (Exception $e) {
            if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
            return ['success' => false, 'message' => 'Lỗi: ' . $e->getMessage()];
        }
    }

    /** Lấy gói hiện tại của user */
    public static function getCurrentSubscription(string $userId): array {
        try {
            $sub = Subscription::getActiveByUser($userId);
            return $sub ? ['success' => true, 'data' => $sub]
                        : ['success' => false, 'message' => 'Không có gói đang hoạt động.'];
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Lỗi: ' . $e->getMessage()];
        }
    }

    /** Hủy gói (của chính user) */
    public static function cancelSubscription(string $userId, int $subscriptionId): array {
        try {
            $sub = Subscription::find($subscriptionId);
            if (!$sub) throw new Exception('Không tìm thấy gói.');
            if ($sub['user_id'] !== $userId) throw new Exception('Bạn không có quyền hủy gói này.');
            if ($sub['status'] !== 'active') throw new Exception('Gói đã hủy hoặc hết hạn.');
            return Subscription::cancel($subscriptionId)
                ? ['success' => true, 'message' => 'Hủy gói thành công.']
                : ['success' => false, 'message' => 'Không thể hủy gói.'];
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Lỗi: ' . $e->getMessage()];
        }
    }

    /** Lịch sử gói của user */
    public static function getSubscriptionHistory(string $userId): array {
        try {
            return ['success' => true, 'data' => Subscription::history($userId)];
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Lỗi: ' . $e->getMessage()];
        }
    }

    /** Kiểm tra user có gói active và còn Xu */
    public static function canUseService(string $userId): array {
        try {
            $sub = Subscription::getActiveByUser($userId);
            if (!$sub) return ['success' => true, 'can_use' => false, 'message' => 'Bạn cần đăng ký gói.'];

            global $db;
            $stmt = $db->getPdo()->prepare("SELECT credits FROM wallets WHERE user_id = :uid LIMIT 1");
            $stmt->execute([':uid' => $userId]);
            $wallet = $stmt->fetch(PDO::FETCH_ASSOC) ?: ['credits' => 0];

            return [
                'success'      => true,
                'can_use'      => true,
                'subscription' => $sub,
                'credits'      => (int)$wallet['credits']
            ];
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Lỗi: ' . $e->getMessage()];
        }
    }

    /** Trừ Xu khi dùng dịch vụ */
    public static function deductCredits(string $userId, int $amount, string $description = 'Sử dụng dịch vụ'): array {
        global $db;
        try {
            $pdo = $db->getPdo();
            $pdo->beginTransaction();

            // Check đủ điều kiện
            $can = self::canUseService($userId);
            if (!$can['success'] || !$can['can_use']) throw new Exception($can['message'] ?? 'Không thể dùng dịch vụ.');
            if (($can['credits'] ?? 0) < $amount) throw new Exception('Số Xu không đủ.');

            // Trừ Xu
            $pdo->prepare("UPDATE wallets SET credits = credits - :amt, updated_at = NOW() WHERE user_id = :uid")
                ->execute([':amt' => $amount, ':uid' => $userId]);

            // Log
            $pdo->prepare("
                INSERT INTO credit_transactions (user_id, type, amount, description, created_at)
                VALUES (:uid, 'debit', :amt, :desc, NOW())
            ")->execute([':uid' => $userId, ':amt' => $amount, ':desc' => $description]);

            $pdo->commit();
            return ['success' => true, 'message' => "Đã trừ {$amount} Xu."];
        } catch (Exception $e) {
            if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
            return ['success' => false, 'message' => 'Lỗi: ' . $e->getMessage()];
        }
    }

    /** Hết hạn sub quá hạn (gọi cron) */
    public static function expireOldSubscriptions(): array {
        try {
            $n = Subscription::expireOld();
            return ['success' => true, 'message' => "Đã hết hạn {$n} subscription.", 'expired_count' => $n];
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Lỗi: ' . $e->getMessage()];
        }
    }

    /**
     * Lấy danh sách gói có sẵn (chỉ active plans) - FORCE REFRESH
     * @param bool $forceRefresh Force refresh from database
     * @return array
     */
    public static function getAvailablePlans($forceRefresh = true) {
        try {
            // Always get fresh data from database
            $allPlans = Plan::getActive();
            
            // Log for debugging
            error_log('SubscriptionController::getAvailablePlans - Found ' . count($allPlans) . ' plans');
            error_log('Plans data: ' . json_encode($allPlans));
            
            return [
                'success' => true,
                'data' => $allPlans,
                'message' => 'Lấy danh sách gói thành công',
                'timestamp' => time()
            ];
            
        } catch (Exception $e) {
            error_log('Error getting available plans: ' . $e->getMessage());
            
            // Fallback to database direct query
            try {
                global $db;
                $sql = "SELECT * FROM subscription_plans WHERE COALESCE(status, 'active') = 'active' ORDER BY price ASC";
                $stmt = $db->getPdo()->prepare($sql);
                $stmt->execute();
                $fallbackPlans = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                return [
                    'success' => true,
                    'data' => $fallbackPlans,
                    'message' => 'Sử dụng dữ liệu trực tiếp từ database'
                ];
                
            } catch (Exception $dbError) {
                // Last resort: hardcoded plans
                return [
                    'success' => true,
                    'data' => self::getFallbackPlans(),
                    'message' => 'Sử dụng dữ liệu dự phòng'
                ];
            }
        }
    }

    /**
     * Dữ liệu dự phòng được cập nhật
     */
    private static function getFallbackPlans() {
        return [
            [
                'id' => 1,
                'name' => 'Free',
                'price' => 0,
                'credits' => 500,  // UPDATED TO MATCH DATABASE
                'duration_days' => 30,
                'description' => 'Dùng thử: 500 xu / 30 ngày.',
                'is_recommended' => 0,
                'status' => 'active'
            ],
            [
                'id' => 2,
                'name' => 'Standard',
                'price' => 200000,
                'credits' => 2000,
                'duration_days' => 30,
                'description' => 'Cho người mới dùng thật.',
                'is_recommended' => 0,
                'status' => 'active'
            ],
            [
                'id' => 3,
                'name' => 'Pro',
                'price' => 500000,
                'credits' => 6000,
                'duration_days' => 30,
                'description' => 'Khuyến dùng: giá/xu tốt hơn.',
                'is_recommended' => 1,
                'status' => 'active'
            ],
            [
                'id' => 4,
                'name' => 'Ultra',
                'price' => 1000000,
                'credits' => 13000,
                'duration_days' => 30,
                'description' => 'Dành cho power-user/agency.',
                'is_recommended' => 0,
                'status' => 'active'
            ]
        ];
    }

}
?>