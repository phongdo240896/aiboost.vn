<?php
/**
 * Authentication Class
 */
class Auth {
    private static $db;
    
    public static function init($database) {
        self::$db = $database;
    }
    
    /**
     * User registration - SỬA LẠI ĐỂ AUTO LOGIN
     */
    public static function register($data) {
        try {
            // Extract data
            $email = trim($data['email'] ?? '');
            $password = $data['password'] ?? '';
            $passwordConfirm = $data['password_confirm'] ?? '';
            $fullName = trim($data['full_name'] ?? '');
            $phone = trim($data['phone'] ?? '');
            
            // Validation
            $errors = [];
            
            if (empty($fullName)) {
                $errors['full_name'] = 'Vui lòng nhập họ tên';
            } elseif (strlen($fullName) < 2) {
                $errors['full_name'] = 'Họ tên phải có ít nhất 2 ký tự';
            }
            
            if (empty($email)) {
                $errors['email'] = 'Vui lòng nhập email';
            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errors['email'] = 'Email không hợp lệ';
            }
            
            if (!empty($phone) && !preg_match('/^[0-9]{10,11}$/', $phone)) {
                $errors['phone'] = 'Số điện thoại không hợp lệ';
            }
            
            if (empty($password)) {
                $errors['password'] = 'Vui lòng nhập mật khẩu';
            } elseif (strlen($password) < 6) {
                $errors['password'] = 'Mật khẩu phải có ít nhất 6 ký tự';
            }
            
            if ($password !== $passwordConfirm) {
                $errors['password_confirm'] = 'Mật khẩu xác nhận không khớp';
            }
            
            if (!empty($errors)) {
                return ['success' => false, 'errors' => $errors];
            }
            
            // Check if email already exists
            $pdo = self::$db->getPdo();
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $existing = $stmt->fetch();
            
            if ($existing) {
                return ['success' => false, 'message' => 'Email đã được sử dụng'];
            }
            
            // Hash password
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            
            // Generate user ID
            $userId = 'user_' . time() . '_' . uniqid();
            
            // Create users table if not exists
            self::createUsersTableIfNotExists();
            
            // Insert new user
            $sql = "INSERT INTO users (id, email, password, full_name, phone, role, status, balance, created_at) 
                    VALUES (?, ?, ?, ?, ?, 'user', 'active', 500.00, NOW())";
            
            $stmt = $pdo->prepare($sql);
            $result = $stmt->execute([
                $userId,
                $email,
                $hashedPassword,
                $fullName,
                $phone
            ]);
            
            if ($result) {
                // Create wallet for user
                self::createInitialWallet($userId);
                
                // AUTO LOGIN - Tự động đăng nhập user vừa tạo
                if (session_status() === PHP_SESSION_NONE) {
                    session_start();
                }
                
                // Lấy thông tin user vừa tạo
                $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
                $stmt->execute([$userId]);
                $newUser = $stmt->fetch();
                
                if ($newUser) {
                    // Set session data để đăng nhập
                    $_SESSION['user_id'] = $newUser['id'];
                    $_SESSION['user_email'] = $newUser['email'];
                    $_SESSION['user_role'] = $newUser['role'];
                    $_SESSION['user_name'] = $newUser['full_name'];
                    $_SESSION['logged_in'] = true;
                    
                    // Generate CSRF token
                    $_SESSION['csrf_token'] = self::generateCSRFToken();
                    
                    error_log("Auto login successful for user: " . $newUser['email']);
                }
                
                return [
                    'success' => true, 
                    'message' => 'Đăng ký thành công! Bạn nhận được 500 Xu miễn phí.',
                    'user_id' => $userId,
                    'auto_login' => true,
                    'user_data' => $newUser
                ];
            } else {
                // Lấy thông tin lỗi chi tiết
                $errorInfo = $stmt->errorInfo();
                error_log("Insert failed - SQL Error: " . print_r($errorInfo, true));
                return ['success' => false, 'message' => 'Lỗi khi tạo tài khoản: ' . ($errorInfo[2] ?? 'Unknown SQL error')];
            }
            
        } catch (Exception $e) {
            error_log("Registration error: " . $e->getMessage());
            error_log("Stack trace: " . $e->getTraceAsString());
            return ['success' => false, 'message' => 'Lỗi hệ thống: ' . $e->getMessage()];
        }
    }
    
