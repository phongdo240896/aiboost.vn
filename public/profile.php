<?php
require_once __DIR__ . '/../app/config.php';
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/auth.php';
require_once __DIR__ . '/../app/middleware.php';

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Require login to access profile
Middleware::requireLogin();

// Log activity
Middleware::logActivity('view_profile');

// Get user data using Auth class
$userData = Auth::getUser();

if (!$userData) {
    Auth::logout();
    header('Location: ' . url('login'));
    exit;
}

$userId = $userData['id'];
$userName = $userData['full_name'] ?? 'User';

// Get balance from wallets table
$currentBalance = 0;
try {
    $walletInfo = $db->select('wallets', '*', ['user_id' => $userId]);
    if ($walletInfo && count($walletInfo) > 0) {
        $currentBalance = (int)$walletInfo[0]['balance'];
    }
} catch (Exception $e) {
    error_log('Get wallet balance error: ' . $e->getMessage());
}

// Get current subscription from subscriptions table - SỬA LỖI QUERY
$currentSubscription = null;
$currentPlan = 'Free'; // Default plan
try {
    $pdo = $db->getPdo();
    
    // Query đơn giản hơn để debug
    $stmt = $pdo->prepare("
        SELECT * FROM subscriptions 
        WHERE user_id = ? AND status = 'active' AND end_date > NOW() 
        ORDER BY end_date DESC 
        LIMIT 1
    ");
    $stmt->execute([$userId]);
    $currentSubscription = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($currentSubscription) {
        $currentPlan = $currentSubscription['plan_name'];
        
        // Log để debug
        error_log("Found subscription: " . print_r($currentSubscription, true));
    } else {
        // Check if user has any subscription (even expired)
        $stmt = $pdo->prepare("
            SELECT * FROM subscriptions 
            WHERE user_id = ? 
            ORDER BY created_at DESC 
            LIMIT 1
        ");
        $stmt->execute([$userId]);
        $anySubscription = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($anySubscription) {
            error_log("Found expired/inactive subscription: " . print_r($anySubscription, true));
        } else {
            error_log("No subscription found for user_id: " . $userId);
        }
    }
    
} catch (Exception $e) {
    error_log('Get subscription error: ' . $e->getMessage());
}

// Xử lý form submissions
$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    try {
        if ($_POST['action'] === 'update_profile') {
            $full_name = trim($_POST['full_name'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $phone = trim($_POST['phone'] ?? '');
            
            if (empty($full_name)) {
                $error_message = "Họ tên không được để trống";
            } elseif (empty($email)) {
                $error_message = "Email không được để trống";
            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $error_message = "Email không hợp lệ";
            } else {
                // Check email duplicate - SỬA LỖI: Dùng select thay vì selectOne
                $existingUsers = $db->select('users', '*', ['email' => $email]);
                $existingUser = !empty($existingUsers) ? $existingUsers[0] : null;
                
                if ($existingUser && $existingUser['id'] != $userId) {
                    $error_message = "Email đã được sử dụng";
                } else {
                    // Update user
                    $result = $db->update('users', [
                        'full_name' => $full_name,
                        'email' => $email,
                        'phone' => $phone,
                        'updated_at' => date('Y-m-d H:i:s')
                    ], ['id' => $userId]);
                    
                    if ($result) {
                        $success_message = "Cập nhật thông tin thành công!";
                        // Update session
                        $userData['full_name'] = $full_name;
                        $userData['email'] = $email;
                        $userData['phone'] = $phone;
                        $_SESSION['user_data'] = $userData;
                    } else {
                        $error_message = "Có lỗi xảy ra khi cập nhật";
                    }
                }
            }
        }
        
        elseif ($_POST['action'] === 'change_password') {
            $current_password = $_POST['current_password'] ?? '';
            $new_password = $_POST['new_password'] ?? '';
            $confirm_password = $_POST['confirm_password'] ?? '';
            
            if (empty($current_password)) {
                $error_message = "Vui lòng nhập mật khẩu hiện tại";
            } elseif (!password_verify($current_password, $userData['password'])) {
                $error_message = "Mật khẩu hiện tại không đúng";
            } elseif (empty($new_password)) {
                $error_message = "Vui lòng nhập mật khẩu mới";
            } elseif (strlen($new_password) < 6) {
                $error_message = "Mật khẩu mới phải có ít nhất 6 ký tự";
            } elseif ($new_password !== $confirm_password) {
                $error_message = "Xác nhận mật khẩu không khớp";
            } else {
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                $result = $db->update('users', [
                    'password' => $hashed_password,
                    'updated_at' => date('Y-m-d H:i:s')
                ], ['id' => $userId]);
                
                if ($result) {
                    $success_message = "Đổi mật khẩu thành công!";
                } else {
                    $error_message = "Có lỗi xảy ra khi đổi mật khẩu";
                }
            }
        }
        
    } catch (Exception $e) {
        error_log("Profile form error: " . $e->getMessage());
        $error_message = "Có lỗi xảy ra: " . $e->getMessage();
    }
}

// Lấy stats và transactions từ wallet_transactions table
$user_stats = [
    'total_purchases' => 0, 
    'total_spent' => 0, 
    'total_deposited' => 0,
    'total_transactions' => 0
];
$recent_transactions = [];

try {
    $pdo = $db->getPdo();
    
    // Tính tổng nạp tiền từ wallet_transactions (type = 'deposit' và amount_vnd)
    $stmt = $pdo->prepare("
        SELECT 
            COALESCE(SUM(CASE WHEN type = 'deposit' AND amount_vnd IS NOT NULL THEN amount_vnd ELSE 0 END), 0) as total_deposited_vnd,
            COALESCE(SUM(CASE WHEN type = 'deposit' THEN amount_xu ELSE 0 END), 0) as total_deposited_xu,
            COALESCE(SUM(CASE WHEN type IN ('withdraw', 'purchase', 'payment') THEN amount_xu ELSE 0 END), 0) as total_spent_xu,
            COUNT(*) as total_transactions
        FROM wallet_transactions 
        WHERE user_id = ? AND status = 'completed'
    ");
    $stmt->execute([$userId]);
    $statsResult = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($statsResult) {
        $user_stats['total_deposited'] = (float)($statsResult['total_deposited_vnd'] ?? 0);
        $user_stats['total_spent'] = (float)($statsResult['total_spent_xu'] ?? 0);
        $user_stats['total_transactions'] = (int)($statsResult['total_transactions'] ?? 0);
    }
    
    // Get recent wallet transactions
    $stmt = $pdo->prepare("
        SELECT * FROM wallet_transactions 
        WHERE user_id = ? 
        ORDER BY created_at DESC 
        LIMIT 10
    ");
    $stmt->execute([$userId]);
    $recent_transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    error_log("Profile stats error: " . $e->getMessage());
}

$pageTitle = "Hồ Sơ Cá Nhân";
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
        
        .content-container {
            padding-top: 1rem !important;
        }
        
        @media (min-width: 1024px) {
            .content-container {
                padding-top: 1.5rem !important;
            }
        }
        
        .profile-card { 
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); 
        }
        .profile-stats { 
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); 
        }
        .subscription-card { 
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); 
        }
        .form-section { 
            background: white; 
            border-radius: 12px; 
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1); 
            transition: all 0.3s ease;
        }
        .form-section:hover {
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
        }
        .notification { 
            animation: slideInDown 0.5s ease-out; 
        }
        @keyframes slideInDown { 
            from { transform: translateY(-100%); opacity: 0; } 
            to { transform: translateY(0); opacity: 1; } 
        }
        .tab-button.active { 
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); 
            color: white; 
        }
        .tab-pane { 
            display: none; 
        }
        .tab-pane.active { 
            display: block; 
        }
        .transaction-item {
            transition: all 0.2s ease;
        }
        .transaction-item:hover {
            background-color: #f8fafc;
            transform: translateX(4px);
        }
        
        /* XU Currency Styling */
        .xu-currency {
            background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            font-weight: bold;
        }
        
        .xu-icon {
            background: linear-gradient(135deg, #fbbf24 0%, #f59e0b 100%);
            width: 20px;
            height: 20px;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 11px;
            font-weight: bold;
            margin-right: 6px;
            text-shadow: 0 1px 2px rgba(0, 0, 0, 0.2);
            border: 1px solid #f59e0b;
            box-shadow: 0 1px 3px rgba(245, 158, 11, 0.3);
        }
        
        .xu-icon-large {
            width: 24px;
            height: 24px;
            font-size: 13px;
        }
        
        /* Transaction type indicators */
        .transaction-deposit { border-left: 4px solid #10b981; }
        .transaction-purchase { border-left: 4px solid #ef4444; }
        .transaction-refund { border-left: 4px solid #f59e0b; }
        
        /* Debug info styling */
        .debug-info {
            background: #f3f4f6;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            padding: 12px;
            margin: 8px 0;
            font-family: monospace;
            font-size: 12px;
            color: #374151;
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
            
            <!-- DEBUG INFO - TEMPORARY -->
            <?php if (isset($_GET['debug'])): ?>
            <div class="debug-info mb-4">
                <strong>🔍 DEBUG SUBSCRIPTION INFO:</strong><br>
                User ID: <?php echo $userId; ?><br>
                Current Plan: <?php echo $currentPlan; ?><br>
                Current Subscription: <?php echo $currentSubscription ? 'FOUND' : 'NOT FOUND'; ?><br>
                <?php if ($currentSubscription): ?>
                    Plan Name: <?php echo $currentSubscription['plan_name']; ?><br>
                    Status: <?php echo $currentSubscription['status']; ?><br>
                    End Date: <?php echo $currentSubscription['end_date']; ?><br>
                    Credits Remaining: <?php echo $currentSubscription['credits_remaining'] ?? 'N/A'; ?><br>
                    Credits Total: <?php echo $currentSubscription['credits_total'] ?? 'N/A'; ?><br>
                <?php endif; ?>
            </div>
            <?php endif; ?>
            
            <!-- Notifications -->
            <?php if ($success_message): ?>
            <div class="notification mb-6 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-lg">
                <div class="flex items-center">
                    <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                    </svg>
                    <span><?php echo htmlspecialchars($success_message); ?></span>
                </div>
            </div>
            <?php endif; ?>
            
            <?php if ($error_message): ?>
            <div class="notification mb-6 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg">
                <div class="flex items-center">
                    <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                    </svg>
                    <span><?php echo htmlspecialchars($error_message); ?></span>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Page Header -->
            <div class="mb-6">
                <h1 class="text-2xl font-bold text-gray-900">👤 Hồ Sơ Cá Nhân</h1>
                <p class="text-gray-600 mt-1">Quản lý thông tin tài khoản và cài đặt của bạn</p>
            </div>
            
            <!-- Profile Overview Cards -->
            <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-6 mb-8">
                
                <!-- Profile Card -->
                <div class="profile-card p-6 rounded-xl text-white">
                    <div class="flex items-center space-x-4">
                        <div class="w-16 h-16 bg-white bg-opacity-20 rounded-full flex items-center justify-center">
                            <span class="text-2xl font-bold"><?php echo strtoupper(substr($userData['full_name'] ?? 'U', 0, 1)); ?></span>
                        </div>
                        <div>
                            <h3 class="text-xl font-bold"><?php echo htmlspecialchars($userData['full_name'] ?? 'Unknown User'); ?></h3>
                            <p class="text-blue-100"><?php echo htmlspecialchars(ucfirst($userData['role'] ?? 'user')); ?></p>
                            <p class="text-blue-100 text-sm">
                                📅 Tham gia <?php echo isset($userData['created_at']) ? date('d/m/Y', strtotime($userData['created_at'])) : 'N/A'; ?>
                            </p>
                        </div>
                    </div>
                </div>
                
                <!-- Stats Card - UPDATED WITH PROPER CALCULATIONS -->
                <div class="profile-stats p-6 rounded-xl text-white">
                    <h3 class="text-lg font-bold mb-4">📊 Thống Kê</h3>
                    <div class="space-y-2">
                        <div class="flex justify-between items-center">
                            <span>Số dư hiện tại:</span>
                            <span class="font-bold flex items-center">
                                <span class="xu-icon">X</span>
                                <?php echo number_format($currentBalance); ?> <span class="xu-currency">XU</span>
                            </span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span>Tổng nạp tiền:</span>
                            <span class="font-bold text-green-300">
                                <?php echo number_format($user_stats['total_deposited']); ?>₫
                            </span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span>Tổng chi tiêu:</span>
                            <span class="font-bold flex items-center">
                                <span class="xu-icon">X</span>
                                <?php echo number_format($user_stats['total_spent']); ?> <span class="xu-currency">XU</span>
                            </span>
                        </div>
                        <div class="flex justify-between">
                            <span>Số giao dịch:</span>
                            <span class="font-bold"><?php echo $user_stats['total_transactions']; ?></span>
                        </div>
                    </div>
                </div>
                
                <!-- Subscription Card - SỬA LỖI HIỂN THỊ -->
                <div class="subscription-card p-6 rounded-xl text-white">
                    <h3 class="text-lg font-bold mb-4">💎 Gói Đăng Ký</h3>
                    <?php if ($currentSubscription && $currentPlan !== 'Free'): ?>
                        <div>
                            <p class="font-bold text-lg flex items-center">
                                <?php
                                $planIcons = [
                                    'Free' => '🆓',
                                    'Basic' => '📦', 
                                    'Standard' => '⚡',
                                    'Pro' => '⭐',
                                    'Ultra' => '👑',
                                    'Premium' => '💎'
                                ];
                                $icon = $planIcons[$currentPlan] ?? '📦';
                                echo $icon . ' ' . htmlspecialchars($currentPlan);
                                ?>
                            </p>
                            <p class="text-blue-100">
                                Hết hạn: <?php echo date('d/m/Y', strtotime($currentSubscription['end_date'])); ?>
                            </p>
                            <div class="mt-2 space-y-1">
                                <?php if (isset($currentSubscription['credits_remaining']) && isset($currentSubscription['credits_total'])): ?>
                                    <div class="text-blue-100 text-sm">
                                        Credits: <?php echo number_format($currentSubscription['credits_remaining']); ?>/<?php echo number_format($currentSubscription['credits_total']); ?> XU
                                    </div>
                                <?php endif; ?>
                                <span class="inline-block bg-white bg-opacity-20 px-2 py-1 rounded text-xs">
                                    ✅ Đang hoạt động
                                </span>
                            </div>
                        </div>
                    <?php else: ?>
                        <div>
                            <p class="text-lg flex items-center">
                                🆓 Gói Miễn Phí
                            </p>
                            <p class="text-blue-100 mb-3">Nâng cấp để sử dụng nhiều tính năng hơn</p>
                            <a href="<?php echo url('pricing'); ?>" class="inline-block bg-white text-blue-600 px-4 py-2 rounded-lg font-medium hover:bg-blue-50 transition-colors">
                                Nâng Cấp Ngay
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Tab Navigation -->
            <div class="mb-6">
                <div class="border-b border-gray-200">
                    <nav class="-mb-px flex space-x-8">
                        <button class="tab-button active py-2 px-1 border-b-2 border-transparent font-medium text-sm" 
                                data-tab="profile-info">
                            👤 Thông Tin Cá Nhân
                        </button>
                        <button class="tab-button py-2 px-1 border-b-2 border-transparent font-medium text-sm text-gray-500 hover:text-gray-700" 
                                data-tab="security">
                            🔒 Bảo Mật
                        </button>
                        <button class="tab-button py-2 px-1 border-b-2 border-transparent font-medium text-sm text-gray-500 hover:text-gray-700" 
                                data-tab="transactions">
                            📊 Lịch Sử Giao Dịch
                        </button>
                    </nav>
                </div>
            </div>
            
            <!-- Tab Contents -->
            <div class="tab-content">
                
                <!-- Profile Information Tab -->
                <div id="profile-info" class="tab-pane active">
                    <div class="form-section p-6">
                        <h2 class="text-2xl font-bold text-gray-900 mb-6">📝 Cập Nhật Thông Tin</h2>
                        
                        <form method="POST" class="space-y-6">
                            <input type="hidden" name="action" value="update_profile">
                            
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <label for="full_name" class="block text-sm font-medium text-gray-700 mb-2">
                                        Họ và Tên <span class="text-red-500">*</span>
                                    </label>
                                    <input type="text" 
                                           id="full_name" 
                                           name="full_name" 
                                           value="<?php echo htmlspecialchars($userData['full_name']); ?>"
                                           required
                                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                </div>
                                
                                <div>
                                    <label for="email" class="block text-sm font-medium text-gray-700 mb-2">
                                        Email <span class="text-red-500">*</span>
                                    </label>
                                    <input type="email" 
                                           id="email" 
                                           name="email" 
                                           value="<?php echo htmlspecialchars($userData['email']); ?>"
                                           required
                                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                </div>
                                
                                <div>
                                    <label for="phone" class="block text-sm font-medium text-gray-700 mb-2">
                                        Số Điện Thoại
                                    </label>
                                    <input type="tel" 
                                           id="phone" 
                                           name="phone" 
                                           value="<?php echo htmlspecialchars($userData['phone'] ?? ''); ?>"
                                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                           placeholder="0987654321">
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">
                                        Trạng Thái Tài Khoản
                                    </label>
                                    <div class="flex items-center space-x-2">
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium 
                                            <?php echo $userData['status'] === 'active' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                                            <?php 
                                            $statusText = [
                                                'active' => '✅ Hoạt động',
                                                'inactive' => '⏸️ Tạm ngưng', 
                                                'banned' => '🚫 Bị khóa'
                                            ];
                                            echo $statusText[$userData['status']] ?? $userData['status'];
                                            ?>
                                        </span>
                                        <?php if ($userData['email_verified'] ?? false): ?>
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                                ✉️ Email đã xác thực
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="flex justify-end">
                                <button type="submit" 
                                        class="bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-6 rounded-lg transition-colors">
                                    💾 Lưu Thay Đổi
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Security Tab -->
                <div id="security" class="tab-pane">
                    <div class="form-section p-6">
                        <h2 class="text-2xl font-bold text-gray-900 mb-6">🔒 Đổi Mật Khẩu</h2>
                        
                        <form method="POST" class="space-y-6">
                            <input type="hidden" name="action" value="change_password">
                            
                            <div>
                                <label for="current_password" class="block text-sm font-medium text-gray-700 mb-2">
                                    Mật Khẩu Hiện Tại <span class="text-red-500">*</span>
                                </label>
                                <input type="password" 
                                       id="current_password" 
                                       name="current_password" 
                                       required
                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                            </div>
                            
                            <div>
                                <label for="new_password" class="block text-sm font-medium text-gray-700 mb-2">
                                    Mật Khẩu Mới <span class="text-red-500">*</span>
                                </label>
                                <input type="password" 
                                       id="new_password" 
                                       name="new_password" 
                                       required
                                       minlength="6"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                <p class="text-gray-500 text-xs mt-1">Mật khẩu phải có ít nhất 6 ký tự</p>
                            </div>
                            
                            <div>
                                <label for="confirm_password" class="block text-sm font-medium text-gray-700 mb-2">
                                    Xác Nhận Mật Khẩu Mới <span class="text-red-500">*</span>
                                </label>
                                <input type="password" 
                                       id="confirm_password" 
                                       name="confirm_password" 
                                       required
                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                            </div>
                            
                            <div class="flex justify-end">
                                <button type="submit" 
                                        class="bg-red-600 hover:bg-red-700 text-white font-medium py-2 px-6 rounded-lg transition-colors">
                                    🔑 Đổi Mật Khẩu
                                </button>
                            </div>
                        </form>
                        
                        <!-- Security Tips -->
                        <div class="mt-8 bg-blue-50 border border-blue-200 rounded-lg p-4">
                            <h3 class="text-lg font-semibold text-blue-900 mb-2">💡 Lời Khuyên Bảo Mật</h3>
                            <ul class="text-blue-800 text-sm space-y-1">
                                <li>• Sử dụng mật khẩu mạnh có ít nhất 8 ký tự</li>
                                <li>• Kết hợp chữ hoa, chữ thường, số và ký tự đặc biệt</li>
                                <li>• Không sử dụng thông tin cá nhân dễ đoán</li>
                                <li>• Thay đổi mật khẩu định kỳ</li>
                                <li>• Không chia sẻ mật khẩu với người khác</li>
                            </ul>
                        </div>
                    </div>
                </div>
                
                <!-- Transactions History Tab - UPDATED WITH WALLET_TRANSACTIONS -->
                <div id="transactions" class="tab-pane">
                    <div class="form-section p-6">
                        <h2 class="text-2xl font-bold text-gray-900 mb-6">📊 Lịch Sử Giao Dịch</h2>
                        
                        <?php if (!empty($recent_transactions)): ?>
                            <div class="space-y-4">
                                <?php foreach ($recent_transactions as $transaction): ?>
                                <div class="transaction-item transaction-<?php echo $transaction['type']; ?> flex items-center justify-between p-4 bg-gray-50 rounded-lg border-l-4">
                                    <div class="flex items-center">
                                        <div class="w-12 h-12 rounded-full flex items-center justify-center mr-4 
                                            <?php 
                                            $bgClass = match($transaction['type']) {
                                                'deposit' => 'bg-green-100 text-green-600',
                                                'purchase' => 'bg-red-100 text-red-600', 
                                                'refund' => 'bg-yellow-100 text-yellow-600',
                                                default => 'bg-gray-100 text-gray-600'
                                            };
                                            echo $bgClass;
                                            ?>">
                                            <?php
                                            $icon = match($transaction['type']) {
                                                'deposit' => '💰',
                                                'purchase' => '🛒',
                                                'refund' => '↩️',
                                                'withdraw' => '💸',
                                                default => '💳'
                                            };
                                            echo $icon;
                                            ?>
                                        </div>
                                        <div>
                                            <p class="font-medium"><?php echo htmlspecialchars($transaction['description'] ?? 'Giao dịch'); ?></p>
                                            <p class="text-sm text-gray-500">
                                                <?php echo date('d/m/Y H:i', strtotime($transaction['created_at'])); ?>
                                                <?php if ($transaction['reference_id'] ?? ''): ?>
                                                    • Ref: <?php echo htmlspecialchars($transaction['reference_id']); ?>
                                                <?php endif; ?>
                                            </p>
                                            <?php if ($transaction['amount_vnd'] ?? 0): ?>
                                                <p class="text-xs text-gray-400">
                                                    VND: <?php echo number_format($transaction['amount_vnd']); ?>đ
                                                </p>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <div class="text-right">
                                        <p class="font-bold 
                                            <?php echo in_array($transaction['type'], ['deposit', 'refund']) ? 'text-green-600' : 'text-red-600'; ?> 
                                            flex items-center justify-end">
                                            <?php echo in_array($transaction['type'], ['deposit', 'refund']) ? '+' : '-'; ?>
                                            <span class="xu-icon">X</span>
                                            <?php echo number_format($transaction['amount_xu']); ?> 
                                            <span class="xu-currency ml-1">XU</span>
                                        </p>
                                        <div class="text-sm text-gray-500 flex items-center justify-end mt-1">
                                            <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium
                                                <?php 
                                                $statusClass = match($transaction['status']) {
                                                    'completed' => 'bg-green-100 text-green-800',
                                                    'pending' => 'bg-yellow-100 text-yellow-800',
                                                    'failed' => 'bg-red-100 text-red-800',
                                                    'cancelled' => 'bg-gray-100 text-gray-800',
                                                    default => 'bg-blue-100 text-blue-800'
                                                };
                                                echo $statusClass;
                                                ?>">
                                                <?php 
                                                $statusText = [
                                                    'completed' => '✅ Hoàn thành',
                                                    'pending' => '⏳ Đang xử lý',
                                                    'failed' => '❌ Thất bại',
                                                    'cancelled' => '🚫 Đã hủy'
                                                ];
                                                echo $statusText[$transaction['status']] ?? ucfirst($transaction['status']);
                                                ?>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            
                            <div class="mt-6 text-center">
                                <a href="<?php echo url('wallet'); ?>" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-blue-600 bg-blue-100 hover:bg-blue-200 transition-colors">
                                    📊 Xem Tất Cả Giao Dịch
                                </a>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-12">
                                <div class="text-gray-300 text-6xl mb-4">💳</div>
                                <h3 class="text-lg font-medium text-gray-900 mb-2">Chưa có giao dịch nào</h3>
                                <p class="text-gray-500 mb-4">Lịch sử giao dịch của bạn sẽ hiển thị ở đây</p>
                                <a href="<?php echo url('topup'); ?>" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 transition-colors">
                                    💰 Nạp Tiền Ngay
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
            </div>
            
        </div>
    </div>

    <!-- Include Footer -->
    <?php include __DIR__ . '/partials/footer.php'; ?>
    
    <!-- JavaScript -->
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        console.log('✅ Profile page loaded successfully!');
        
        // Tab functionality
        const tabButtons = document.querySelectorAll('.tab-button');
        const tabPanes = document.querySelectorAll('.tab-pane');
        
        tabButtons.forEach(button => {
            button.addEventListener('click', function() {
                const targetTab = this.getAttribute('data-tab');
                
                // Remove active class from all tabs and panes
                tabButtons.forEach(btn => {
                    btn.classList.remove('active');
                    btn.classList.add('text-gray-500', 'hover:text-gray-700');
                    btn.classList.remove('text-white');
                });
                
                tabPanes.forEach(pane => {
                    pane.classList.remove('active');
                });
                
                // Add active class to clicked tab
                this.classList.add('active');
                this.classList.remove('text-gray-500', 'hover:text-gray-700');
                
                // Show target pane
                const targetPane = document.getElementById(targetTab);
                if (targetPane) {
                    targetPane.classList.add('active');
                }
            });
        });
        
        // Auto-hide notifications after 5 seconds
        const notifications = document.querySelectorAll('.notification');
        notifications.forEach(notification => {
            setTimeout(() => {
                notification.style.opacity = '0';
                notification.style.transform = 'translateY(-20px)';
                setTimeout(() => {
                    notification.remove();
                }, 300);
            }, 5000);
        });
        
        // Password confirmation validation
        const newPassword = document.getElementById('new_password');
        const confirmPassword = document.getElementById('confirm_password');
        
        if (newPassword && confirmPassword) {
            function validatePassword() {
                if (newPassword.value !== confirmPassword.value) {
                    confirmPassword.setCustomValidity('Mật khẩu xác nhận không khớp');
                } else {
                    confirmPassword.setCustomValidity('');
                }
            }
            
            newPassword.addEventListener('input', validatePassword);
            confirmPassword.addEventListener('input', validatePassword);
        }
    });
    
    console.log('✅ Profile page with subscription fix loaded!');
    console.log('User: <?php echo htmlspecialchars($userName); ?>');
    console.log('Balance: <?php echo number_format($currentBalance); ?> XU');
    console.log('Current Plan: <?php echo htmlspecialchars($currentPlan); ?>');
    <?php if ($currentSubscription): ?>
    console.log('Subscription found: Yes');
    console.log('Plan name: <?php echo htmlspecialchars($currentSubscription['plan_name']); ?>');
    console.log('Status: <?php echo htmlspecialchars($currentSubscription['status']); ?>');
    console.log('End date: <?php echo htmlspecialchars($currentSubscription['end_date']); ?>');
    <?php else: ?>
    console.log('Subscription found: No');
    <?php endif; ?>
    </script>
</body>
</html>