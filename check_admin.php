<?php
require_once __DIR__ . '/app/config.php';
require_once __DIR__ . '/app/db.php';

echo "<h1>🔍 Kiểm tra Admin Account</h1>";

try {
    // Tìm admin user
    $admin = $db->findOne('users', ['email' => 'admin@aiboost.vn']);
    
    if ($admin) {
        echo "<h2>✅ Admin account found:</h2>";
        echo "<p><strong>Email:</strong> " . $admin['email'] . "</p>";
        echo "<p><strong>Name:</strong> " . $admin['full_name'] . "</p>";
        echo "<p><strong>Role:</strong> " . $admin['role'] . "</p>";
        echo "<p><strong>Status:</strong> " . $admin['status'] . "</p>";
        echo "<p><strong>Password Hash:</strong> " . substr($admin['password'], 0, 50) . "...</p>";
        
        // Test password verification
        echo "<h2>🔐 Password Tests:</h2>";
        
        $passwords = ['admin123', 'Admin123', 'ADMIN123', 'admin', '123456'];
        
        foreach ($passwords as $testPassword) {
            $isValid = password_verify($testPassword, $admin['password']);
            echo "<p><strong>'{$testPassword}':</strong> " . ($isValid ? "✅ ĐÚNG" : "❌ SAI") . "</p>";
        }
        
        // Show correct hash for admin123
        echo "<h2>📝 Correct hash for 'admin123':</h2>";
        echo "<p>" . password_hash('admin123', PASSWORD_DEFAULT) . "</p>";
        
    } else {
        echo "<p>❌ Admin account NOT found!</p>";
        
        echo "<h2>📋 All users in database:</h2>";
        $users = $db->select('users');
        foreach ($users as $user) {
            echo "<p>• {$user['email']} - {$user['full_name']} ({$user['role']})</p>";
        }
    }
    
} catch (Exception $e) {
    echo "<p>❌ Error: " . $e->getMessage() . "</p>";
}
?>