    /**
     * User login
     */
    public static function login($email, $password) {
        try {
            // Find user by email
            $user = self::$db->findOne('users', ['email' => $email]);
            
            if (!$user) {
                return ['success' => false, 'message' => 'Email không tồn tại'];
            }
            
            // Check if user is active
            if ($user['status'] !== 'active') {
                return ['success' => false, 'message' => 'Tài khoản đã bị khóa'];
            }
            
            // Verify password
            if (!password_verify($password, $user['password'])) {
                return ['success' => false, 'message' => 'Mật khẩu không đúng'];
            }
            
            // Start session and set user data
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_email'] = $user['email'];
            $_SESSION['user_role'] = $user['role'];
            $_SESSION['user_name'] = $user['full_name'];
            $_SESSION['logged_in'] = true;
            
            // Generate CSRF token
            $_SESSION['csrf_token'] = self::generateCSRFToken();
            
            return [
                'success' => true, 
                'message' => 'Đăng nhập thành công',
                'user' => $user
            ];
            
        } catch (Exception $e) {
            error_log("Login error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Lỗi hệ thống'];
        }
    }
    
    /**
     * Create initial wallet for new user
     */
    private static function createInitialWallet($userId) {
        try {
            $pdo = self::$db->getPdo();
            
            // Create wallets table if not exists
            $sql = "
                CREATE TABLE IF NOT EXISTS wallets (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    user_id VARCHAR(50) NOT NULL,
                    balance INT DEFAULT 0,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    UNIQUE KEY unique_user (user_id),
                    INDEX idx_user (user_id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ";
            $pdo->exec($sql);
            
            // Insert wallet with 500 XU
            $sql = "INSERT INTO wallets (user_id, balance) VALUES (?, 500) 
                    ON DUPLICATE KEY UPDATE balance = 500";
            $pdo->prepare($sql)->execute([$userId]);
            
            // Create wallet transactions table if not exists
            $sql = "
                CREATE TABLE IF NOT EXISTS wallet_transactions (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    transaction_id VARCHAR(100) NOT NULL,
                    user_id VARCHAR(50) NOT NULL,
                    type ENUM('deposit', 'withdraw', 'system_gift', 'payment') NOT NULL,
                    amount_vnd DECIMAL(15,2) DEFAULT 0,
                    amount_xu INT NOT NULL,
                    exchange_rate DECIMAL(10,2) DEFAULT 100,
                    balance_before INT NOT NULL,
                    balance_after INT NOT NULL,
                    reference_id VARCHAR(100) NULL,
                    description TEXT,
                    status ENUM('pending', 'completed', 'failed') DEFAULT 'completed',
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    INDEX idx_user (user_id),
                    INDEX idx_transaction (transaction_id),
                    INDEX idx_type (type)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ";
            $pdo->exec($sql);
            
            // Log initial credit transaction
            $transactionId = 'WELCOME_' . $userId . '_' . time();
            $sql = "INSERT INTO wallet_transactions 
                    (transaction_id, user_id, type, amount_xu, balance_before, balance_after, description, status)
                    VALUES (?, ?, 'system_gift', 500, 0, 500, '🎁 Chào mừng thành viên mới - Tặng 500 XU', 'completed')";
            
            $pdo->prepare($sql)->execute([$transactionId, $userId]);
            
            error_log("Created wallet for user $userId with 500 XU");
            
        } catch (Exception $e) {
            error_log("Create wallet error for user $userId: " . $e->getMessage());
        }
    }
    
    /**
     * Get current user data
     */
    public static function getUser() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        if (!isset($_SESSION['user_id'])) {
            return null;
        }
        
        try {
            return self::$db->findOne('users', ['id' => $_SESSION['user_id']]);
        } catch (Exception $e) {
            error_log("Get user error: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Get user balance from users table
     */
    public static function getBalance($userId = null) {
        try {
            if (!$userId) {
                $userData = self::getUser();
                $userId = $userData['id'] ?? null;
            }
            
            if (!$userId) {
                return 0;
            }
            
            // Get balance directly from users table
            $userQuery = "SELECT balance FROM users WHERE id = ?";
            $result = self::$db->query($userQuery, [$userId]);
            
            return $result ? (float)($result[0]['balance'] ?? 0) : 0;
            
        } catch (Exception $e) {
            error_log("Get balance error: " . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * Update user balance
     */
    public static function updateBalance($userId, $newBalance) {
        try {
            return self::$db->update('users', 
                ['balance' => $newBalance], 
                ['id' => $userId]
            );
        } catch (Exception $e) {
            error_log("Update balance error for user $userId: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Check if user is logged in
     */
    public static function isLoggedIn() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        return isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
    }
    
    /**
     * Check if user is admin
     */
    public static function isAdmin() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        return isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin';
    }
    
    /**
     * Generate CSRF Token
     */
    public static function generateCSRFToken() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        $token = bin2hex(random_bytes(32));
        $_SESSION['csrf_token'] = $token;
        return $token;
    }
    
    /**
     * Verify CSRF Token
     */
    public static function verifyCSRFToken($token) {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
    }
    
    /**
     * Get CSRF Token
     */
    public static function getCSRFToken() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        if (!isset($_SESSION['csrf_token'])) {
            return self::generateCSRFToken();
        }
        
        return $_SESSION['csrf_token'];
    }
    
    /**
     * Logout user
     */
    public static function logout() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        session_unset();
        session_destroy();
        
        return ['success' => true, 'message' => 'Đăng xuất thành công'];
    }
    
    /**
     * Create users table if not exists
     */
    private static function createUsersTableIfNotExists() {
        try {
            $pdo = self::$db->getPdo();
            
            $sql = "
                CREATE TABLE IF NOT EXISTS users (
                    id VARCHAR(50) PRIMARY KEY,
                    email VARCHAR(255) UNIQUE NOT NULL,
                    password VARCHAR(255) NOT NULL,
                    full_name VARCHAR(255) NOT NULL,
                    phone VARCHAR(20) NULL,
                    role ENUM('user', 'admin') DEFAULT 'user',
                    status ENUM('active', 'inactive', 'banned') DEFAULT 'active',
                    balance DECIMAL(15,2) DEFAULT 500.00,
                    avatar VARCHAR(500) NULL,
                    address TEXT NULL,
                    email_verified TINYINT(1) DEFAULT 0,
                    last_login TIMESTAMP NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    INDEX idx_email (email),
                    INDEX idx_status (status)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ";
            
            $pdo->exec($sql);
            
        } catch (Exception $e) {
            error_log("Create users table error: " . $e->getMessage());
        }
    }
    
    /**
     * Create admin user if not exists
     */
    public static function createAdminIfNotExists() {
        try {
            // Check if admin exists
            $admin = self::$db->findOne('users', ['role' => 'admin']);
            
            if (!$admin) {
                // Create admin user
                $adminId = 'admin_' . uniqid();
                $adminPassword = password_hash('admin123', PASSWORD_DEFAULT);
                
                self::$db->insert('users', [
                    'id' => $adminId,
                    'email' => 'admin@aiboost.vn',
                    'password' => $adminPassword,
                    'full_name' => 'System Administrator',
                    'role' => 'admin',
                    'status' => 'active',
                    'balance' => 1000000,
                    'created_at' => date('Y-m-d H:i:s')
                ]);
                
                error_log("Admin user created: admin@aiboost.vn / admin123");
            }
            
        } catch (Exception $e) {
            error_log("Create admin error: " . $e->getMessage());
        }
    }
}

// Initialize Auth with database
if (isset($db)) {
    Auth::init($db);
    
    // Create users table and admin user if needed
    Auth::createAdminIfNotExists();
}

/**
 * Lấy thông tin user hiện tại từ session
 * @return array|null
 */
function getCurrentUser() {
    if (!isset($_SESSION['user_id'])) {
        return null;
    }
    
    return [
        'id' => $_SESSION['user_id'],
        'username' => $_SESSION['username'] ?? 'user',
        'email' => $_SESSION['email'] ?? '',
        'full_name' => $_SESSION['full_name'] ?? $_SESSION['username'] ?? 'User',
        'role' => $_SESSION['role'] ?? 'user'
    ];
}

/**
 * Kiểm tra user đã đăng nhập chưa
 * @return bool
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

/**
 * Đăng xuất user
 * @return void
 */
function logout() {
    // Xóa tất cả session variables
    $_SESSION = array();
    
    // Hủy session cookie
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    
    // Hủy session
    session_destroy();
}

/**
 * Đăng nhập user
 * @param array $userData
 * @return void
 */
function loginUser($userData) {
    $_SESSION['user_id'] = $userData['id'] ?? $userData['user_id'] ?? uniqid('user_');
    $_SESSION['username'] = $userData['username'] ?? $userData['email'] ?? 'user';
    $_SESSION['email'] = $userData['email'] ?? '';
    $_SESSION['full_name'] = $userData['full_name'] ?? $userData['name'] ?? $_SESSION['username'];
    $_SESSION['role'] = $userData['role'] ?? 'user';
    $_SESSION['login_time'] = time();
}

/**
 * Redirect nếu chưa đăng nhập
 * @param string $redirectTo
 * @return void
 */
function requireLogin($redirectTo = '/login') {
    if (!isLoggedIn()) {
        header('Location: ' . $redirectTo);
        exit();
    }
}

/**
 * Redirect nếu đã đăng nhập
 * @param string $redirectTo
 * @return void
 */
function requireGuest($redirectTo = '/dashboard') {
    if (isLoggedIn()) {
        header('Location: ' . $redirectTo);
        exit();
    }
}

/**
 * Kiểm tra quyền admin
 * @return bool
 */
function isAdmin() {
    $user = getCurrentUser();
    return $user && ($user['role'] === 'admin' || $user['role'] === 'super_admin');
}

/**
 * Require admin role
 * @param string $redirectTo
 * @return void
 */
function requireAdmin($redirectTo = '/') {
    if (!isAdmin()) {
        header('Location: ' . $redirectTo);
        exit();
    }
}

/**
 * Tạo CSRF token
 * @return string
 */
function generateCSRFToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Verify CSRF token
 * @param string $token
 * @return bool
 */
function verifyCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Get user avatar URL
 * @param array|null $user
 * @return string
 */
function getUserAvatar($user = null) {
    if (!$user) {
        $user = getCurrentUser();
    }
    
    if (!$user) {
        return '/assets/images/default-avatar.png';
    }
    
    // Nếu có avatar custom
    if (isset($user['avatar']) && !empty($user['avatar'])) {
        return $user['avatar'];
    }
    
    // Gravatar fallback
    $email = $user['email'] ?? '';
    if (!empty($email)) {
        $hash = md5(strtolower(trim($email)));
        return "https://www.gravatar.com/avatar/{$hash}?d=identicon&s=40";
    }
    
    return '/assets/images/default-avatar.png';
}

/**
 * Flash message system
 * @param string $key
 * @param string $message
 * @param string $type
 * @return void
 */
function setFlashMessage($key, $message, $type = 'info') {
    $_SESSION['flash_messages'][$key] = [
        'message' => $message,
        'type' => $type
    ];
}

/**
 * Get and clear flash message
 * @param string $key
 * @return array|null
 */
function getFlashMessage($key) {
    if (isset($_SESSION['flash_messages'][$key])) {
        $message = $_SESSION['flash_messages'][$key];
        unset($_SESSION['flash_messages'][$key]);
        return $message;
    }
    return null;
}

/**
 * Tạo demo user session để test
 * @return void
 */
function createDemoSession() {
    if (!isLoggedIn()) {
        loginUser([
            'id' => 'user-2025-uuid-001',
            'username' => 'demo_user',
            'email' => 'demo@aiboost.vn',
            'full_name' => 'Demo User',
            'role' => 'user'
        ]);
    }
}
?>