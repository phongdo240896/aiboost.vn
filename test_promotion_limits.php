<?php
require_once __DIR__ . '/app/config.php';
require_once __DIR__ . '/app/db.php';
require_once __DIR__ . '/app/PromotionManager.php';

$promotionManager = new PromotionManager($db);
$testUserId = 'user_1758016603_68c9345b08259'; // User test

echo "=== TEST GIỚI HẠN KHUYẾN MÃI ===\n\n";

// 1. Tạo khuyến mãi test với giới hạn 1 lần per user
echo "1. Tạo khuyến mãi test với giới hạn 1 lần mỗi user...\n";
$db->query("
    INSERT INTO promotions (name, type, value, min_deposit, start_date, end_date, status, usage_limit_per_user) 
    VALUES ('Test Limit 1 User', 'percentage', 10, 5000, NOW(), DATE_ADD(NOW(), INTERVAL 30 DAY), 'active', 1)
    ON DUPLICATE KEY UPDATE name = VALUES(name)
");

$testPromotion = $db->query("SELECT * FROM promotions WHERE name = 'Test Limit 1 User' LIMIT 1");
if (empty($testPromotion)) {
    echo "❌ Không tạo được khuyến mãi test!\n";
    exit;
}

$promoId = $testPromotion[0]['id'];
echo "✅ Tạo thành công khuyến mãi ID: {$promoId}\n\n";

// 2. Kiểm tra user có thể sử dụng không (lần đầu)
echo "2. Kiểm tra lần đầu sử dụng:\n";
$canUse = $promotionManager->canUserUseSpecificPromotion($testUserId, $promoId);
echo $canUse ? "✅ User có thể sử dụng khuyến mãi\n" : "❌ User không thể sử dụng khuyến mãi\n";

$usageCount = $promotionManager->getUserPromotionUsageCount($testUserId, $promoId);
echo "Số lần đã sử dụng: {$usageCount}\n\n";

// 3. Giả lập việc user sử dụng khuyến mãi lần đầu
echo "3. Giả lập sử dụng khuyến mãi lần đầu:\n";
$db->query("
    INSERT INTO promotion_usage (user_id, promotion_id, transaction_id, deposit_amount, bonus_amount, bonus_xu) 
    VALUES (?, ?, 'TEST_001', 10000, 1000, 10)
", [$testUserId, $promoId]);
echo "✅ Đã ghi nhận sử dụng khuyến mãi\n\n";

// 4. Kiểm tra lại sau khi đã sử dụng 1 lần
echo "4. Kiểm tra sau khi đã sử dụng 1 lần:\n";
$canUse = $promotionManager->canUserUseSpecificPromotion($testUserId, $promoId);
echo $canUse ? "✅ User vẫn có thể sử dụng khuyến mãi" : "❌ User không thể sử dụng khuyến mãi (đã hết lượt)\n";

$usageCount = $promotionManager->getUserPromotionUsageCount($testUserId, $promoId);
echo "Số lần đã sử dụng: {$usageCount}\n\n";

// 5. Test với user khác
$testUserId2 = 'user_1757830742_3917';
echo "5. Test với user khác ({$testUserId2}):\n";
$canUse = $promotionManager->canUserUseSpecificPromotion($testUserId2, $promoId);
echo $canUse ? "✅ User 2 có thể sử dụng khuyến mãi" : "❌ User 2 không thể sử dụng khuyến mãi\n";

$usageCount = $promotionManager->getUserPromotionUsageCount($testUserId2, $promoId);
echo "User 2 số lần đã sử dụng: {$usageCount}\n\n";

// 6. Cleanup
echo "6. Dọn dẹp dữ liệu test:\n";
$db->query("DELETE FROM promotion_usage WHERE transaction_id = 'TEST_001'");
$db->query("DELETE FROM promotions WHERE name = 'Test Limit 1 User'");
echo "✅ Đã dọn dẹp xong\n\n";

echo "=== KẾT THÚC TEST ===\n";
?>