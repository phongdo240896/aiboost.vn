<?php
class SubscriptionMiddleware {
    
    /**
     * Check if user has an active subscription (not Free)
     * @return array ['has_subscription' => bool, 'plan_name' => string, 'status' => string]
     */
    public static function checkUserSubscription($userId) {
        global $db;
        
        try {
            $pdo = $db->getPdo();
            
            // Check for active subscription
            $stmt = $pdo->prepare("
                SELECT * FROM subscriptions 
                WHERE user_id = ? 
                AND status = 'active' 
                AND end_date > NOW() 
                ORDER BY end_date DESC 
                LIMIT 1
            ");
            $stmt->execute([$userId]);
            $subscription = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($subscription) {
                return [
                    'has_subscription' => true,
                    'plan_name' => $subscription['plan_name'],
                    'status' => 'active',
                    'end_date' => $subscription['end_date'],
                    'credits_remaining' => $subscription['credits_remaining']
                ];
            }
            
            // Check if user ever had a subscription (expired)
            $stmt = $pdo->prepare("
                SELECT * FROM subscriptions 
                WHERE user_id = ? 
                ORDER BY created_at DESC 
                LIMIT 1
            ");
            $stmt->execute([$userId]);
            $expiredSub = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($expiredSub) {
                return [
                    'has_subscription' => false,
                    'plan_name' => $expiredSub['plan_name'],
                    'status' => 'expired',
                    'end_date' => $expiredSub['end_date'],
                    'credits_remaining' => 0
                ];
            }
            
            // No subscription found - Free user
            return [
                'has_subscription' => false,
                'plan_name' => 'Free',
                'status' => 'free',
                'end_date' => null,
                'credits_remaining' => 0
            ];
            
        } catch (Exception $e) {
            error_log("SubscriptionMiddleware error: " . $e->getMessage());
            return [
                'has_subscription' => false,
                'plan_name' => 'Free',
                'status' => 'error',
                'end_date' => null,
                'credits_remaining' => 0
            ];
        }
    }
    
    /**
     * Require user to have an active subscription
     * Redirects to upgrade page if no subscription
     */
    public static function requireSubscription() {
        if (!Auth::isLoggedIn()) {
            header('Location: ' . url('login'));
            exit;
        }
        
        // SỬA LỖI: Dùng Auth::getUser() thay vì getUserId()
        $userData = Auth::getUser();
        if (!$userData) {
            header('Location: ' . url('login'));
            exit;
        }
        
        $userId = $userData['id'];
        $subscription = self::checkUserSubscription($userId);
        
        if (!$subscription['has_subscription']) {
            // Store intended URL in session
            $_SESSION['intended_url'] = $_SERVER['REQUEST_URI'];
            $_SESSION['subscription_required'] = true;
            
            // Redirect to subscription required page
            header('Location: ' . url('subscription-required'));
            exit;
        }
        
        return $subscription;
    }
    
    /**
     * Get list of allowed plans for a feature
     */
    public static function getAllowedPlans($feature = 'topup') {
        $plans = [
            'topup' => ['Basic', 'Standard', 'Pro', 'Ultra', 'Premium'],
            'ai_advanced' => ['Pro', 'Ultra', 'Premium'],
            'voice_ai' => ['Standard', 'Pro', 'Ultra', 'Premium'],
            'video_ai' => ['Ultra', 'Premium']
        ];
        
        return $plans[$feature] ?? [];
    }
    
    /**
     * Check if plan has access to feature
     */
    public static function planHasAccess($planName, $feature = 'topup') {
        $allowedPlans = self::getAllowedPlans($feature);
        return in_array($planName, $allowedPlans);
    }
}
?>