<?php
require_once __DIR__ . '/../app/config.php';
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/wallet.php';  // Thêm dòng này
require_once __DIR__ . '/../app/auth.php';
require_once __DIR__ . '/../app/middleware.php';

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Require login to access dashboard
Middleware::requireLogin();

// Log activity
Middleware::logActivity('view_dashboard');

// Get user data and stats using Auth class
$userData = Auth::getUser();
$currentBalance = Auth::getBalance();

if (!$userData) {
    // User not found, logout
    Auth::logout();
    header('Location: ' . url('login'));
    exit;
}

$totalImages = 0;
$totalVideos = 0;
$totalContent = 0;
$recentActivity = [];

try {
    // Get recent transactions for activity
    $recentActivity = $db->select('transactions', '*', 
        ['user_id' => $_SESSION['user_id']], 
        'created_at DESC', 
        5
    );
    
} catch (Exception $e) {
    error_log('Dashboard error: ' . $e->getMessage());
}

// User info
$userName = $userData['full_name'] ?? 'User';
$userEmail = $userData['email'] ?? '';
$isNewUser = !$recentActivity || count($recentActivity) <= 1;
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - AIboost.vn</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }
        .content-container {
            padding-top: 1rem !important;
        }
        @media (min-width: 1024px) {
            .content-container {
                padding-top: 1.5rem !important;
            }
        }
        .feature-card {
            transition: all 0.3s ease;
        }
        .feature-card:hover {
            transform: translateY(-2px);
        }
    </style>
