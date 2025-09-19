<?php
echo "<!DOCTYPE html>";
echo "<html lang='vi'>";
echo "<head>";
echo "<meta charset='UTF-8'>";
echo "<title>Test AIBOOST.VN</title>";
echo "<style>body{font-family:Arial;margin:20px;} .ok{color:green;} .error{color:red;}</style>";
echo "</head>";
echo "<body>";

echo "<h1>🧪 Test AIBOOST.VN</h1>";
echo "<p>Thời gian: " . date('Y-m-d H:i:s') . "</p>";

// Test 1: PHP Version
echo "<h2>📋 Thông tin hệ thống</h2>";
echo "<p class='ok'>✅ PHP Version: " . phpversion() . "</p>";
echo "<p class='ok'>✅ Server: " . ($_SERVER['SERVER_SOFTWARE'] ?? 'PHP Built-in Server') . "</p>";
echo "<p class='ok'>✅ Document Root: " . __DIR__ . "</p>";

// Test 2: File structure
echo "<h2>📁 Cấu trúc thư mục</h2>";
$files = [
    'public/index.php',
    'app/config.php', 
    'app/db.php'
];

foreach ($files as $file) {
    if (file_exists($file)) {
        echo "<p class='ok'>✅ $file - Tồn tại</p>";
    } else {
        echo "<p class='error'>❌ $file - Không tồn tại</p>";
    }
}

// Test 3: Extensions
echo "<h2>🔧 PHP Extensions</h2>";
$extensions = ['curl', 'json', 'mbstring', 'openssl'];
foreach ($extensions as $ext) {
    if (extension_loaded($ext)) {
        echo "<p class='ok'>✅ $ext</p>";
    } else {
        echo "<p class='error'>❌ $ext</p>";
    }
}

// Test 4: Config test (nếu file tồn tại)
echo "<h2>⚙️ Config Test</h2>";
if (file_exists('app/config.php')) {
    try {
        require_once 'app/config.php';
        echo "<p class='ok'>✅ Config loaded thành công</p>";
        if (defined('SUPABASE_URL')) {
            echo "<p class='ok'>✅ Supabase URL configured</p>";
        } else {
            echo "<p class='error'>❌ Supabase URL chưa configured</p>";
        }
    } catch (Exception $e) {
        echo "<p class='error'>❌ Config Error: " . $e->getMessage() . "</p>";
    }
} else {
    echo "<p class='error'>❌ app/config.php không tồn tại</p>";
}

echo "<hr>";
echo "<p><strong>✅ Test hoàn thành!</strong></p>";
echo "<p><a href='/'>← Về trang chủ</a></p>";
echo "</body></html>";
?>