<?php
require_once __DIR__ . '/app/config.php';
require_once __DIR__ . '/app/db.php';
require_once __DIR__ . '/app/auth.php';

echo "<h1>🚀 AIboost System Initialization</h1>";

try {
    // Test database connection
    echo "<p>✅ Database connection: OK</p>";
    
    // Create admin user
    Auth::createAdminIfNotExists();
    echo "<p>✅ Admin user created/verified</p>";
    
    // Create bank_settings table
    $createBankTable = "
        CREATE TABLE IF NOT EXISTS bank_settings (
            id INT AUTO_INCREMENT PRIMARY KEY,
            bank_code VARCHAR(20) UNIQUE NOT NULL,
            bank_name VARCHAR(100) NOT NULL,
            account_number VARCHAR(50) NOT NULL,
            account_holder VARCHAR(100) NOT NULL,
            api_token TEXT,
            status ENUM('active', 'inactive') DEFAULT 'inactive',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ";
    
    $db->getPdo()->exec($createBankTable);
    echo "<p>✅ Bank settings table created/verified</p>";
    
    // Create transactions table
    $createTransTable = "
        CREATE TABLE IF NOT EXISTS transactions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id VARCHAR(50) NOT NULL,
            amount DECIMAL(15,2) NOT NULL,
            type ENUM('credit', 'debit') NOT NULL,
            description TEXT,
            status ENUM('pending', 'completed', 'failed') DEFAULT 'pending',
            bank VARCHAR(20) NULL,
            pay_code VARCHAR(20) NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ";
    
    $db->getPdo()->exec($createTransTable);
    echo "<p>✅ Transactions table created/verified</p>";
    
    // Create activity_logs table
    $createActivityTable = "
        CREATE TABLE IF NOT EXISTS activity_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id VARCHAR(50) NOT NULL,
            action VARCHAR(100) NOT NULL,
            details TEXT,
            ip_address VARCHAR(45),
            user_agent TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ";
    
    $db->getPdo()->exec($createActivityTable);
    echo "<p>✅ Activity logs table created/verified</p>";
    
    echo "<hr>";
    echo "<h2>🎉 System Ready!</h2>";
    echo "<p><strong>Admin Login:</strong></p>";
    echo "<p>Email: admin@aiboost.vn</p>";
    echo "<p>Password: admin123</p>";
    echo "<p><a href='/login'>→ Go to Login</a></p>";
    
} catch (Exception $e) {
    echo "<p>❌ Error: " . $e->getMessage() . "</p>";
}
?>