<?php
require_once __DIR__ . '/../app/config.php';
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/auth.php';
require_once __DIR__ . '/../app/middleware.php';
require_once __DIR__ . '/../app/WalletManager.php';

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Require login
Middleware::requireLogin();
Middleware::logActivity('view_checkout_page');

// Get user data
$userData = Auth::getUser();
if (!$userData) {
    header('Location: ' . url('login'));
    exit;
}

$userId = $userData['id'];
$userName = $userData['full_name'] ?? 'User';
$userEmail = $userData['email'] ?? '';

// Get plan details from POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['plan_id'])) {
    header('Location: ' . url('pricing'));
    exit;
}

$planId = $_POST['plan_id'];
$planName = $_POST['plan_name'] ?? 'Unknown';
$planPrice = floatval($_POST['plan_price'] ?? 0);
$planCredits = intval($_POST['plan_credits'] ?? 0);
$billing = $_POST['billing'] ?? 'monthly';

// Calculate final price and credits based on billing
$finalPrice = $planPrice;
$finalCredits = $planCredits;
$duration = 30; // days

if ($billing === 'yearly') {
    $finalPrice = $planPrice * 12 * 0.8; // 20% discount
    $finalCredits = $planCredits * 12 * 1.1; // 10% bonus
    $duration = 365;
}

// Generate unique payment code for subscription - Format: SUB####XXXX
$paymentCode = 'SUB' . substr($userId, -4) . strtoupper(substr(md5(time() . $userId), 0, 4));

// Initialize WalletManager
$walletManager = new WalletManager($db);
$exchangeRate = $walletManager->getExchangeRate();

// Get bank settings
$bankSettings = [];
try {
    $bankRecords = $db->select('bank_settings', '*', ['status' => 'active'], 'bank_code ASC');
    foreach ($bankRecords as $bank) {
        $bankSettings[$bank['bank_code']] = [
            'bank_name' => $bank['bank_name'],
            'account_number' => $bank['account_number'],
            'account_holder' => $bank['account_holder'],
            'status' => $bank['status']
        ];
    }
} catch (Exception $e) {
    error_log('Error loading bank settings: ' . $e->getMessage());
}

