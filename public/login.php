<?php
require_once __DIR__ . '/../app/config.php';
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/auth.php';

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Kiểm tra cookie remember me khi tải trang
if (!isset($_SESSION['user_id']) && isset($_COOKIE['remember_token'])) {
    $result = Auth::loginWithRememberToken($_COOKIE['remember_token']);
    if ($result['success']) {
        header('Location: /dashboard');
        exit();
    }
}

// Redirect if already logged in
if (isset($_SESSION['user_id']) && !empty($_SESSION['user_id'])) {
    header('Location: /dashboard');
    exit();
}

$errors = [];
$success = '';

// Check for registration success message
if (isset($_GET['registered']) && $_GET['registered'] === 'true') {
    $success = 'Đăng ký thành công! Vui lòng đăng nhập với email và mật khẩu.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $rememberMe = isset($_POST['remember_me']) && $_POST['remember_me'] === '1';
        
        if (empty($email)) {
            $errors['email'] = 'Vui lòng nhập email';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Email không hợp lệ';
        }
        
        if (empty($password)) {
            $errors['password'] = 'Vui lòng nhập mật khẩu';
        }
        
        if (empty($errors)) {
            // Gọi Auth::login với tùy chọn remember me
            $result = Auth::login($email, $password, $rememberMe);
            
            if ($result['success']) {
                $success = $result['message'];
                header("refresh:1;url=/dashboard");
            } else {
                $errors['general'] = $result['message'];
            }
        }
        
    } catch (Exception $e) {
        error_log("Login exception: " . $e->getMessage());
        $errors['general'] = 'Lỗi hệ thống: ' . $e->getMessage();
    }
}

// Generate CSRF token
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf_token'];
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đăng Nhập - AIboost.vn</title>
    <script src="https://cdn.tailwindcss.com"></script>
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
            <h2 class="text-3xl font-bold text-gray-900 mb-2">🔐 Đăng Nhập</h2>
            <p class="text-gray-600">Nhập email và mật khẩu để tiếp tục</p>
        </div>

        <!-- Success Message -->
        <?php if (!empty($success)): ?>
        <div class="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg mb-6">
            <div class="flex items-center">
                <span class="text-xl mr-2">✅</span>
                <div>
                    <div class="font-semibold">Thành công!</div>
                    <div class="text-sm"><?php echo htmlspecialchars($success); ?></div>
                    <?php if (!isset($_GET['registered'])): ?>
                    <div class="text-xs mt-1">Đang chuyển hướng...</div>
                    <?php endif; ?>
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

        <!-- Login Form -->
        <form method="POST" class="space-y-6" id="loginForm">
            <!-- CSRF Token -->
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
            
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Email</label>
                <input 
                    type="email" 
                    name="email"
                    value="<?php echo htmlspecialchars($_POST['email'] ?? $_GET['email'] ?? ''); ?>"
                    placeholder="example@gmail.com"
                    class="w-full px-4 py-3 border <?php echo isset($errors['email']) ? 'border-red-300' : 'border-gray-300'; ?> rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                    required
                    <?php echo !empty($success) && !isset($_GET['registered']) ? 'disabled' : ''; ?>
                >
                <?php if (isset($errors['email'])): ?>
                <p class="mt-1 text-sm text-red-600"><?php echo htmlspecialchars($errors['email']); ?></p>
                <?php endif; ?>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Mật khẩu</label>
                <input 
                    type="password" 
                    name="password"
                    placeholder="••••••••"
                    class="w-full px-4 py-3 border <?php echo isset($errors['password']) ? 'border-red-300' : 'border-gray-300'; ?> rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                    required
                    <?php echo !empty($success) && !isset($_GET['registered']) ? 'disabled' : ''; ?>
                >
                <?php if (isset($errors['password'])): ?>
                <p class="mt-1 text-sm text-red-600"><?php echo htmlspecialchars($errors['password']); ?></p>
                <?php endif; ?>
            </div>

            <!-- Remember Me & Forgot Password -->
            <div class="flex items-center justify-between">
                <div class="flex items-center">
                    <input 
                        id="remember_me" 
                        name="remember_me" 
                        type="checkbox" 
                        value="1"
                        class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded"
                        <?php echo !empty($success) && !isset($_GET['registered']) ? 'disabled' : ''; ?>
                    >
                    <label for="remember_me" class="ml-2 block text-sm text-gray-700">
                        Ghi nhớ đăng nhập
                    </label>
                </div>
                <div class="text-sm">
                    <a href="/forgot-password" class="text-blue-600 hover:text-blue-700">Quên mật khẩu?</a>
                </div>
            </div>

            <button 
                type="submit" 
                class="w-full bg-blue-600 text-white py-3 rounded-lg hover:bg-blue-700 transition-colors font-semibold disabled:opacity-50"
                <?php echo !empty($success) && !isset($_GET['registered']) ? 'disabled' : ''; ?>
                id="submitBtn"
            >
                <?php echo !empty($success) && !isset($_GET['registered']) ? 'Đang chuyển hướng...' : 'Đăng nhập'; ?>
            </button>
        </form>

        <!-- Register Link -->
        <div class="mt-6 text-center">
            <p class="text-gray-600">
                Chưa có tài khoản? 
                <a href="/register" class="text-blue-600 hover:text-blue-700 font-medium">Đăng ký miễn phí</a>
            </p>
        </div>

        <!-- Back to home -->
        <div class="mt-4 text-center">
            <a href="/" class="text-gray-500 hover:text-gray-700 text-sm">← Quay lại trang chủ</a>
        </div>
    </div>

    <script>
        // Auto redirect countdown
        <?php if (!empty($success) && !isset($_GET['registered'])): ?>
        let countdown = 1;
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
        document.getElementById('loginForm').addEventListener('submit', function(e) {
            const submitBtn = document.getElementById('submitBtn');
            submitBtn.textContent = 'Đang đăng nhập...';
            submitBtn.disabled = true;
        });
    </script>
</body>
</html>