<?php
require_once __DIR__ . '/app/config.php';
require_once __DIR__ . '/app/db.php';

echo "<h1>🔄 Reset Admin Password</h1>";

try {
    // Hash password mới
    $newPassword = 'admin123';
    $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
    
    echo "<p>New password: <strong>$newPassword</strong></p>";
    echo "<p>New hash: <strong>$hashedPassword</strong></p>";
    
    // Update password
    $result = $db->update('users', 
        ['password' => $hashedPassword], 
        ['email' => 'admin@aiboost.vn']
    );
    
    if ($result) {
        echo "<p>✅ Password updated successfully!</p>";
        
        // Test login
        $admin = $db->findOne('users', ['email' => 'admin@aiboost.vn']);
        if ($admin && password_verify($newPassword, $admin['password'])) {
            echo "<p>✅ Password verification test: PASSED</p>";
        } else {
            echo "<p>❌ Password verification test: FAILED</p>";
        }
        
    } else {
        echo "<p>❌ Failed to update password</p>";
    }
    
} catch (Exception $e) {
    echo "<p>❌ Error: " . $e->getMessage() . "</p>";
}

echo '<p><a href="login.php">Test Login Page</a></p>';
?>