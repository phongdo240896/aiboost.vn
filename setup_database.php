<?php
// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>🚀 Setup Database AIboost.vn</h1>";

try {
    echo "<h2>📋 Loading dependencies...</h2>";
    
    // Check if files exist before requiring
    $requiredFiles = [
        __DIR__ . '/app/config.php',
        __DIR__ . '/app/db.php',
        __DIR__ . '/app/models/Plan.php',
        __DIR__ . '/app/models/Subscription.php',
        __DIR__ . '/app/controllers/SubscriptionController.php'
    ];
    
    foreach ($requiredFiles as $file) {
        if (!file_exists($file)) {
            throw new Exception("File không tồn tại: " . $file);
        }
        echo "<p>✅ Found: " . basename($file) . "</p>";
    }
    
    // Require files
    require_once __DIR__ . '/app/config.php';
    require_once __DIR__ . '/app/db.php';
    require_once __DIR__ . '/app/models/Plan.php';
    require_once __DIR__ . '/app/models/Subscription.php';
    require_once __DIR__ . '/app/controllers/SubscriptionController.php';
    
    echo "<p>✅ All files loaded successfully</p>";
    
    echo "<h2>📋 Tạo các bảng...</h2>";
    
    // Test database connection
    echo "<p>🔧 Testing database connection...</p>";
    $pdo = $db->getPdo();
    echo "<p>✅ Database connected</p>";
    
    // 1. Tạo bảng subscription_plans
    echo "<p>🔧 Tạo bảng subscription_plans...</p>";
    
    // Check if method exists
    if (!method_exists('Plan', 'createTableIfNotExists')) {
        throw new Exception("Method Plan::createTableIfNotExists() không tồn tại");
    }
    
    Plan::createTableIfNotExists();
    echo "<p>✅ subscription_plans OK</p>";
    
    // 2. Tạo bảng subscriptions
    echo "<p>🔧 Tạo bảng subscriptions...</p>";
    
    if (!method_exists('Subscription', 'createTableIfNotExists')) {
        throw new Exception("Method Subscription::createTableIfNotExists() không tồn tại");
    }
    
    Subscription::createTableIfNotExists();
    echo "<p>✅ subscriptions OK</p>";
    
    // 3. Tạo bảng wallets
    echo "<p>🔧 Tạo bảng wallets...</p>";
    $walletSQL = "
        CREATE TABLE IF NOT EXISTS wallets (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id VARCHAR(50) UNIQUE NOT NULL,
            balance DECIMAL(15,2) DEFAULT 0.00,
            credits INT DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_user_id (user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ";
    $db->getPdo()->exec($walletSQL);
    echo "<p>✅ wallets OK</p>";
    
    // 4. Tạo bảng credit_transactions
    echo "<p>🔧 Tạo bảng credit_transactions...</p>";
    $transactionSQL = "
        CREATE TABLE IF NOT EXISTS credit_transactions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id VARCHAR(50) NOT NULL,
            type ENUM('credit', 'debit') NOT NULL,
            amount INT NOT NULL,
            description TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_user_id (user_id),
            INDEX idx_type (type),
            INDEX idx_created_at (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ";
    $db->getPdo()->exec($transactionSQL);
    echo "<p>✅ credit_transactions OK</p>";
    
    // 5. Seed sample data
    echo "<p>🌱 Tạo dữ liệu mẫu...</p>";
    
    if (!method_exists('Plan', 'seedSampleData')) {
        throw new Exception("Method Plan::seedSampleData() không tồn tại");
    }
    
    Plan::seedSampleData();
    echo "<p>✅ Sample data OK</p>";
    
    // 6. Test getAvailablePlans
    echo "<h2>🧪 Test API...</h2>";
    
    if (!method_exists('SubscriptionController', 'getAvailablePlans')) {
        throw new Exception("Method SubscriptionController::getAvailablePlans() không tồn tại");
    }
    
    $plansResult = SubscriptionController::getAvailablePlans();
    
    if ($plansResult['success']) {
        echo "<p>✅ getAvailablePlans() hoạt động: " . count($plansResult['data']) . " gói</p>";
        
        echo "<h3>📦 Danh sách gói:</h3>";
        foreach ($plansResult['data'] as $plan) {
            echo "<div style='border: 1px solid #ddd; padding: 10px; margin: 5px; border-radius: 5px;'>";
            echo "<strong>{$plan['name']}</strong><br>";
            echo "Giá: " . number_format($plan['price']) . "đ<br>";
            echo "Credits: " . number_format($plan['credits']) . "<br>";
            echo "Thời hạn: {$plan['duration_days']} ngày<br>";
            echo "Recommended: " . ($plan['is_recommended'] ? 'Có' : 'Không') . "<br>";
            echo "</div>";
        }
    } else {
        echo "<p>❌ Lỗi getAvailablePlans(): " . $plansResult['message'] . "</p>";
    }
    
    echo "<h2>🎉 Setup hoàn tất!</h2>";
    echo "<p><a href='/pricing.php' style='background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>→ Xem Pricing Page</a></p>";
    
} catch (Exception $e) {
    echo "<h2>❌ Lỗi Setup</h2>";
    echo "<p style='color: red;'><strong>Error:</strong> " . $e->getMessage() . "</p>";
    echo "<p style='color: red;'><strong>File:</strong> " . $e->getFile() . "</p>";
    echo "<p style='color: red;'><strong>Line:</strong> " . $e->getLine() . "</p>";
    echo "<pre style='background: #f8f8f8; padding: 10px; font-size: 12px;'>" . $e->getTraceAsString() . "</pre>";
    
    echo "<h3>🔍 Debug Info:</h3>";
    echo "<p><strong>Current directory:</strong> " . __DIR__ . "</p>";
    echo "<p><strong>PHP version:</strong> " . PHP_VERSION . "</p>";
    
    echo "<h3>📁 File structure check:</h3>";
    $checkDirs = ['app', 'app/models', 'app/controllers'];
    foreach ($checkDirs as $dir) {
        $fullPath = __DIR__ . '/' . $dir;
        echo "<p><strong>{$dir}:</strong> " . (is_dir($fullPath) ? "✅ Exists" : "❌ Missing") . "</p>";
    }
}
?>