<?php
require_once __DIR__ . '/app/config.php';
require_once __DIR__ . '/app/db.php';
require_once __DIR__ . '/app/WalletManager.php';

$walletManager = new WalletManager($db);
$testUserId = 'user_1758016603_68c9345b08259';

echo "=== TEST THỰC TẾ KHUYẾN MÃI CÓ GIỚI HẠN ===\n\n";

// Tạo khuyến mãi thực với giới hạn 2 lần mỗi user
$db->query("
    INSERT INTO promotions (name, type, value, min_deposit, start_date, end_date, status, usage_limit_per_user) 
    VALUES ('Khuyến mãi 15% - Giới hạn 2 lần', 'percentage', 15, 10000, NOW(), DATE_ADD(NOW(), INTERVAL 7 DAY), 'active', 2)
    ON DUPLICATE KEY UPDATE 
        start_date = NOW(), 
        end_date = DATE_ADD(NOW(), INTERVAL 7 DAY),
        status = 'active'
");

echo "✅ Đã tạo khuyến mãi: 15% bonus, tối thiểu 10,000đ, giới hạn 2 lần/user\n\n";

// Test nạp lần 1
echo "🔸 Lần 1: Nạp 15,000đ\n";
$result1 = $walletManager->deposit($testUserId, 15000, null, 'Test nạp lần 1');
if ($result1['success'] && isset($result1['promotion']['applied'])) {
    echo "✅ Nhận được bonus: {$result1['promotion']['bonus_xu']} XU\n";
} else {
    echo "❌ Không nhận được bonus\n";
}

// Test nạp lần 2  
echo "\n🔸 Lần 2: Nạp 20,000đ\n";
$result2 = $walletManager->deposit($testUserId, 20000, null, 'Test nạp lần 2');
if ($result2['success'] && isset($result2['promotion']['applied'])) {
    echo "✅ Nhận được bonus: {$result2['promotion']['bonus_xu']} XU\n";
} else {
    echo "❌ Không nhận được bonus\n";
}

// Test nạp lần 3 (sẽ không có bonus)
echo "\n🔸 Lần 3: Nạp 25,000đ (sẽ không có bonus)\n";
$result3 = $walletManager->deposit($testUserId, 25000, null, 'Test nạp lần 3');
if ($result3['success'] && isset($result3['promotion']['applied'])) {
    echo "❌ Lỗi: Vẫn nhận được bonus dù đã hết lượt!\n";
} else {
    echo "✅ Đúng: Không nhận bonus (đã hết lượt)\n";
}

echo "\n=== LỊCH SỬ SỬ DỤNG KHUYẾN MÃI ===\n";
$usage = $db->query("
    SELECT pu.*, p.name as promo_name
    FROM promotion_usage pu
    LEFT JOIN promotions p ON pu.promotion_id = p.id  
    WHERE pu.user_id = ?
    ORDER BY pu.created_at DESC
    LIMIT 5
", [$testUserId]);

foreach ($usage as $u) {
    echo "- {$u['created_at']}: {$u['promo_name']} | Nạp: " . number_format($u['deposit_amount']) . "đ → Bonus: {$u['bonus_xu']} XU\n";
}
?>