<?php
require_once __DIR__ . '/../app/config.php';
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/auth.php';

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Simple guest check
if (isset($_SESSION['user_id']) && !empty($_SESSION['user_id'])) {
    header('Location: /dashboard');
    exit();
}

$errors = [];
$success = '';

// Generate CSRF token
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf_token'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Kiểm tra dữ liệu POST có tồn tại không
        if (empty($_POST)) {
            $errors['general'] = 'Không nhận được dữ liệu form';
        } else {
            // Simple rate limiting
            if (!isset($_SESSION['last_register_attempt'])) {
                $_SESSION['last_register_attempt'] = 0;
            }
            
            if (time() - $_SESSION['last_register_attempt'] < 3) {
                $errors['general'] = 'Vui lòng đợi 3 giây trước khi thử lại';
            } else {
                $_SESSION['last_register_attempt'] = time();
                
                // Gọi Auth::register
                $result = Auth::register($_POST);
                
                if ($result && isset($result['success']) && $result['success']) {
                    $success = $result['message'];
                    
                    // CHUYỂN HƯỚNG ĐẾN DASHBOARD THAY VÌ LOGIN
                    if (isset($result['auto_login']) && $result['auto_login']) {
                        // Đã auto login thành công, chuyển đến dashboard
                        $redirectUrl = '/dashboard';
                    } else {
                        // Fallback nếu auto login không thành công
                        $redirectUrl = '/login?registered=true&email=' . urlencode($_POST['email'] ?? '');
                    }
                    
                    header("refresh:2;url=" . $redirectUrl);
                } else {
                    if (isset($result['errors']) && is_array($result['errors'])) {
                        $errors = $result['errors'];
                    } else {
                        $errors['general'] = $result['message'] ?? 'Lỗi không xác định khi đăng ký';
                    }
                }
            }
        }
        
    } catch (Exception $e) {
        $errors['general'] = 'Lỗi hệ thống: ' . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đăng Ký - AIboost.vn</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }
    </style>