// Save pending subscription order
try {
    // Create subscription_orders table if not exists
    $createOrderTable = "CREATE TABLE IF NOT EXISTS `subscription_orders` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `order_id` VARCHAR(50) UNIQUE NOT NULL,
        `user_id` VARCHAR(36) NOT NULL,
        `plan_id` INT NOT NULL,
        `plan_name` VARCHAR(100),
        `amount` DECIMAL(15,2) NOT NULL,
        `credits` INT NOT NULL,
        `duration` INT NOT NULL,
        `billing_type` VARCHAR(20),
        `status` ENUM('pending', 'processing', 'completed', 'failed', 'cancelled') DEFAULT 'pending',
        `transaction_id` VARCHAR(100),
        `processed_at` DATETIME,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_order_id (order_id),
        INDEX idx_user_id (user_id),
        INDEX idx_status (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    
    $db->getPdo()->exec($createOrderTable);
    
    // Check if order already exists
    $existingOrder = $db->query(
        "SELECT id FROM subscription_orders WHERE order_id = ?",
        [$paymentCode]
    );
    
    if (empty($existingOrder)) {
        // Insert new order
        $stmt = $db->getPdo()->prepare("
            INSERT INTO subscription_orders 
            (order_id, user_id, plan_id, plan_name, amount, credits, duration, billing_type, status)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending')
        ");
        
        $stmt->execute([
            $paymentCode,
            $userId,
            $planId,
            $planName,
            $finalPrice,
            $finalCredits,
            $duration,
            $billing
        ]);
        
        error_log("Created subscription order: $paymentCode for user: $userId");
    }
    
} catch (Exception $e) {
    error_log('Error saving subscription order: ' . $e->getMessage());
}

$pageTitle = "Thanh toán gói " . $planName . " - AIboost.vn";
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        body { 
            font-family: 'Inter', sans-serif; 
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
        }
        .bank-tab.active {
            background-color: #3b82f6;
            color: white;
        }
        .main-container {
            background: white;
            min-height: calc(100vh - 64px);
        }
        /* Gradient header background */
        .gradient-bg {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
    </style>
</head>
<body>
    <?php include __DIR__ . '/partials/sidebar.php'; ?>
    <?php include __DIR__ . '/partials/header.php'; ?>

    <!-- Main Content Container - Sát với header -->
    <div class="lg:ml-64 pt-16">
        <div class="main-container">
            <!-- Gradient Header Section -->
            <div class="gradient-bg px-4 sm:px-6 lg:px-8 py-8">
                <div class="max-w-7xl mx-auto">
                    <div class="text-center text-white">
                        <h1 class="text-3xl font-bold mb-2">💳 Thanh Toán Nâng Cấp Gói</h1>
                        <p class="text-white/90 text-lg">Hoàn tất thanh toán để kích hoạt gói cước và nhận Xu</p>
                    </div>
                </div>
            </div>

            <!-- Content Section -->
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    
                    <!-- Order Summary -->
                    <div class="lg:col-span-1">
                        <div class="bg-white rounded-xl shadow-lg border p-6 sticky top-6">
                            <h3 class="text-lg font-semibold mb-4 flex items-center">
                                <span class="bg-gradient-to-r from-blue-500 to-purple-600 bg-clip-text text-transparent">
                                    📦 Thông tin đơn hàng
                                </span>
                            </h3>
                            
                            <div class="space-y-4">
                                <div class="flex justify-between items-center py-2 border-b border-gray-100">
                                    <span class="text-gray-600">Gói dịch vụ:</span>
                                    <span class="font-semibold text-gray-900"><?= htmlspecialchars($planName) ?></span>
                                </div>
                                <div class="flex justify-between items-center py-2 border-b border-gray-100">
                                    <span class="text-gray-600">Chu kỳ:</span>
                                    <span class="text-gray-900"><?= $billing === 'yearly' ? 'Hàng năm' : 'Hàng tháng' ?></span>
                                </div>
                                <div class="flex justify-between items-center py-2 border-b border-gray-100">
                                    <span class="text-gray-600">Thời hạn:</span>
                                    <span class="text-gray-900"><?= $duration ?> ngày</span>
                                </div>
                                <div class="flex justify-between items-center py-2 border-b border-gray-100">
                                    <span class="text-gray-600">Xu nhận được:</span>
                                    <span class="text-green-600 font-bold"><?= number_format($finalCredits) ?> XU</span>
                                </div>
                                
                                <?php if ($billing === 'yearly'): ?>
                                <div class="bg-gradient-to-r from-green-50 to-blue-50 p-4 rounded-lg border border-green-200">
                                    <div class="flex items-start">
                                        <i class="fas fa-gift text-green-600 mr-2 mt-1"></i>
                                        <div>
                                            <div class="font-semibold text-green-800">Ưu đãi gói năm:</div>
                                            <div class="text-sm text-green-700 mt-1">
                                                • Giảm 20% giá gói<br>
                                                • Tặng thêm 10% Xu
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <?php endif; ?>
                                
                                <div class="pt-4 border-t-2 border-gray-200">
                                    <div class="flex justify-between items-center">
                                        <span class="text-xl font-bold text-gray-900">Tổng thanh toán:</span>
                                        <span class="text-3xl font-bold bg-gradient-to-r from-blue-600 to-purple-600 bg-clip-text text-transparent">
                                            <?= number_format($finalPrice) ?> ₫
                                        </span>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mt-6 p-4 bg-gradient-to-r from-amber-50 to-orange-50 border-l-4 border-amber-400 rounded-r-lg">
                                <p class="text-sm font-semibold text-amber-800 mb-2">
                                    <i class="fas fa-exclamation-triangle mr-1"></i>
                                    Mã giao dịch (Nội dung CK):
                                </p>
                                <div class="bg-white p-3 rounded-lg border-2 border-dashed border-amber-300">
                                    <p class="font-mono font-bold text-2xl text-center text-amber-900 select-all tracking-wider">
                                        <?= $paymentCode ?>
                                    </p>
                                </div>
                                <p class="text-xs text-amber-700 mt-2 text-center font-medium">
                                    ⚠️ Vui lòng nhập chính xác mã này khi chuyển khoản
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- Payment Instructions -->
                    <div class="lg:col-span-2">
                        <div class="bg-white rounded-xl shadow-lg border">
                            <!-- Header -->
                            <div class="bg-gradient-to-r from-blue-600 to-purple-600 p-6 rounded-t-xl">
                                <h3 class="text-xl font-bold text-white flex items-center">
                                    <i class="fas fa-university mr-2"></i>
                                    Thông tin chuyển khoản
                                </h3>
                                <p class="text-blue-100 mt-1">Chọn ngân hàng và thực hiện chuyển khoản</p>
                            </div>

                            <div class="p-6">
                                <!-- Bank Tabs -->
                                <div class="flex flex-wrap gap-3 mb-6">
                                    <?php foreach ($bankSettings as $code => $bank): ?>
                                    <button onclick="selectBank('<?= $code ?>')" 
                                            class="px-6 py-3 rounded-xl border-2 hover:shadow-md transition-all duration-200 bank-tab font-medium"
                                            data-bank="<?= $code ?>">
                                        <i class="fas fa-university mr-2"></i>
                                        <?= $bank['bank_name'] ?>
                                    </button>
                                    <?php endforeach; ?>
                                </div>

                                <!-- Bank Details -->
                                <div id="bankInfo" style="display: none;">
                                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                                        <div class="space-y-4">
                                            <h4 class="font-bold text-lg text-gray-900 border-b pb-2">Thông tin tài khoản:</h4>
                                            
                                            <div class="space-y-4">
                                                <div class="bg-gradient-to-r from-gray-50 to-gray-100 p-4 rounded-xl border">
                                                    <span class="text-gray-600 text-sm font-medium">Ngân hàng</span>
                                                    <div class="font-bold text-lg mt-1 text-gray-900" id="bankName"></div>
                                                </div>
                                                
                                                <div class="bg-gradient-to-r from-blue-50 to-indigo-50 p-4 rounded-xl border border-blue-200">
                                                    <span class="text-blue-600 text-sm font-medium">Số tài khoản</span>
                                                    <div class="flex items-center justify-between mt-2">
                                                        <span id="accountNumber" class="font-mono font-bold text-xl text-blue-900"></span>
                                                        <button onclick="copyToClipboard('accountNumber')" 
                                                                class="text-blue-600 hover:text-blue-800 p-2 hover:bg-blue-100 rounded-lg transition">
                                                            <i class="fas fa-copy text-lg"></i>
                                                        </button>
                                                    </div>
                                                </div>
                                                
                                                <div class="bg-gradient-to-r from-green-50 to-emerald-50 p-4 rounded-xl border border-green-200">
                                                    <span class="text-green-600 text-sm font-medium">Chủ tài khoản</span>
                                                    <div class="font-bold text-lg mt-1 text-green-900" id="accountHolder"></div>
                                                </div>
                                                
                                                <div class="bg-gradient-to-r from-purple-50 to-pink-50 p-4 rounded-xl border border-purple-200">
                                                    <span class="text-purple-600 text-sm font-medium">Số tiền</span>
                                                    <div class="flex items-center justify-between mt-2">
                                                        <span class="font-bold text-2xl text-purple-900">
                                                            <?= number_format($finalPrice) ?> ₫
                                                        </span>
                                                        <button onclick="copyAmount()" 
                                                                class="text-purple-600 hover:text-purple-800 p-2 hover:bg-purple-100 rounded-lg transition">
                                                            <i class="fas fa-copy text-lg"></i>
                                                        </button>
                                                    </div>
                                                </div>
                                                
                                                <div class="bg-gradient-to-r from-amber-50 to-orange-50 p-4 rounded-xl border-2 border-amber-300">
                                                    <span class="text-amber-800 text-sm font-bold">Nội dung CK (Bắt buộc)</span>
                                                    <div class="flex items-center justify-between mt-2">
                                                        <span id="transferContent" class="font-mono font-bold text-xl text-amber-900">
                                                            <?= $paymentCode ?>
                                                        </span>
                                                        <button onclick="copyToClipboard('transferContent')" 
                                                                class="text-amber-600 hover:text-amber-800 p-2 hover:bg-amber-100 rounded-lg transition">
                                                            <i class="fas fa-copy text-lg"></i>
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- QR Code -->
                                        <div>
                                            <h4 class="font-bold text-lg text-gray-900 border-b pb-2 mb-4">Quét mã QR để thanh toán:</h4>
                                            <div id="qrCodeContainer" class="bg-gradient-to-br from-gray-50 to-gray-100 rounded-xl p-6 text-center border-2 border-dashed border-gray-300">
                                                <img id="qrCodeImage" style="display: none;" 
                                                     class="rounded-xl mx-auto shadow-lg border-4 border-white" 
                                                     width="280" height="280">
                                                <div id="qrCodePlaceholder" class="py-12 text-gray-400">
                                                    <i class="fas fa-qrcode text-6xl mb-4 text-gray-300"></i>
                                                    <p class="text-lg font-medium">Chọn ngân hàng để hiển thị mã QR</p>
                                                </div>
                                                
                                                <div class="mt-6 flex justify-center gap-3">
                                                    <button id="downloadQRBtn" onclick="downloadQR()" 
                                                            class="px-6 py-3 bg-gradient-to-r from-blue-600 to-purple-600 text-white rounded-xl hover:from-blue-700 hover:to-purple-700 transition shadow-lg"
                                                            style="display: none;">
                                                        <i class="fas fa-download mr-2"></i> Tải mã QR
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Important Notes -->
                                    <div class="mt-8 bg-gradient-to-r from-blue-50 to-indigo-50 p-6 rounded-xl border border-blue-200">
                                        <h5 class="font-bold text-blue-900 mb-4 text-lg">
                                            <i class="fas fa-info-circle mr-2"></i>
                                            Lưu ý quan trọng:
                                        </h5>
                                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                            <div class="flex items-start space-x-3">
                                                <i class="fas fa-check-circle text-green-500 mt-1 text-lg"></i>
                                                <span class="text-blue-800">Nhập <strong>chính xác</strong> nội dung: <code class="bg-white px-2 py-1 rounded font-mono"><?= $paymentCode ?></code></span>
                                            </div>
                                            <div class="flex items-start space-x-3">
                                                <i class="fas fa-check-circle text-green-500 mt-1 text-lg"></i>
                                                <span class="text-blue-800">Kích hoạt <strong>tự động</strong> sau 1-2 phút</span>
                                            </div>
                                            <div class="flex items-start space-x-3">
                                                <i class="fas fa-check-circle text-green-500 mt-1 text-lg"></i>
                                                <span class="text-blue-800">Nhận ngay <strong><?= number_format($finalCredits) ?> XU</strong> vào ví</span>
                                            </div>
                                            <div class="flex items-start space-x-3">
                                                <i class="fas fa-check-circle text-green-500 mt-1 text-lg"></i>
                                                <span class="text-blue-800">Hotline hỗ trợ: <strong>0325.59.59.95</strong></span>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Status Check -->
                                    <div class="mt-8 text-center">
                                        <button onclick="checkPaymentStatus()" 
                                                class="px-8 py-4 bg-gradient-to-r from-green-600 to-emerald-600 text-white rounded-xl hover:from-green-700 hover:to-emerald-700 transition shadow-lg text-lg font-semibold">
                                            <i class="fas fa-sync-alt mr-2"></i>
                                            Kiểm tra trạng thái thanh toán
                                        </button>
                                        
                                        <div id="paymentStatus" class="mt-4"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Support Section -->
                <div class="mt-8 bg-gradient-to-r from-purple-600 via-blue-600 to-indigo-600 text-white rounded-xl p-8 shadow-xl">
                    <div class="flex flex-col lg:flex-row items-center justify-between">
                        <div class="text-center lg:text-left mb-6 lg:mb-0">
                            <h4 class="text-2xl font-bold mb-3">
                                <i class="fas fa-headset mr-2"></i>
                                Cần hỗ trợ?
                            </h4>
                            <p class="text-white/90 text-lg">Đội ngũ CSKH sẵn sàng hỗ trợ bạn 24/7</p>
                        </div>
                        <div class="flex flex-col sm:flex-row gap-4">
                            <a href="#" class="inline-flex items-center justify-center bg-white text-purple-600 px-6 py-3 rounded-xl font-semibold hover:bg-gray-100 transition shadow-lg">
                                <i class="fas fa-comments mr-2"></i> Live Chat
                            </a>
                            <a href="tel:0325595995" class="inline-flex items-center justify-center bg-white text-purple-600 px-6 py-3 rounded-xl font-semibold hover:bg-gray-100 transition shadow-lg">
                                <i class="fas fa-phone mr-2"></i> 0325.59.59.95
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Include Footer -->
    <?php include __DIR__ . '/partials/footer.php'; ?>

    <script>
        let selectedBank = '';
        let bankSettings = <?= json_encode($bankSettings) ?>;
        let paymentCode = '<?= $paymentCode ?>';
        let amount = <?= $finalPrice ?>;
        let checkInterval;

        // Auto select first bank on load
        document.addEventListener('DOMContentLoaded', function() {
            const firstBankBtn = document.querySelector('.bank-tab');
            if (firstBankBtn) {
                firstBankBtn.click();
            }
            
            // Start auto checking payment status every 10 seconds
            startAutoCheck();
        });

        function selectBank(bank) {
            if (!bankSettings[bank]) return;
            
            selectedBank = bank;
            
            // Update UI
            document.querySelectorAll('.bank-tab').forEach(tab => {
                tab.classList.remove('active', 'bg-blue-600', 'text-white', 'shadow-lg', 'scale-105');
                tab.classList.add('hover:shadow-md', 'border-gray-300');
            });
            
            const activeTab = document.querySelector(`[data-bank="${bank}"]`);
            if (activeTab) {
                activeTab.classList.add('active', 'bg-blue-600', 'text-white', 'shadow-lg', 'scale-105', 'border-blue-600');
                activeTab.classList.remove('hover:shadow-md', 'border-gray-300');
            }
            
            // Show bank info with animation
            const bankInfo = document.getElementById('bankInfo');
            bankInfo.style.display = 'block';
            bankInfo.style.opacity = '0';
            setTimeout(() => {
                bankInfo.style.opacity = '1';
                bankInfo.style.transition = 'opacity 0.3s ease-in-out';
            }, 50);
            
            const settings = bankSettings[bank];
            document.getElementById('bankName').textContent = settings.bank_name;
            document.getElementById('accountNumber').textContent = settings.account_number;
            document.getElementById('accountHolder').textContent = settings.account_holder;
            
            // Generate QR Code
            generateQRCode();
        }

        function generateQRCode() {
            if (!selectedBank) return;
            
            const settings = bankSettings[selectedBank];
            const qrUrl = `https://img.vietqr.io/image/${selectedBank}-${settings.account_number}-compact2.jpg?amount=${amount}&addInfo=${paymentCode}&accountName=${encodeURIComponent(settings.account_holder)}`;
            
            const qrImage = document.getElementById('qrCodeImage');
            const placeholder = document.getElementById('qrCodePlaceholder');
            const downloadBtn = document.getElementById('downloadQRBtn');
            
            qrImage.src = qrUrl;
            qrImage.onload = function() {
                qrImage.style.display = 'block';
                placeholder.style.display = 'none';
                downloadBtn.style.display = 'inline-block';
            };
            
            qrImage.onerror = function() {
                // Fallback QR if VietQR fails
                qrImage.src = `https://api.qrserver.com/v1/create-qr-code/?size=280x280&data=${encodeURIComponent(`${settings.bank_name}\nSTK: ${settings.account_number}\nSố tiền: ${amount}\nNội dung: ${paymentCode}`)}`;
            };
        }

        function copyToClipboard(elementId) {
            const element = document.getElementById(elementId);
            const text = element.textContent.trim();
            
            navigator.clipboard.writeText(text).then(() => {
                // Show success feedback
                const originalContent = element.innerHTML;
                element.innerHTML = '<i class="fas fa-check text-green-600"></i> Đã sao chép!';
                element.classList.add('text-green-600');
                
                setTimeout(() => {
                    element.innerHTML = originalContent;
                    element.classList.remove('text-green-600');
                }, 2000);
            }).catch(() => {
                alert('Không thể sao chép. Vui lòng chọn và copy thủ công.');
            });
        }

        function copyAmount() {
            navigator.clipboard.writeText('<?= $finalPrice ?>').then(() => {
                // Show success message
                const toast = document.createElement('div');
                toast.className = 'fixed top-4 right-4 bg-green-500 text-white px-6 py-3 rounded-lg shadow-lg z-50';
                toast.innerHTML = '<i class="fas fa-check mr-2"></i>Đã sao chép số tiền!';
                document.body.appendChild(toast);
                
                setTimeout(() => {
                    toast.remove();
                }, 3000);
            });
        }

        function downloadQR() {
            const qrImage = document.getElementById('qrCodeImage');
            const link = document.createElement('a');
            link.href = qrImage.src;
            link.download = `QR_Subscription_${paymentCode}.jpg`;
            link.click();
        }

        async function checkPaymentStatus() {
            const statusDiv = document.getElementById('paymentStatus');
            
            // Show loading
            statusDiv.innerHTML = `
                <div class="inline-flex items-center text-blue-600 bg-blue-50 px-6 py-3 rounded-xl shadow-sm">
                    <i class="fas fa-spinner fa-spin mr-3"></i>
                    <span class="font-medium">Đang kiểm tra trạng thái thanh toán...</span>
                </div>
            `;
            
            try {
                const response = await fetch(`/api/check_subscription_payment.php?order_id=${paymentCode}`);
                const result = await response.json();
                
                if (result.status === 'completed') {
                    // Payment successful
                    statusDiv.innerHTML = `
                        <div class="inline-flex items-center text-green-600 bg-green-50 px-6 py-4 rounded-xl shadow-sm">
                            <i class="fas fa-check-circle mr-3 text-2xl"></i>
                            <div>
                                <div class="font-bold text-lg">Thanh toán thành công!</div>
                                <div class="text-sm">Đang kích hoạt gói cước...</div>
                            </div>
                        </div>
                    `;
                    
                    // Stop auto checking
                    if (checkInterval) clearInterval(checkInterval);
                    
                    // Redirect after 2 seconds
                    setTimeout(() => {
                        window.location.href = '/dashboard?success=subscription_activated';
                    }, 2000);
                    
                } else if (result.status === 'processing') {
                    statusDiv.innerHTML = `
                        <div class="inline-flex items-center text-yellow-600 bg-yellow-50 px-6 py-3 rounded-xl shadow-sm">
                            <i class="fas fa-hourglass-half mr-3"></i>
                            <span class="font-medium">Đang xử lý thanh toán...</span>
                        </div>
                    `;
                } else {
                    statusDiv.innerHTML = `
                        <div class="inline-flex items-center text-gray-600 bg-gray-50 px-6 py-3 rounded-xl shadow-sm">
                            <i class="fas fa-clock mr-3"></i>
                            <span class="font-medium">Chưa nhận được thanh toán</span>
                        </div>
                    `;
                }
            } catch (error) {
                statusDiv.innerHTML = `
                    <div class="inline-flex items-center text-red-600 bg-red-50 px-6 py-3 rounded-xl shadow-sm">
                        <i class="fas fa-exclamation-circle mr-3"></i>
                        <span class="font-medium">Lỗi kết nối. Vui lòng thử lại.</span>
                    </div>
                `;
            }
        }

        function startAutoCheck() {
            // Check every 10 seconds
            checkInterval = setInterval(checkPaymentStatus, 10000);
        }

        // Stop checking when page is hidden
        document.addEventListener('visibilitychange', function() {
            if (document.hidden) {
                if (checkInterval) clearInterval(checkInterval);
            } else {
                startAutoCheck();
            }
        });

        // Add smooth scroll behavior
        document.documentElement.style.scrollBehavior = 'smooth';
    </script>
</body>
</html>