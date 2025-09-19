<?php
require_once __DIR__ . '/../app/config.php';
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/auth.php';
require_once __DIR__ . '/../app/middleware.php';

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
$isLoggedIn = Auth::isLoggedIn();
$userName = $_SESSION['user_name'] ?? 'Người dùng';

// Perform logout using Auth class
if ($isLoggedIn) {
    Auth::logout();
    
    // Start new session for logout message
    session_start();
    $_SESSION['logout_success'] = true;
}

// Auto redirect after 3 seconds
$redirectUrl = url();
header("refresh:3;url=" . $redirectUrl);
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đăng Xuất - AIboost.vn</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }
        .fade-in {
            animation: fadeIn 0.5s ease-in;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
    </style>
</head>
<body class="min-h-screen bg-gradient-to-br from-blue-50 to-indigo-100 flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-xl p-8 w-full max-w-md text-center fade-in">
        <!-- Header -->
        <div class="mb-8">
            <div class="text-6xl mb-4">👋</div>
            <h1 class="text-2xl font-bold text-gray-900 mb-2">🤖 AIboost.vn</h1>
            <h2 class="text-3xl font-bold text-gray-900 mb-4">Đăng Xuất Thành Công</h2>
            
            <?php if ($isLoggedIn): ?>
            <p class="text-gray-600">
                Tạm biệt <strong><?php echo htmlspecialchars($userName); ?></strong>!<br>
                Cảm ơn bạn đã sử dụng dịch vụ AI của chúng tôi.
            </p>
            <?php else: ?>
            <p class="text-gray-600">
                Bạn đã đăng xuất khỏi hệ thống.
            </p>
            <?php endif; ?>
        </div>

        <!-- Success Message -->
        <div class="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg mb-6">
            <div class="flex items-center justify-center">
                <span class="text-xl mr-2">✅</span>
                <div>
                    <div class="font-semibold">Đăng xuất thành công!</div>
                    <div class="text-sm">Phiên làm việc của bạn đã được kết thúc an toàn</div>
                </div>
            </div>
        </div>

        <!-- Auto Redirect Info -->
        <div class="bg-blue-50 border border-blue-200 text-blue-700 px-4 py-3 rounded-lg mb-6">
            <div class="flex items-center justify-center">
                <span class="text-xl mr-2">⏱️</span>
                <div>
                    <div class="font-semibold">Tự động chuyển hướng</div>
                    <div class="text-sm">Đang chuyển về trang chủ sau <span id="countdown">3</span> giây...</div>
                </div>
            </div>
        </div>

        <!-- Action Buttons -->
        <div class="space-y-4">
            <a href="<?php echo url(); ?>" 
               class="w-full inline-block bg-blue-600 text-white py-3 px-4 rounded-lg hover:bg-blue-700 transition-colors font-semibold">
                🏠 Về Trang Chủ
            </a>
            
            <a href="<?php echo url('login'); ?>" 
               class="w-full inline-block bg-gray-100 text-gray-700 py-3 px-4 rounded-lg hover:bg-gray-200 transition-colors font-semibold">
                🔑 Đăng Nhập Lại
            </a>
        </div>
    </div>

    <!-- JavaScript -->
    <script>
        // Countdown timer
        let timeLeft = 3;
        const countdownElement = document.getElementById('countdown');
        
        const timer = setInterval(() => {
            timeLeft--;
            countdownElement.textContent = timeLeft;
            
            if (timeLeft <= 0) {
                clearInterval(timer);
                countdownElement.textContent = '0';
                window.location.href = '<?php echo url(); ?>';
            }
        }, 1000);
    </script>
</body>
</html>