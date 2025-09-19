<?php
require_once __DIR__ . '/../app/config.php';
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/auth.php';
require_once __DIR__ . '/../app/middleware.php';
require_once __DIR__ . '/../app/SubscriptionMiddleware.php';

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Require login
Middleware::requireLogin();

$userData = Auth::getUser();
// SỬA LỖI: Lấy ID từ userData thay vì dùng getUserId()
$userId = isset($userData['id']) ? $userData['id'] : null;
$userName = isset($userData['full_name']) ? $userData['full_name'] : 'User';

// Get user's current subscription status
$subscriptionInfo = SubscriptionMiddleware::checkUserSubscription($userId);

// Get intended URL (where user tried to go)
$intendedUrl = isset($_SESSION['intended_url']) ? $_SESSION['intended_url'] : url('dashboard');
$featureName = '';

// Determine which feature was blocked
if (strpos($intendedUrl, 'topup') !== false) {
    $featureName = 'Nạp Xu';
} elseif (strpos($intendedUrl, 'video-ai') !== false) {
    $featureName = 'Video AI';
} elseif (strpos($intendedUrl, 'voice-ai') !== false) {
    $featureName = 'Voice AI';
} else {
    $featureName = 'Tính năng này';
}

// Clear session flag
unset($_SESSION['subscription_required']);

$pageTitle = "Yêu Cầu Nâng Cấp Gói";
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle); ?> - AIboost.vn</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }
        
        .gradient-bg {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        
        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.05); }
        }
        
        .pulse-animation {
            animation: pulse 2s infinite;
        }
        
        .lock-icon {
            animation: shake 0.5s;
        }
        
        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            10%, 30%, 50%, 70%, 90% { transform: translateX(-10px); }
            20%, 40%, 60%, 80% { transform: translateX(10px); }
        }
        
        .feature-badge {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
        }
        
        .upgrade-banner {
            background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
        }
    </style>
