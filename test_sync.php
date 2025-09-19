<?php
require_once __DIR__ . '/app/config.php';
require_once __DIR__ . '/app/db.php';
require_once __DIR__ . '/app/models/Plan.php';
require_once __DIR__ . '/app/controllers/SubscriptionController.php';

echo "<h1>🔄 Test Data Sync</h1>";

try {
    echo "<h2>📊 Dữ liệu từ Plan Model (Admin sử dụng):</h2>";
    $plansFromModel = Plan::getActive();
    echo "<pre>" . json_encode($plansFromModel, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "</pre>";
    
    echo "<h2>🎯 Dữ liệu từ SubscriptionController (Pricing sử dụng):</h2>";
    $plansFromController = SubscriptionController::getAvailablePlans();
    echo "<pre>" . json_encode($plansFromController, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "</pre>";
    
    // So sánh
    if (count($plansFromModel) === count($plansFromController['data'])) {
        echo "<h2>✅ Đồng bộ OK: " . count($plansFromModel) . " gói</h2>";
    } else {
        echo "<h2>❌ Không đồng bộ:</h2>";
        echo "<p>Plan Model: " . count($plansFromModel) . " gói</p>";
        echo "<p>SubscriptionController: " . count($plansFromController['data']) . " gói</p>";
    }
    
    echo "<p><a href='/admin/package'>→ Back to Package Admin</a></p>";
    echo "<p><a href='/pricing'>→ View Pricing Page</a></p>";
    
} catch (Exception $e) {
    echo "<h2>❌ Lỗi Test</h2>";
    echo "<p>Error: " . htmlspecialchars($e->getMessage()) . "</p>";
}
?>