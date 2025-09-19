<?php
require_once __DIR__ . '/app/db.php';

echo "<h1>🔌 Test MySQL Connection</h1>";

try {
    // Test connection
    $pdo = $db->getPdo();
    echo "<p>✅ MySQL connection successful!</p>";
    
    // Test users table
    $users = $db->select('users', '*', null, null, 5);
    echo "<p>✅ Users found: " . count($users) . "</p>";
    
    foreach ($users as $user) {
        echo "<p>• {$user['email']} - {$user['full_name']} ({$user['role']})</p>";
    }
    
    // Test wallet
    if (count($users) > 0) {
        $balance = $wallet->getBalance($users[0]['id']);
        echo "<p>✅ Wallet balance for {$users[0]['email']}: " . number_format($balance) . "₫</p>";
    }
    
} catch (Exception $e) {
    echo "<p>❌ Error: " . $e->getMessage() . "</p>";
}
?>