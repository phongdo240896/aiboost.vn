<?php
require_once __DIR__ . '/app/config.php';
require_once __DIR__ . '/app/db.php';
require_once __DIR__ . '/app/WalletManager.php';

// Test với user_id thực tế
$testUserId = 'user_1758016603_68c9345b08259'; // Thay bằng user_id thực
$walletManager = new WalletManager($db);

echo "=== TEST KHUYẾN MÃI ===\n\n";

// Kiểm tra khuyến mãi hiện tại trước
echo "=== KIỂM TRA KHUYẾN MÃI HIỆN TẠI ===\n";
$promotions = $db->query("
    SELECT * FROM promotions 
    WHERE status = 'active' 
    AND start_date <= NOW() 
    AND end_date >= NOW()
    ORDER BY value DESC
");

if (empty($promotions)) {
    echo "❌ Không có khuyến mãi nào đang hoạt động!\n";
    echo "Hãy tạo khuyến mãi trong admin panel trước.\n\n";
} else {
    foreach ($promotions as $promo) {
        echo "✅ Khuyến mãi: {$promo['name']}\n";
        echo "   - Loại: {$promo['type']}\n";
        echo "   - Giá trị: {$promo['value']}%\n";
        echo "   - Nạp tối thiểu: " . number_format($promo['min_deposit']) . "đ\n";
        echo "   - Thời gian: {$promo['start_date']} → {$promo['end_date']}\n\n";
    }
}

// Kiểm tra số dư hiện tại
$currentBalance = $walletManager->getBalance($testUserId);
echo "Số dư hiện tại: {$currentBalance} XU\n\n";

// Test nạp 15,000 VND (đủ điều kiện khuyến mãi 20%, min 10,000)
echo "=== THỰC HIỆN NẠP TIỀN ===\n";
echo "Nạp 15,000 VND...\n\n";

$result = $walletManager->deposit($testUserId, 15000, null, 'Test nạp tiền có khuyến mãi');

echo "Kết quả nạp tiền:\n";
print_r($result);

if ($result['success']) {
    if (isset($result['promotion']) && $result['promotion']['applied']) {
        echo "\n🎉 KHUYẾN MÃI ĐÃ ĐƯỢC ÁP DỤNG!\n";
        echo "- Tên khuyến mãi: {$result['promotion']['name']}\n";
        echo "- XU nhận được: {$result['xu_received']} XU\n";
        echo "- Bonus XU: {$result['promotion']['bonus_xu']} XU\n";
        echo "- Tổng số dư: {$result['new_balance']} XU\n";
    } else {
        echo "\n❌ Không có khuyến mãi nào được áp dụng\n";
        echo "Có thể do:\n";
        echo "- Không có khuyến mãi đang hoạt động\n";
        echo "- Số tiền nạp chưa đủ điều kiện\n";
        echo "- Lỗi trong quá trình xử lý\n";
    }
}

// Kiểm tra lịch sử giao dịch
echo "\n=== LỊCH SỬ GIAO DỊCH ===\n";
$history = $walletManager->getTransactionHistory($testUserId, 5);
foreach ($history as $tx) {
    echo "- {$tx['created_at']}: {$tx['description']} | ";
    if ($tx['amount_xu']) {
        echo "{$tx['amount_xu']} XU";
    }
    if ($tx['amount_vnd']) {
        echo " ({$tx['amount_vnd']} VND)";
    }
    echo "\n";
}

// Kiểm tra bảng promotion_usage
echo "\n=== KIỂM TRA PROMOTION USAGE ===\n";
$usages = $db->query("
    SELECT pu.*, p.name as promotion_name 
    FROM promotion_usage pu
    LEFT JOIN promotions p ON pu.promotion_id = p.id
    WHERE pu.user_id = ?
    ORDER BY pu.created_at DESC
    LIMIT 3
", [$testUserId]);

if (empty($usages)) {
    echo "Chưa có lịch sử sử dụng khuyến mãi nào.\n";
} else {
    foreach ($usages as $usage) {
        echo "- {$usage['created_at']}: {$usage['promotion_name']}\n";
        echo "  Nạp: " . number_format($usage['deposit_amount']) . "đ → Bonus: {$usage['bonus_xu']} XU\n";
    }
}
?>