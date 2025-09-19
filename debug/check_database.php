<?php
require_once __DIR__ . '/../app/config.php';
require_once __DIR__ . '/../app/db.php';

echo "<h2>🔍 KIỂM TRA DATABASE</h2>";

try {
    // 1. Kiểm tra kết nối database
    $pdo = $db->getPdo();
    echo "<p>✅ Kết nối database thành công</p>";
    
    // 2. Kiểm tra bảng users
    $tables = $pdo->query("SHOW TABLES LIKE 'users'")->fetchAll();
    if (empty($tables)) {
        echo "<p>❌ Bảng 'users' chưa tồn tại - Đang tạo...</p>";
        
        // Tạo bảng users
        $sql = "
            CREATE TABLE users (
                id VARCHAR(50) PRIMARY KEY,
                email VARCHAR(255) UNIQUE NOT NULL,
                password VARCHAR(255) NOT NULL,
                full_name VARCHAR(255) NOT NULL,
                phone VARCHAR(20) NULL,
                role ENUM('user', 'admin') DEFAULT 'user',
                status ENUM('active', 'inactive', 'banned') DEFAULT 'active',
                balance DECIMAL(15,2) DEFAULT 500.00,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ";
        
        $pdo->exec($sql);
        echo "<p>✅ Đã tạo bảng 'users'</p>";
    } else {
        echo "<p>✅ Bảng 'users' đã tồn tại</p>";
        
        // Hiển thị cấu trúc bảng
        $columns = $pdo->query("DESCRIBE users")->fetchAll();
        echo "<h3>Cấu trúc bảng users:</h3>";
        echo "<table border='1' style='border-collapse: collapse;'>";
        echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th></tr>";
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
    }
    
    // 3. Test insert
    echo "<h3>🧪 TEST ĐĂNG KÝ:</h3>";
    echo "<form method='POST'>";
    echo "<button type='submit' name='test_register'>Test Đăng Ký</button>";
    echo "</form>";
    
    if (isset($_POST['test_register'])) {
        $testUserId = 'test_' . time();
        $testEmail = 'test_' . time() . '@example.com';
        
        $sql = "INSERT INTO users (id, email, password, full_name, phone, role, status, balance) 
                VALUES (?, ?, ?, ?, ?, 'user', 'active', 500)";
        
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
            echo "ID: $testUserId<br>";
            echo "Email: $testEmail<br>";
            echo "Password: 123456";
            echo "</div>";
        } else {
            echo "<div style='background: #f8d7da; padding: 10px; border-radius: 5px;'>";
            echo "❌ Test đăng ký thất bại!";
            echo "</div>";
        }
    }
    
} catch (Exception $e) {
    echo "<p>❌ Lỗi database: " . $e->getMessage() . "</p>";
}
?>