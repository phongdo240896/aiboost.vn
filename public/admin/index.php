<?php
// test.php

// Bắt đầu session
session_start();

// Kiểm tra kết nối MySQL
$host = "localhost";     // Trên cPanel sẽ là localhost
$user = "root";          // Trên cPanel: username của DB
$pass = "";              // Trên cPanel: password DB
$db   = "aiboost";       // Tên database (đổi theo anh tạo)

$conn = new mysqli($host, $user, $pass, $db);

if ($conn->connect_error) {
    die("❌ Kết nối thất bại: " . $conn->connect_error);
} else {
    echo "✅ Kết nối DB thành công!<br>";
}

// Demo login giả lập
if (!isset($_SESSION['user'])) {
    $_SESSION['user'] = "demo_user";
    echo "🔑 Đăng nhập giả lập thành công, user = " . $_SESSION['user'] . "<br>";
} else {
    echo "👋 Xin chào, " . $_SESSION['user'] . "<br>";
    echo "<a href='?logout=1'>Đăng xuất</a>";
}

// Đăng xuất
if (isset($_GET['logout'])) {
    session_destroy();
    echo "<br>🚪 Đã logout!";
}
?>