</head>
<body class="min-h-screen bg-gray-50">
    
    <!-- Include Sidebar -->
    <?php include __DIR__ . '/partials/sidebar.php'; ?>
    
    <!-- Include Header -->
    <?php include __DIR__ . '/partials/header.php'; ?>

    <!-- Main Content -->
    <div class="lg:ml-64 pt-16">
        <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
            
            <!-- Alert Box -->
            <div class="bg-white rounded-2xl shadow-xl p-8 mb-8 text-center relative overflow-hidden">
                <div class="absolute top-0 left-0 w-full h-2 gradient-bg"></div>
                
                <!-- Lock Icon -->
                <div class="lock-icon inline-flex items-center justify-center w-24 h-24 bg-red-100 rounded-full mb-6">
                    <svg class="w-12 h-12 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                    </svg>
                </div>
                
                <h1 class="text-3xl font-bold text-gray-900 mb-4">
                    🚫 Tính Năng Yêu Cầu Đăng Ký Gói
                </h1>
                
                <div class="max-w-2xl mx-auto">
                    <p class="text-lg text-gray-600 mb-2">
                        <strong><?php echo htmlspecialchars($featureName); ?></strong> chỉ dành cho khách hàng đã đăng ký gói từ <span class="text-blue-600 font-semibold">Standard</span> trở lên.
                    </p>
                    
                    <p class="text-gray-500 mb-6">
                        Bạn đang sử dụng gói <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-gray-100 text-gray-800">
                            🆓 <?php echo htmlspecialchars($subscriptionInfo['plan_name']); ?>
                        </span>
                        <?php if ($subscriptionInfo['status'] === 'expired'): ?>
                            <span class="text-red-600 text-sm">(Đã hết hạn)</span>
                        <?php endif; ?>
                    </p>
                    
                    <!-- Benefits -->
                    <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-6">
                        <h3 class="font-semibold text-blue-900 mb-2">✨ Lợi ích khi nâng cấp:</h3>
                        <ul class="text-left text-blue-800 space-y-1 text-sm">
                            <li>✅ Nạp Xu không giới hạn để sử dụng các dịch vụ AI</li>
                            <li>✅ Truy cập đầy đủ các công cụ AI tiên tiến</li>
                            <li>✅ Ưu tiên xử lý và tốc độ nhanh hơn</li>
                            <li>✅ Hỗ trợ khách hàng VIP 24/7</li>
                            <li>✅ Nhận ưu đãi và khuyến mãi đặc biệt</li>
                        </ul>
                    </div>
                    
                    <!-- Action Buttons -->
                    <div class="flex flex-col sm:flex-row gap-4 justify-center">
                        <a href="<?php echo url('pricing'); ?>" 
                           class="pulse-animation inline-flex items-center justify-center px-8 py-3 border border-transparent text-base font-medium rounded-lg text-white gradient-bg hover:opacity-90 transition-opacity">
                            🚀 Xem Bảng Giá & Nâng Cấp
                        </a>
                        <a href="<?php echo url('dashboard'); ?>" 
                           class="inline-flex items-center justify-center px-8 py-3 border border-gray-300 text-base font-medium rounded-lg text-gray-700 bg-white hover:bg-gray-50 transition-colors">
                            ← Quay Lại Dashboard
                        </a>
                    </div>
                </div>
            </div>
            
            <!-- Special Offer Banner -->
            <div class="upgrade-banner rounded-xl p-6 text-white text-center mb-8">
                <div class="flex items-center justify-center mb-4">
                    <svg class="w-8 h-8 mr-2" fill="currentColor" viewBox="0 0 20 20">
                        <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/>
                    </svg>
                    <h2 class="text-2xl font-bold">Ưu Đãi Đặc Biệt!</h2>
                    <svg class="w-8 h-8 ml-2" fill="currentColor" viewBox="0 0 20 20">
                        <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/>
                    </svg>
                </div>
                <p class="text-xl mb-4">
                    Đăng ký gói <strong>Pro</strong> hôm nay để nhận ngay <strong>6.000 XU</strong> mỗi tháng!
                </p>
                <p class="text-sm opacity-90">
                    💡 Mẹo: Gói Pro là lựa chọn phổ biến nhất với đầy đủ tính năng AI và giá cả hợp lý
                </p>
            </div>
            
            <!-- FAQ Section -->
            <div class="bg-gray-100 rounded-xl p-6">
                <h3 class="text-lg font-bold text-gray-900 mb-4">❓ Câu hỏi thường gặp</h3>
                <div class="space-y-4">
                    <div>
                        <h4 class="font-semibold text-gray-800">Tại sao tôi cần đăng ký gói để nạp XU?</h4>
                        <p class="text-gray-600 text-sm mt-1">
                            Tính năng nạp XU được thiết kế dành riêng cho khách hàng đã cam kết sử dụng dịch vụ của chúng tôi. 
                            Điều này giúp chúng tôi cung cấp dịch vụ tốt nhất và hỗ trợ chuyên nghiệp cho bạn.
                        </p>
                    </div>
                    <div>
                        <h4 class="font-semibold text-gray-800">Sự khác biệt giữa các gói là gì?</h4>
                        <p class="text-gray-600 text-sm mt-1">
                            Mỗi gói cung cấp số lượng XU và tính năng khác nhau. Gói cao hơn sẽ có nhiều XU hơn, 
                            truy cập nhiều công cụ AI nâng cao hơn và được ưu tiên xử lý nhanh hơn.
                        </p>
                    </div>
                    <div>
                        <h4 class="font-semibold text-gray-800">Tôi có thể hủy gói bất cứ lúc nào không?</h4>
                        <p class="text-gray-600 text-sm mt-1">
                            Có, bạn có thể hủy gói đăng ký bất cứ lúc nào. Gói của bạn sẽ vẫn hoạt động đến hết thời hạn đã thanh toán.
                        </p>
                    </div>
                    <div>
                        <h4 class="font-semibold text-gray-800">XU của tôi có bị mất khi nâng cấp gói không?</h4>
                        <p class="text-gray-600 text-sm mt-1">
                            Không, số XU hiện có của bạn sẽ được giữ nguyên và cộng thêm XU từ gói mới khi nâng cấp.
                        </p>
                    </div>
                </div>
            </div>
            
            <!-- Contact Support -->
            <div class="mt-8 text-center">
                <p class="text-gray-600">
                    Cần hỗ trợ? 
                    <a href="<?php echo url('help'); ?>" class="text-blue-600 hover:underline font-medium">
                        Liên hệ với chúng tôi
                    </a>
                </p>
            </div>
            
        </div>
    </div>

    <!-- Include Footer -->
    <?php include __DIR__ . '/partials/footer.php'; ?>
    
    <!-- JavaScript -->
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        console.log('✅ Subscription Required page loaded');
        
        // Add smooth scroll for anchor links
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({ behavior: 'smooth', block: 'start' });
                }
            });
        });
        
        // Log subscription status
        console.log('Current plan: <?php echo $subscriptionInfo['plan_name']; ?>');
        console.log('Status: <?php echo $subscriptionInfo['status']; ?>');
        console.log('Feature blocked: <?php echo $featureName; ?>');
    });
    </script>
</body>
</html>