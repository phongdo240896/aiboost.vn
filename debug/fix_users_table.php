<?php
require_once __DIR__ . '/../app/config.php';
require_once __DIR__ . '/../app/db.php';

echo "<h2>🔧 SỬA BẢNG USERS</h2>";

try {
    $pdo = $db->getPdo();
    
    // Kiểm tra cột balance có tồn tại không
    $columns = $pdo->query("SHOW COLUMNS FROM users LIKE 'balance'")->fetchAll();
    
    if (empty($columns)) {
        echo "<p>⚠️ Cột 'balance' chưa có - Đang thêm...</p>";
        
        // Thêm cột balance
        $pdo->exec("ALTER TABLE users ADD COLUMN balance DECIMAL(15,2) DEFAULT 500.00 AFTER status");
        echo "<p>✅ Đã thêm cột 'balance'</p>";
    } else {
        echo "<p>✅ Cột 'balance' đã tồn tại</p>";
    }
    
    // Kiểm tra lại cấu trúc bảng
    echo "<h3>Cấu trúc bảng users sau khi sửa:</h3>";
    $columns = $pdo->query("DESCRIBE users")->fetchAll();
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr style='background: #f5f5f5;'><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th></tr>";
    foreach ($columns as $col) {
        echo "<tr>";
        echo "<td>{$col['Field']}</td>";
        echo "<td>{$col['Type']}</td>";
        echo "<td>{$col['Null']}</td>";
        echo "<td>{$col['Key']}</td>";
        echo "<td>{$col['Default']}</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    // Test đăng ký lại
    echo "<h3>🧪 TEST ĐĂNG KÝ SAU KHI SỬA:</h3>";
    echo "<form method='POST'>";
    echo "<button type='submit' name='test_register' style='background: #007cba; color: white; padding: 10px 20px; border: none; border-radius: 5px;'>Test Đăng Ký</button>";
    echo "</form>";
    
    if (isset($_POST['test_register'])) {
        $testUserId = 'test_' . time();
        $testEmail = 'test_' . time() . '@example.com';
        
        echo "<div style='background: #fff3cd; padding: 10px; border-radius: 5px; margin: 10px 0;'>";
        echo "<strong>Đang test với dữ liệu:</strong><br>";
        echo "ID: $testUserId<br>";
        echo "Email: $testEmail<br>";
        echo "Password: 123456<br>";
        echo "Full Name: Test User<br>";
        echo "Phone: 0901234567<br>";
        echo "</div>";
        
        try {
            $sql = "INSERT INTO users (id, email, password, full_name, phone, role, status, balance) 
                    VALUES (?, ?, ?, ?, ?, 'user', 'active', 500.00)";
            
            $stmt = $pdo->prepare($sql);
            $result = $stmt->execute([
                $testUserId,
                $testEmail,
                password_hash('123456', PASSWORD_DEFAULT),
                'Test User',
                '0901234567'
            ]);
            
            if ($result) {
                echo "<div style='background: #d4edda; padding: 10px; border-radius: 5px;'>";
                echo "✅ Test đăng ký thành công!<br>";
                echo "🎉 Có thể đăng ký user mới bình thường rồi!";
                echo "</div>";
            } else {
                echo "<div style='background: #f8d7da; padding: 10px; border-radius: 5px;'>";
                echo "❌ Test đăng ký vẫn thất bại!";
                echo "</div>";
            }
            
        } catch (Exception $e) {
            echo "<div style='background: #f8d7da; padding: 10px; border-radius: 5px;'>";
            echo "❌ Lỗi test: " . $e->getMessage();
            echo "</div>";
        }
    }
    
} catch (Exception $e) {
    echo "<p>❌ Lỗi: " . $e->getMessage() . "</p>";
}
?>
<br>
<a href="/register.php" style="background: #28a745; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;">🔙 Quay lại đăng ký</a>