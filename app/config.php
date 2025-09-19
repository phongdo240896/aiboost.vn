<?php
// Detect environment
$isLocal = (strpos($_SERVER['HTTP_HOST'], 'localhost') !== false || 
           strpos($_SERVER['HTTP_HOST'], '127.0.0.1') !== false);

// Cấu hình Database MySQL
if ($isLocal) {
    // Local development
    define('DB_HOST', 'localhost');
    define('DB_NAME', 'aiboost_local');
    define('DB_USER', 'root');
    define('DB_PASS', '');
    define('DB_CHARSET', 'utf8mb4');
    
    define('APP_URL', 'http://localhost:8000');
    define('APP_ENV', 'development');
    // Show errors for development
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    // Production hosting - KIỂM TRA PASSWORD
    define('DB_HOST', 'localhost');
    define('DB_NAME', 'sdvybfld_aiboost.vn');
    define('DB_USER', 'sdvybfld_aiboost');
    define('DB_PASS', 'aiboost@123'); // Hoặc 'aiboost@123'
    define('DB_CHARSET', 'utf8mb4');
    
    define('APP_URL', 'https://aiboost.vn');
    define('APP_ENV', 'production');
    error_reporting(E_ALL); // Tạm thời bật để debug
    ini_set('display_errors', 1);
}

// Cấu hình Supabase - SỬA LẠI API KEY
define('SUPABASE_URL', 'https://krozfkdsuepvilnchcub.supabase.co');
define('SUPABASE_ANON_KEY', 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6Imtyb3pma2RzdWVwdmlsbmNoY3ViIiwicm9sZSI6ImFub24iLCJpYXQiOjE3NTc2OTY0MTYsImV4cCI6MjA3MzI3MjQxNn0.mhKKh9JPGkrR2eNTYeOn3zRXwEU7WFvJDQCz_8uvSaI');
define('SUPABASE_SERVICE_ROLE_KEY', 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6Imtyb3pma2RzdWVwdmlsbmNoY3ViIiwicm9zZSI6InNlcnZpY2Vfcm9sZSIsImlhdCI6MTc1NzY5NjQxNiwiZXhwIjoyMDczMjcyNDE2fQ.9mZT0kMvoD39infJkgSE8BTLlmwOHh0Hr9HuG_lLT4E');

// Cấu hình ứng dụng
define('APP_NAME', 'AIboost.vn');
define('APP_VERSION', '1.0.0');

// Cấu hình session
define('SESSION_TIMEOUT', 3600); // 1 giờ
define('REMEMBER_ME_TIMEOUT', 2592000); // 30 ngày

// Cấu hình bảo mật
define('ENCRYPTION_KEY', 'aiboost-32-char-secret-key-2025');
define('JWT_SECRET', 'aiboost-jwt-secret-key-production');
define('PASSWORD_SALT', 'aiboost-password-salt-secure');

// Cấu hình thanh toán
define('PAYMENT_GATEWAY', 'vnpay');
define('VNPAY_TMN_CODE', getenv('VNPAY_TMN_CODE') ?: 'your-vnpay-tmn-code');
define('VNPAY_HASH_SECRET', getenv('VNPAY_HASH_SECRET') ?: 'your-vnpay-hash-secret');

// Cấu hình email
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USERNAME', getenv('SMTP_USERNAME') ?: 'noreply@vocash.vn');
define('SMTP_PASSWORD', getenv('SMTP_PASSWORD') ?: 'your-app-password');
define('FROM_EMAIL', 'noreply@vocash.vn');
define('FROM_NAME', 'AIboost.vn');

// Múi giờ
date_default_timezone_set('Asia/Ho_Chi_Minh');

// Helper functions
function getBaseUrl() {
    return APP_URL;
}

function asset($path) {
    return APP_URL . '/public/' . ltrim($path, '/');
}

/**
 * URL helper function - SỬA LẠI
 */
function url($path = '') {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    
    // Xử lý path
    $path = ltrim($path, '/');
    if (!empty($path)) {
        $path = '/' . $path;
    }
    
    return $protocol . $host . $path;
}

// Đảm bảo autoload các model và controller
function autoloadClasses($className) {
    $paths = [
        __DIR__ . '/models/' . $className . '.php',
        __DIR__ . '/controllers/' . $className . '.php',
    ];
    
    foreach ($paths as $path) {
        if (file_exists($path)) {
            require_once $path;
            return;
        }
    }
}

spl_autoload_register('autoloadClasses');
?>