</head>
<body class="min-h-screen bg-gray-50">
    <!-- Include Sidebar -->
    <?php include __DIR__ . '/partials/sidebar.php'; ?>
    
    <!-- Include Header -->
    <?php include __DIR__ . '/partials/header.php'; ?>

    <!-- Main Content -->
    <div class="lg:ml-64 content-container">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8" style="padding-top: 0.5rem !important; padding-bottom: 1rem;">
            
            <!-- Welcome Section -->
            <div class="bg-gradient-to-r from-blue-500 to-purple-600 rounded-xl text-white p-6 lg:p-8 mb-6 lg:mb-8">
                <h2 class="text-2xl lg:text-3xl font-bold mb-2">
                    🎉 Chào mừng <?php echo htmlspecialchars($userName); ?>!
                    <?php if (Auth::isAdmin()): ?>
                    <span class="text-lg bg-white/20 px-3 py-1 rounded-full ml-2">👑 Admin</span>
                    <?php endif; ?>
                </h2>
                <?php if ($isNewUser): ?>
                <p class="text-blue-100 mb-4">Bạn đã được tặng 500 Xu để trải nghiệm các dịch vụ AI<br><br>Hãy nâng cấp gói để sử dụng dịch vụ tốt nhất</p>
                <?php else: ?>
                <p class="text-blue-100 mb-4">Chào mừng bạn quay lại! Hãy tiếp tục sáng tạo với AI</p>
                <?php endif; ?>
                
                <!-- Admin Quick Access -->
                <?php if (Auth::isAdmin()): ?>
                <div class="mt-4">
                    <a href="<?php echo url('admin'); ?>" class="bg-white/20 hover:bg-white/30 px-4 py-2 rounded-lg text-white transition-colors inline-flex items-center">
                        🔧 Quản Trị Hệ Thống
                    </a>
                </div>
                <?php endif; ?>
            </div>

            <!-- AI Services Grid -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mb-8">
                
                <!-- AI Image Generator -->
                <div class="feature-card bg-white rounded-xl shadow-sm p-6 border cursor-pointer hover:shadow-lg transition-shadow" onclick="openImageGenerator()">
                    <div class="text-4xl mb-4">🎨</div>
                    <h3 class="text-xl font-bold text-gray-900 mb-2">Tạo Ảnh AI</h3>
                    <p class="text-gray-600 mb-4">Tạo ảnh chất lượng cao từ mô tả văn bản</p>
                    <div class="flex justify-between items-center">
                        <span class="text-green-600 font-semibold">2.500₫/ảnh</span>
                        <span class="bg-blue-100 text-blue-600 px-3 py-1 rounded-full text-sm">Phổ biến</span>
                    </div>
                </div>

                <!-- AI Video Generator -->
                <div class="feature-card bg-white rounded-xl shadow-sm p-6 border cursor-pointer hover:shadow-lg transition-shadow" onclick="openVideoGenerator()">
                    <div class="text-4xl mb-4">🎬</div>
                    <h3 class="text-xl font-bold text-gray-900 mb-2">Video AI</h3>
                    <p class="text-gray-600 mb-4">Tạo video marketing chuyên nghiệp</p>
                    <div class="flex justify-between items-center">
                        <span class="text-green-600 font-semibold">12.500₫/giây</span>
                        <span class="bg-purple-100 text-purple-600 px-3 py-1 rounded-full text-sm">Mới</span>
                    </div>
                </div>

                <!-- AI Content Writer -->
                <div class="feature-card bg-white rounded-xl shadow-sm p-6 border cursor-pointer hover:shadow-lg transition-shadow" onclick="openContentWriter()">
                    <div class="text-4xl mb-4">✍️</div>
                    <h3 class="text-xl font-bold text-gray-900 mb-2">Nội Dung AI</h3>
                    <p class="text-gray-600 mb-4">Viết content marketing tự động</p>
                    <div class="flex justify-between items-center">
                        <span class="text-green-600 font-semibold">250₫/từ</span>
                        <span class="bg-yellow-100 text-yellow-600 px-3 py-1 rounded-full text-sm">Tiết kiệm</span>
                    </div>
                </div>

                <!-- Voice AI -->
                <div class="feature-card bg-white rounded-xl shadow-sm p-6 border cursor-pointer hover:shadow-lg transition-shadow" onclick="openVoiceAI()">
                    <div class="text-4xl mb-4">🎙️</div>
                    <h3 class="text-xl font-bold text-gray-900 mb-2">Voice AI</h3>
                    <p class="text-gray-600 mb-4">Chuyển văn bản thành giọng nói</p>
                    <div class="flex justify-between items-center">
                        <span class="text-green-600 font-semibold">500₫/phút</span>
                        <span class="bg-green-100 text-green-600 px-3 py-1 rounded-full text-sm">Sắp ra</span>
                    </div>
                </div>

                <!-- AI Assistant -->
                <div class="feature-card bg-white rounded-xl shadow-sm p-6 border cursor-pointer hover:shadow-lg transition-shadow" onclick="openAssistant()">
                    <div class="text-4xl mb-4">🤖</div>
                    <h3 class="text-xl font-bold text-gray-900 mb-2">AI Assistant</h3>
                    <p class="text-gray-600 mb-4">Trợ lý AI thông minh 24/7</p>
                    <div class="flex justify-between items-center">
                        <span class="text-green-600 font-semibold">1.000₫/tin</span>
                        <span class="bg-red-100 text-red-600 px-3 py-1 rounded-full text-sm">Hot</span>
                    </div>
                </div>

                <!-- Wallet & History -->
                <div class="feature-card bg-white rounded-xl shadow-sm p-6 border cursor-pointer hover:shadow-lg transition-shadow" onclick="openWallet()">
                    <div class="text-4xl mb-4">💼</div>
                    <h3 class="text-xl font-bold text-gray-900 mb-2">Ví & Lịch Sử</h3>
                    <p class="text-gray-600 mb-4">Quản lý số dư và xem lịch sử</p>
                    <div class="flex justify-between items-center">
                        <span class="text-blue-600 font-semibold">Miễn phí</span>
                        <span class="bg-blue-100 text-blue-600 px-3 py-1 rounded-full text-sm">Tiện ích</span>
                    </div>
                </div>
            </div>

            <!-- Quick Stats -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
                <div class="bg-white rounded-xl shadow-sm p-6 text-center">
                    <div class="text-3xl font-bold text-blue-600 mb-2 stat-number" data-value="<?php echo $totalImages; ?>">
                        <?php echo number_format($totalImages); ?>
                    </div>
                    <div class="text-sm text-gray-600">Ảnh đã tạo</div>
                </div>
                <div class="bg-white rounded-xl shadow-sm p-6 text-center">
                    <div class="text-3xl font-bold text-purple-600 mb-2 stat-number" data-value="<?php echo $totalVideos; ?>">
                        <?php echo number_format($totalVideos); ?>
                    </div>
                    <div class="text-sm text-gray-600">Video đã tạo</div>
                </div>
                <div class="bg-white rounded-xl shadow-sm p-6 text-center">
                    <div class="text-3xl font-bold text-green-600 mb-2 stat-number" data-value="<?php echo $totalContent; ?>">
                        <?php echo number_format($totalContent); ?>
                    </div>
                    <div class="text-sm text-gray-600">Nội dung đã viết</div>
                </div>
                <div class="bg-white rounded-xl shadow-sm p-6 text-center">
                    <div class="text-3xl font-bold text-orange-600 mb-2 stat-number" data-value="<?php echo $currentBalance; ?>">
                        <?php echo number_format($currentBalance); ?>
                    </div>
                    <div class="text-sm text-gray-600">Số dư hiện tại</div>
                </div>
            </div>

            <!-- Recent Activity -->
            <div class="bg-white rounded-xl shadow-sm p-6">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-xl font-bold text-gray-900">📊 Hoạt động gần đây</h3>
                    <a href="<?php echo url('wallet'); ?>" class="text-blue-600 hover:text-blue-700 text-sm font-medium">
                        Xem tất cả →
                    </a>
                </div>
                
                <?php if ($recentActivity && count($recentActivity) > 0): ?>
                <div class="space-y-4">
                    <?php foreach ($recentActivity as $activity): ?>
                    <div class="activity-item flex items-center justify-between p-4 bg-gray-50 rounded-lg">
                        <div class="flex items-center">
                            <div class="w-10 h-10 rounded-full flex items-center justify-center mr-4 
                                <?php echo $activity['type'] === 'credit' ? 'bg-green-100 text-green-600' : 'bg-red-100 text-red-600'; ?>">
                                <?php echo $activity['type'] === 'credit' ? '💰' : '💸'; ?>
                            </div>
                            <div>
                                <div class="font-medium text-gray-900">
                                    <?php echo htmlspecialchars($activity['description'] ?? 'Giao dịch'); ?>
                                </div>
                                <div class="text-sm text-gray-500">
                                    <?php echo date('d/m/Y H:i', strtotime($activity['created_at'])); ?>
                                </div>
                            </div>
                        </div>
                        <div class="text-right">
                            <div class="font-semibold <?php echo $activity['type'] === 'credit' ? 'text-green-600' : 'text-red-600'; ?>">
                                <?php echo $activity['type'] === 'credit' ? '+' : '-'; ?><?php echo number_format($activity['amount']); ?>₫
                            </div>
                            <div class="text-xs text-gray-500 uppercase">
                                <?php echo htmlspecialchars($activity['status'] ?? 'completed'); ?>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                <div class="text-center py-8 text-gray-500">
                    <div class="text-4xl mb-2">🌟</div>
                    <p class="text-sm">Chưa có hoạt động nào. Hãy bắt đầu tạo nội dung AI đầu tiên!</p>
                    <div class="mt-4">
                        <button onclick="openImageGenerator()" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition-colors">
                            Tạo ảnh đầu tiên 🎨
                        </button>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Include Footer -->
    <?php include __DIR__ . '/partials/footer.php'; ?>

    <script>
        // Navigation functions for dashboard cards
        function openImageGenerator() {
            window.location.href = '<?php echo url('image'); ?>';
        }
        
        function openVideoGenerator() {
            window.location.href = '<?php echo url('video'); ?>';
        }
        
        function openContentWriter() {
            window.location.href = '<?php echo url('content'); ?>';
        }
        
        function openVoiceAI() {
            window.location.href = '<?php echo url('voice'); ?>';
        }
        
        function openAssistant() {
            window.location.href = '<?php echo url('assistant'); ?>';
        }
        
        function openWallet() {
            window.location.href = '<?php echo url('wallet'); ?>';
        }

        // Animate stats on load
        document.addEventListener('DOMContentLoaded', function() {
            // Animate stat numbers
            const statNumbers = document.querySelectorAll('.stat-number');
            statNumbers.forEach(stat => {
                const value = parseInt(stat.dataset.value) || 0;
                animateValue(stat, 0, value, 1000);
            });
            
            // Add feature card click effects
            const featureCards = document.querySelectorAll('.feature-card');
            featureCards.forEach(card => {
                card.addEventListener('click', function() {
                    this.style.transform = 'scale(0.98)';
                    setTimeout(() => {
                        this.style.transform = '';
                    }, 150);
                });
            });
        });

        // Animate number counting
        function animateValue(element, start, end, duration) {
            if (start === end) return;
            
            const range = end - start;
            const increment = end > start ? 1 : -1;
            const stepTime = Math.abs(Math.floor(duration / range));
            let current = start;
            
            const timer = setInterval(() => {
                current += increment;
                
                if (element.textContent.includes('₫')) {
                    element.textContent = number_format(current) + '₫';
                } else {
                    element.textContent = number_format(current);
                }
                
                if (current === end) {
                    clearInterval(timer);
                }
            }, stepTime);
        }

        // Number formatting function
        function number_format(number) {
            return new Intl.NumberFormat('vi-VN').format(number);
        }

        // Auto refresh balance every 30 seconds
        setInterval(async function() {
            try {
                const response = await fetch('<?php echo url('api/balance'); ?>');
                const data = await response.json();
                
                if (data.success) {
                    const balanceElement = document.querySelector('.text-orange-600.stat-number');
                    if (balanceElement) {
                        const currentBalance = parseInt(balanceElement.dataset.value);
                        const newBalance = parseInt(data.balance);
                        
                        if (currentBalance !== newBalance) {
                            balanceElement.dataset.value = newBalance;
                            animateValue(balanceElement, currentBalance, newBalance, 1000);
                        }
                    }
                }
            } catch (error) {
                console.log('Balance refresh failed:', error);
            }
        }, 30000);

        console.log('✅ Dashboard with Auth/Middleware loaded successfully!');
        console.log('User: <?php echo htmlspecialchars($userName); ?>');
        console.log('Balance: <?php echo number_format($currentBalance); ?>₫');
    </script>
</body>
</html>