<?php
require_once __DIR__ . '/auth.php';

/**
 * Middleware Class
 * Handles route protection and access control
 */
class Middleware {
    
    /**
     * Require user to be logged in
     */
    public static function requireLogin($redirectUrl = 'login') {
        if (!Auth::isLoggedIn()) {
            // Store intended URL for redirect after login
            $_SESSION['intended_url'] = $_SERVER['REQUEST_URI'];
            
            header('Location: ' . url($redirectUrl));
            exit;
        }
        
        // Check if session is still valid (optional)
        self::validateSession();
    }
    
    /**
     * Require user to be admin
     */
    public static function requireAdmin($redirectUrl = 'dashboard') {
        self::requireLogin();
        
        if (!Auth::isAdmin()) {
            header('Location: ' . url($redirectUrl));
            exit;
        }
    }
    
    /**
     * Require user to be guest (not logged in)
     */
    public static function requireGuest($redirectUrl = 'dashboard') {
        if (Auth::isLoggedIn()) {
            header('Location: ' . url($redirectUrl));
            exit;
        }
    }
    
    /**
     * Check if user has sufficient balance
     */
    public static function requireBalance($requiredAmount, $redirectUrl = 'topup') {
        self::requireLogin();
        
        $balance = Auth::getBalance();
        
        if ($balance < $requiredAmount) {
            $_SESSION['balance_error'] = "Bạn cần có ít nhất " . number_format($requiredAmount) . "₫ để sử dụng tính năng này";
            header('Location: ' . url($redirectUrl));
            exit;
        }
        
        return true;
    }
    
    /**
     * Validate CSRF token for POST requests
     */
    public static function validateCSRF() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $token = $_POST['csrf_token'] ?? '';
            
            if (!Auth::verifyCSRFToken($token)) {
                http_response_code(403);
                die('CSRF token mismatch');
            }
        }
    }
    
    /**
     * Rate limiting (simple implementation)
     */
    public static function rateLimit($key, $maxRequests = 5, $timeWindow = 300) {
        // Khởi tạo session nếu chưa có
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        $now = time();
        $sessionKey = "rate_limit_$key";
        
        // Nếu chưa có rate limit data
        if (!isset($_SESSION[$sessionKey])) {
            $_SESSION[$sessionKey] = ['count' => 1, 'start_time' => $now];
            return true;
        }
        
        $rateData = $_SESSION[$sessionKey];
        
        // Reset nếu đã hết thời gian
        if ($now - $rateData['start_time'] > $timeWindow) {
            $_SESSION[$sessionKey] = ['count' => 1, 'start_time' => $now];
            return true;
        }
        
        // Kiểm tra có vượt limit không
        if ($rateData['count'] >= $maxRequests) {
            $waitTime = $timeWindow - ($now - $rateData['start_time']);
            
            // Thay vì die(), return false và để register.php xử lý
            return [
                'success' => false, 
                'message' => "Quá nhiều lần thử. Vui lòng đợi $waitTime giây."
            ];
        }
        
        // Tăng counter
        $_SESSION[$sessionKey]['count']++;
        return true;
    }
    
    /**
     * Validate session integrity
     */
    private static function validateSession() {
        // Check session timeout (24 hours)
        if (isset($_SESSION['login_time']) && (time() - $_SESSION['login_time']) > 86400) {
            Auth::logout();
            header('Location: ' . url('login') . '?timeout=1');
            exit;
        }
        
        // Verify user still exists and is active
        $user = Auth::getUser();
        if (!$user || $user['status'] !== 'active') {
            Auth::logout();
            header('Location: ' . url('login') . '?inactive=1');
            exit;
        }
    }
    
    /**
     * Check user permissions for specific actions
     */
    public static function can($permission, $resource = null) {
        if (!Auth::isLoggedIn()) {
            return false;
        }
        
        $userRole = $_SESSION['user_role'] ?? 'user';
        
        // Define permissions
        $permissions = [
            'admin' => [
                'view_admin_panel',
                'manage_users',
                'view_all_transactions',
                'system_settings',
                'create_image',
                'create_video',
                'create_content'
            ],
            'user' => [
                'create_image',
                'create_video', 
                'create_content',
                'view_own_data',
                'topup_wallet'
            ]
        ];
        
        return isset($permissions[$userRole]) && in_array($permission, $permissions[$userRole]);
    }
    
    /**
     * Require specific permission
     */
    public static function requirePermission($permission, $redirectUrl = 'dashboard') {
        self::requireLogin();
        
        if (!self::can($permission)) {
            $_SESSION['permission_error'] = 'Bạn không có quyền truy cập chức năng này';
            header('Location: ' . url($redirectUrl));
            exit;
        }
    }
    
    /**
     * Log user activity
     */
    public static function logActivity($action, $details = null) {
        if (!Auth::isLoggedIn()) {
            return;
        }
        
        try {
            global $db;
            
            $logData = [
                'user_id' => $_SESSION['user_id'],
                'action' => $action,
                'details' => $details,
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
                'created_at' => date('Y-m-d H:i:s')
            ];
            
            // For now, just log to error log
            error_log("User Activity: " . json_encode($logData));
            
        } catch (Exception $e) {
            error_log('Activity log error: ' . $e->getMessage());
        }
    }
    
    /**
     * Sanitize input data
     */
    public static function sanitizeInput($data) {
        if (is_array($data)) {
            return array_map([self::class, 'sanitizeInput'], $data);
        }
        
        return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
    }
    
    /**
     * Validate and sanitize POST data
     */
    public static function validatePostData($rules) {
        $data = [];
        $errors = [];
        
        foreach ($rules as $field => $rule) {
            $value = $_POST[$field] ?? '';
            $value = self::sanitizeInput($value);
            
            // Required check
            if (isset($rule['required']) && $rule['required'] && empty($value)) {
                $errors[$field] = $rule['message'] ?? "Trường {$field} là bắt buộc";
                continue;
            }
            
            // Type validation
            if (!empty($value) && isset($rule['type'])) {
                switch ($rule['type']) {
                    case 'email':
                        if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
                            $errors[$field] = $rule['message'] ?? "Email không hợp lệ";
                        }
                        break;
                    case 'numeric':
                        if (!is_numeric($value)) {
                            $errors[$field] = $rule['message'] ?? "Giá trị phải là số";
                        }
                        break;
                    case 'min_length':
                        if (strlen($value) < $rule['value']) {
                            $errors[$field] = $rule['message'] ?? "Độ dài tối thiểu {$rule['value']} ký tự";
                        }
                        break;
                }
            }
            
            $data[$field] = $value;
        }
        
        return ['data' => $data, 'errors' => $errors];
    }
    
    /**
     * Handle JSON responses for AJAX requests
     */
    public static function jsonResponse($data, $statusCode = 200) {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }
    
    /**
     * Check if request is AJAX
     */
    public static function isAjax() {
        return isset($_SERVER['HTTP_X_REQUESTED_WITH']) && 
               strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    }
}
?>