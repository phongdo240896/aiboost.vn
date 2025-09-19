<?php
require_once __DIR__ . '/app/config.php';
require_once __DIR__ . '/app/db.php';
require_once __DIR__ . '/app/PromotionManager.php';

$promotionManager = new PromotionManager($db);

echo "=== DEBUG KHUYẾN MÃI ===\n\n";

// Test với số tiền 15,000
$testAmount = 15000;
echo "Test với số tiền: " . number_format($testAmount) . "đ\n\n";

// 1. Kiểm tra khuyến mãi có tồn tại không
echo "1. Kiểm tra khuyến mãi trong database:\n";
$allPromotions = $db->query("SELECT * FROM promotions ORDER BY created_at DESC");
if (empty($allPromotions)) {
    echo "❌ Không có khuyến mãi nào trong database!\n";
    echo "Cần tạo khuyến mãi trước:\n";
    echo "INSERT INTO promotions (name, type, value, min_deposit, start_date, end_date, status) 
VALUES ('Test 20%', 'percentage', 20, 10000, NOW(), DATE_ADD(NOW(), INTERVAL 30 DAY), 'active');\n";
    exit;
} else {
    foreach ($allPromotions as $p) {
        echo "- ID: {$p['id']}, Name: {$p['name']}, Value: {$p['value']}, Status: {$p['status']}\n";
        echo "  Min: " . number_format($p['min_deposit']) . "đ, Start: {$p['start_date']}, End: {$p['end_date']}\n";
    }
}

echo "\n2. Tìm khuyến mãi phù hợp cho {$testAmount}đ:\n";
$activePromotion = $promotionManager->getActivePromotion($testAmount);
if (!$activePromotion) {
    echo "❌ Không tìm thấy khuyến mãi phù hợp!\n";
    
    // Debug thêm
    echo "\nDebug SQL query:\n";
    $debugPromotions = $db->query("
        SELECT *, 
               (start_date <= NOW()) as start_valid,
               (end_date >= NOW()) as end_valid,
               (min_deposit <= ?) as min_valid
        FROM promotions 
        WHERE status = 'active'
    ", [$testAmount]);
    
    foreach ($debugPromotions as $dp) {
        echo "- {$dp['name']}: start_valid={$dp['start_valid']}, end_valid={$dp['end_valid']}, min_valid={$dp['min_valid']}\n";
    }
} else {
    echo "✅ Tìm thấy khuyến mãi: {$activePromotion['name']}\n";
    
    echo "\n3. Tính toán bonus:\n";
    $bonus = $promotionManager->calculateBonus($activePromotion, $testAmount);
    echo "Bonus amount: {$bonus} VND\n";
    
    // Test apply promotion (dry run)
    echo "\n4. Test áp dụng khuyến mãi:\n";
    echo "Sẽ không thực sự áp dụng, chỉ test logic...\n";
}

echo "\n=== KẾT THÚC DEBUG ===\n";
?>