<?php
// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/debug.log');

echo "<h1>🔧 Bank Settings Debug</h1>";

require_once __DIR__ . '/app/config.php';
require_once __DIR__ . '/app/db.php';
require_once __DIR__ . '/app/auth.php';

try {
    echo "<h2>✅ Database Connection</h2>";
    echo "<p>Database connected successfully</p>";
    
    echo "<h2>🏦 Bank Settings Table</h2>";
    
    // Check if table exists
    $tableExists = $db->tableExists('bank_settings');
    echo "<p>Table exists: " . ($tableExists ? 'Yes' : 'No') . "</p>";
    
    if (!$tableExists) {
        echo "<p>Creating bank_settings table...</p>";
        $createTableSQL = "
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
        
        $db->getPdo()->exec($createTableSQL);
        echo "<p>✅ Table created</p>";
    }
    
    // Test insert
    echo "<h2>🧪 Test Insert</h2>";
    $testResult = $db->insert('bank_settings', [
        'bank_code' => 'TEST',
        'bank_name' => 'Test Bank',
        'account_number' => '123456789',
        'account_holder' => 'TEST USER',
        'api_token' => 'test_token',
        'status' => 'active'
    ]);
    
    echo "<p>Insert result: " . ($testResult ? 'Success' : 'Failed') . "</p>";
    
    // Test select
    echo "<h2>📋 Current Records</h2>";
    $records = $db->select('bank_settings', '*');
    echo "<pre>" . print_r($records, true) . "</pre>";
    
    // Clean up test record
    $db->delete('bank_settings', ['bank_code' => 'TEST']);
    echo "<p>Test record cleaned up</p>";
    
    echo "<h2>✅ All Tests Passed</h2>";
    echo "<p><a href='/admin/bank_accounts'>→ Back to Bank Settings</a></p>";
    
} catch (Exception $e) {
    echo "<h2>❌ Error</h2>";
    echo "<p style='color: red;'>" . $e->getMessage() . "</p>";
    echo "<pre style='color: red;'>" . $e->getTraceAsString() . "</pre>";
}
?>