</head>
<body class="min-h-screen bg-gradient-to-br from-blue-50 to-indigo-100 flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-xl p-8 w-full max-w-md">
        <!-- Header -->
        <div class="text-center mb-8">
            <h1 class="text-2xl font-bold text-gray-900 mb-4">🤖 AIboost.vn</h1>
            <h2 class="text-3xl font-bold text-gray-900 mb-2">📝 Đăng Ký</h2>
            <p class="text-gray-600">Tạo tài khoản để nhận 500 Xu miễn phí</p>
        </div>

        <!-- Success Message -->
        <?php if (!empty($success)): ?>
        <div class="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg mb-6">
            <div class="flex items-center">
                <span class="text-xl mr-2">🎉</span>
                <div>
                    <div class="font-semibold">Chào mừng!</div>
                    <div class="text-sm"><?php echo htmlspecialchars($success); ?></div>
                    <div class="text-xs mt-1 font-semibold text-green-600">Đang chuyển đến Dashboard...</div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- General Error -->
        <?php if (isset($errors['general'])): ?>
        <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg mb-6">
            <div class="flex items-center">
                <span class="text-xl mr-2">⚠️</span>
                <div>
                    <div class="font-semibold">Lỗi</div>
                    <div class="text-sm"><?php echo htmlspecialchars($errors['general']); ?></div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Registration Form -->
        <form method="POST" action="" class="space-y-6" id="registerForm">
            <!-- CSRF Token -->
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
            
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Họ và tên *</label>
                <input 
                    type="text" 
                    name="full_name"
                    value="<?php echo htmlspecialchars($_POST['full_name'] ?? ''); ?>"
                    placeholder="Nguyễn Văn A"
                    class="w-full px-4 py-3 border <?php echo isset($errors['full_name']) ? 'border-red-300' : 'border-gray-300'; ?> rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                    required
                    <?php echo !empty($success) ? 'disabled' : ''; ?>
                >
                <?php if (isset($errors['full_name'])): ?>
                <p class="mt-1 text-sm text-red-600"><?php echo htmlspecialchars($errors['full_name']); ?></p>
                <?php endif; ?>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Email *</label>
                <input 
                    type="email" 
                    name="email"
                    value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>"
                    placeholder="example@gmail.com"
                    class="w-full px-4 py-3 border <?php echo isset($errors['email']) ? 'border-red-300' : 'border-gray-300'; ?> rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                    required
                    <?php echo !empty($success) ? 'disabled' : ''; ?>
                >
                <?php if (isset($errors['email'])): ?>
                <p class="mt-1 text-sm text-red-600"><?php echo htmlspecialchars($errors['email']); ?></p>
                <?php endif; ?>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Số điện thoại</label>
                <input 
                    type="tel" 
                    name="phone"
                    value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>"
                    placeholder="0901234567"
                    class="w-full px-4 py-3 border <?php echo isset($errors['phone']) ? 'border-red-300' : 'border-gray-300'; ?> rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                    <?php echo !empty($success) ? 'disabled' : ''; ?>
                >
                <?php if (isset($errors['phone'])): ?>
                <p class="mt-1 text-sm text-red-600"><?php echo htmlspecialchars($errors['phone']); ?></p>
                <?php endif; ?>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Mật khẩu *</label>
                <input 
                    type="password" 
                    name="password"
                    placeholder="••••••••"
                    class="w-full px-4 py-3 border <?php echo isset($errors['password']) ? 'border-red-300' : 'border-gray-300'; ?> rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                    required
                    <?php echo !empty($success) ? 'disabled' : ''; ?>
                >
                <?php if (isset($errors['password'])): ?>
                <p class="mt-1 text-sm text-red-600"><?php echo htmlspecialchars($errors['password']); ?></p>
                <?php endif; ?>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Xác nhận mật khẩu *</label>
                <input 
                    type="password" 
                    name="password_confirm"
                    placeholder="••••••••"
                    class="w-full px-4 py-3 border <?php echo isset($errors['password_confirm']) ? 'border-red-300' : 'border-gray-300'; ?> rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                    required
                    <?php echo !empty($success) ? 'disabled' : ''; ?>
                >
                <?php if (isset($errors['password_confirm'])): ?>
                <p class="mt-1 text-sm text-red-600"><?php echo htmlspecialchars($errors['password_confirm']); ?></p>
                <?php endif; ?>
            </div>

            <button 
                type="submit" 
                class="w-full bg-blue-600 text-white py-3 rounded-lg hover:bg-blue-700 transition-colors font-semibold disabled:opacity-50"
                <?php echo !empty($success) ? 'disabled' : ''; ?>
                id="submitBtn"
            >
                <?php echo !empty($success) ? 'Đang chuyển hướng...' : 'Đăng ký miễn phí'; ?>
            </button>
        </form>

        <!-- Links -->
        <div class="mt-6 text-center">
            <p class="text-gray-600">
                Đã có tài khoản? 
                <a href="/login" class="text-blue-600 hover:text-blue-700 font-medium">Đăng nhập</a>
            </p>
        </div>

        <div class="mt-4 text-center">
            <a href="/" class="text-gray-500 hover:text-gray-700 text-sm">← Quay lại trang chủ</a>
        </div>
    </div>

    <script>
        // Auto redirect countdown
        <?php if (!empty($success)): ?>
        let countdown = 2;
        const button = document.getElementById('submitBtn');
        
        const timer = setInterval(() => {
            countdown--;
            button.textContent = `Chuyển hướng sau ${countdown}s...`;
            
            if (countdown <= 0) {
                clearInterval(timer);
                window.location.href = '/dashboard';
            }
        }, 1000);
        <?php endif; ?>
        
        // Form validation
        document.getElementById('registerForm').addEventListener('submit', function(e) {
            const password = document.querySelector('input[name="password"]').value;
            const passwordConfirm = document.querySelector('input[name="password_confirm"]').value;
            
            if (password !== passwordConfirm) {
                e.preventDefault();
                alert('Mật khẩu xác nhận không khớp!');
                return false;
            }
            
            // Hiển thị loading
            const submitBtn = document.getElementById('submitBtn');
            submitBtn.textContent = 'Đang xử lý...';
            submitBtn.disabled = true;
        });
    </script>
</body>
</html>