<?php
// Bắt đầu session nếu chưa có
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Require database and auth
require_once __DIR__ . '/../../app/config.php';
require_once __DIR__ . '/../../app/db.php';
require_once __DIR__ . '/../../app/auth.php';

// Check if user is logged in
$isLoggedIn = Auth::isLoggedIn();
$userData = null;
$balance = 0;
$currentPlan = 'Free';
$unreadNotifications = 0;
$recentNotifications = [];

if ($isLoggedIn) {
    $userData = Auth::getUser();
    $userId = $userData['id'] ?? null;
    
    if ($userId) {
        // Get balance from wallets table
        try {
            $walletInfo = $db->select('wallets', '*', ['user_id' => $userId]);
            if ($walletInfo && count($walletInfo) > 0) {
                $balance = (int)$walletInfo[0]['balance'];
            } else {
                // Create wallet if not exists with default 500 XU
                try {
                    $db->insert('wallets', [
                        'user_id' => $userId,
                        'balance' => 500,
                        'created_at' => date('Y-m-d H:i:s'),
                        'updated_at' => date('Y-m-d H:i:s')
                    ]);
                    $balance = 500;
                } catch (Exception $e) {
                    error_log('Create wallet error: ' . $e->getMessage());
                }
            }
        } catch (Exception $e) {
            error_log('Get wallet balance error: ' . $e->getMessage());
        }
        
        // Get current active subscription plan
        try {
            $pdo = $db->getPdo();
            $stmt = $pdo->prepare("
                SELECT plan_name, end_date 
                FROM subscriptions 
                WHERE user_id = ? AND status = 'active' AND end_date > NOW() 
                ORDER BY end_date DESC 
                LIMIT 1
            ");
            $stmt->execute([$userId]);
            $subscription = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($subscription) {
                $currentPlan = $subscription['plan_name'];
                $planEndDate = $subscription['end_date'];
            }
        } catch (Exception $e) {
            error_log('Get subscription error: ' . $e->getMessage());
        }
        
        // Get unread notifications count
        try {
            require_once __DIR__ . '/../../app/NotificationManager.php';
            if (isset($db)) {
                NotificationManager::init($db);
            }
            
            $unreadNotifications = NotificationManager::getUnreadCount($userId);
            
            // Get recent 5 notifications for dropdown
            $pdo = $db->getPdo();
            $stmt = $pdo->prepare("
                SELECT n.*, un.is_read, un.read_at
                FROM notifications n
                INNER JOIN user_notifications un ON n.id = un.notification_id
                WHERE un.user_id = ? 
                AND n.status = 'active'
                AND (n.expires_at IS NULL OR n.expires_at > NOW())
                ORDER BY un.is_read ASC, n.priority DESC, n.created_at DESC
                LIMIT 5
            ");
            $stmt->execute([$userId]);
            $recentNotifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (Exception $e) {
            error_log('Get notifications error: ' . $e->getMessage());
        }
    }
}

// Page titles mapping
$pageTitles = [
    'index.php' => 'Trang Chủ',
    'dashboard.php' => 'Dashboard', 
    'topup.php' => 'Nạp Xu',
    'image.php' => 'Tạo Ảnh AI',
    'video.php' => 'Video AI',
    'content.php' => 'Nội Dung AI',
    'voice.php' => 'Voice AI',
    'assistant.php' => 'AI Assistant',
    'wallet.php' => 'Ví & Lịch Sử',
    'stats.php' => 'Thống Kê',
    'settings.php' => 'Cài Đặt',
    'profile.php' => 'Hồ Sơ',
    'support.php' => 'Hỗ Trợ',
    'pricing.php' => 'Bảng Giá',
    'admin/index.php' => 'Admin Panel',
    'login.php' => 'Đăng Nhập',
    'register.php' => 'Đăng Ký'
];

// Get current page
$currentPage = basename($_SERVER['PHP_SELF']);
$pageTitle = $pageTitles[$currentPage] ?? 'AIboost.vn';

// Get user initials for avatar
$userInitials = 'U';
$userName = 'User';
$userRole = 'Member';

if ($userData) {
    $userName = $userData['full_name'] ?? 'User';
    $userRole = ucfirst($userData['role'] ?? 'member');
    $userInitials = strtoupper(substr($userName, 0, 1));
}

// Plan colors and icons
$planInfo = getPlanInfo($currentPlan);

function getPlanInfo($planName) {
    $plans = [
        'Free' => ['color' => 'gray', 'icon' => '🆓', 'bg' => 'bg-gray-100', 'text' => 'text-gray-800'],
        'Standard' => ['color' => 'blue', 'icon' => '⚡', 'bg' => 'bg-blue-100', 'text' => 'text-blue-800'],
        'Pro' => ['color' => 'purple', 'icon' => '⭐', 'bg' => 'bg-purple-100', 'text' => 'text-purple-800'],
        'Ultra' => ['color' => 'gold', 'icon' => '👑', 'bg' => 'bg-yellow-100', 'text' => 'text-yellow-800'],
        'Premium' => ['color' => 'red', 'icon' => '💎', 'bg' => 'bg-red-100', 'text' => 'text-red-800']
    ];
    
    return $plans[$planName] ?? $plans['Free'];
}
?>

<!-- Header Component -->
<header class="bg-white shadow-sm border-b fixed top-0 left-0 right-0 z-40 w-full">
    <div class="w-full px-2 sm:px-4 lg:px-8">
        <div class="flex justify-between items-center h-14 sm:h-16">
            <!-- Left side - Mobile menu + Logo + Title -->
            <div class="flex items-center flex-1 min-w-0">
                <?php if ($isLoggedIn): ?>
                <!-- Mobile menu button -->
                <button class="lg:hidden p-1.5 sm:p-2 rounded-lg hover:bg-gray-100 flex-shrink-0" onclick="handleMobileMenu()">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>
                    </svg>
                </button>
                <?php endif; ?>
                
                <!-- Logo & Title -->
                <div class="flex items-center min-w-0 flex-1 ml-2">
                    <a href="<?php echo url(); ?>" class="flex items-center min-w-0">
                        <div class="w-7 h-7 sm:w-8 sm:h-8 bg-blue-600 rounded-lg flex items-center justify-center mr-2 flex-shrink-0">
                            <span class="text-white font-bold text-sm">🤖</span>
                        </div>
                        <h1 class="text-base sm:text-lg lg:text-xl font-bold text-gray-900 truncate">
                            <span class="hidden sm:inline"><?php echo htmlspecialchars($pageTitle); ?></span>
                            <span class="sm:hidden">Dashboard</span>
                        </h1>
                    </a>
                </div>
            </div>

            <!-- Right side -->
            <div class="flex items-center gap-1 sm:gap-2">
                <?php if ($isLoggedIn): ?>
                    
                    <!-- Notification Bell -->
                    <div class="relative" id="notificationContainer">
                        <button onclick="toggleNotificationDropdown()" 
                                id="notificationButton"
                                class="relative p-1.5 sm:p-2 rounded-lg hover:bg-gray-100 transition-colors"
                                title="Thông báo">
                            <svg class="w-5 h-5 sm:w-6 sm:h-6 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                      d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"></path>
                            </svg>
                            
                            <!-- Notification Badge -->
                            <?php if ($unreadNotifications > 0): ?>
                            <span id="notification-badge" 
                                  class="absolute -top-0.5 -right-0.5 bg-red-500 text-white text-xs rounded-full h-4 w-4 flex items-center justify-center text-[10px] font-bold animate-pulse">
                                <?php echo $unreadNotifications > 9 ? '9+' : $unreadNotifications; ?>
                            </span>
                            <?php endif; ?>
                        </button>
                    </div>
                    
                    <!-- Balance display -->
                    <div class="flex items-center bg-orange-50 px-2 py-1.5 rounded-lg border border-orange-200">
                        <span class="text-orange-600 font-semibold text-xs sm:text-sm flex items-center whitespace-nowrap">
                            <span class="text-[10px] sm:text-xs mr-1">💰</span>
                            <span><?php echo number_format($balance); ?></span>
                            <span class="text-[10px] sm:text-xs ml-0.5">XU</span>
                        </span>
                    </div>
                    
                    <!-- Topup button -->
                    <a href="<?php echo url('topup'); ?>" 
                       class="flex items-center justify-center bg-blue-600 text-white p-1.5 sm:px-3 sm:py-1.5 rounded-lg hover:bg-blue-700 transition-colors">
                        <svg class="w-4 h-4 sm:w-5 sm:h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                        </svg>
                        <span class="hidden sm:inline ml-1 text-sm">Nạp</span>
                    </a>
                    
                    <!-- User dropdown -->
                    <div class="relative">
                        <button onclick="handleDropdown()" 
                                class="flex items-center gap-1 hover:bg-gray-50 p-1 sm:p-1.5 rounded-lg transition-colors">
                            <!-- User avatar -->
                            <div class="w-7 h-7 sm:w-8 sm:h-8 bg-gradient-to-r from-blue-500 to-purple-600 rounded-full flex items-center justify-center flex-shrink-0">
                                <span class="text-white font-bold text-xs sm:text-sm"><?php echo $userInitials; ?></span>
                            </div>
                            <!-- Dropdown arrow -->
                            <svg class="w-3 h-3 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                            </svg>
                        </button>
                        
                        <!-- User Dropdown menu -->
                        <div class="absolute right-0 mt-2 w-48 sm:w-56 bg-white rounded-lg shadow-xl border border-gray-200 hidden z-50" id="headerUserDropdown">
                            <div class="py-2">
                                <!-- Mobile-only: Show user info at top -->
                                <div class="md:hidden px-4 py-3 border-b border-gray-200">
                                    <div class="font-medium text-gray-900"><?php echo htmlspecialchars($userName); ?></div>
                                    <div class="text-sm flex items-center mt-1">
                                        <span class="mr-1"><?php echo $planInfo['icon']; ?></span>
                                        <span class="<?php echo $planInfo['text']; ?> font-medium"><?php echo htmlspecialchars($currentPlan); ?></span>
                                    </div>
                                    <div class="text-xs text-orange-600 mt-2 flex items-center">
                                        <span class="xu-icon-dropdown">X</span>
                                        <?php echo number_format($balance); ?> <span class="xu-currency-dropdown">XU</span>
                                    </div>
                                </div>
                                
                                <!-- Show topup on mobile -->
                                <a href="<?php echo url('topup'); ?>" class="sm:hidden flex items-center px-4 py-3 text-sm text-blue-600 hover:bg-blue-50 border-b border-gray-200">
                                    <span class="mr-3">💳</span>
                                    Nạp xu
                                </a>
                                
                                <a href="<?php echo url('profile'); ?>" class="flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                    <span class="mr-3">👤</span>
                                    Hồ sơ cá nhân
                                </a>

                                <a href="<?php echo url('wallet'); ?>" class="flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                    <span class="mr-3">💼</span>
                                    Ví & Lịch sử
                                </a>
                                <a href="<?php echo url('topup'); ?>" class="hidden sm:flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                    <span class="mr-3">💳</span>
                                    Nạp xu
                                </a>
                                <a href="<?php echo url('pricing'); ?>" class="flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                    <span class="mr-3">📦</span>
                                    Nâng cấp gói
                                </a>
                                
                                <?php if ($userData && $userData['role'] === 'admin'): ?>
                                <hr class="my-2 border-gray-200">
                                <a href="<?php echo url('admin'); ?>" class="flex items-center px-4 py-2 text-sm text-purple-600 hover:bg-purple-50">
                                    <span class="mr-3">👑</span>
                                    Admin Panel
                                </a>
                                <?php endif; ?>
                                
                                <a href="<?php echo url('support'); ?>" class="flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                    <span class="mr-3">❓</span>
                                    Hỗ trợ
                                </a>
                                <hr class="my-2 border-gray-200">
                                <a href="<?php echo url('logout'); ?>" class="flex items-center px-4 py-2 text-sm text-red-600 hover:bg-red-50">
                                    <span class="mr-3">🚪</span>
                                    Đăng xuất
                                </a>
                            </div>
                        </div>
                    </div>
                    
                <?php else: ?>
                    <!-- Not logged in buttons -->
                    <div class="flex items-center gap-2">
                        <a href="<?php echo url('login'); ?>" 
                           class="px-3 py-1.5 text-sm font-medium text-gray-700 hover:text-gray-900 rounded-lg hover:bg-gray-100 transition-colors">
                            Đăng nhập
                        </a>
                        <a href="<?php echo url('register'); ?>" 
                           class="px-3 py-1.5 text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 rounded-lg transition-colors">
                            Đăng ký
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</header>

<!-- Spacer div to push content below fixed header -->
<div class="h-14 sm:h-16"></div>

<!-- Mobile Notification Modal (separate from overlay) -->
<div id="mobileNotificationModal" class="fixed inset-0 z-[100] hidden sm:hidden">
    <!-- Overlay background -->
    <div class="fixed inset-0 bg-black bg-opacity-50" onclick="closeNotificationModal()"></div>
    
    <!-- Modal content -->
    <div class="fixed inset-x-4 top-20 bg-white rounded-lg shadow-2xl border border-gray-200 max-h-[75vh] overflow-hidden">
        <div class="p-3 border-b border-gray-200 bg-gradient-to-r from-blue-50 to-purple-50">
            <div class="flex items-center justify-between">
                <h3 class="font-semibold text-gray-900 flex items-center">
                    <span class="text-lg mr-2">🔔</span>
                    <span class="text-base">Thông báo</span>
                    <?php if ($unreadNotifications > 0): ?>
                    <span class="ml-2 text-xs text-gray-600 bg-white px-2 py-1 rounded-full">
                        <?php echo $unreadNotifications; ?> chưa đọc
                    </span>
                    <?php endif; ?>
                </h3>
                <button onclick="closeNotificationModal()" class="p-1 hover:bg-white rounded">
                    <svg class="w-5 h-5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>
        </div>
        
        <div class="overflow-y-auto max-h-[55vh]">
            <?php if (empty($recentNotifications)): ?>
            <div class="px-4 py-8 text-center text-gray-500">
                <svg class="w-12 h-12 mx-auto mb-3 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                          d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"></path>
                </svg>
                <p class="text-sm">Không có thông báo mới</p>
            </div>
            <?php else: ?>
                <?php foreach ($recentNotifications as $notification): ?>
                <a href="<?php echo url('notifications/view.php?id=' . $notification['id']); ?>" 
                   class="block px-3 py-3 hover:bg-gray-50 transition-colors border-b border-gray-100 last:border-b-0 <?php echo !$notification['is_read'] ? 'bg-blue-50' : ''; ?>">
                    <div class="flex items-start gap-3">
                        <div class="flex-shrink-0">
                            <div class="w-8 h-8 rounded-full flex items-center justify-center"
                                 style="background-color: <?php echo $notification['color']; ?>20;">
                                <i class="<?php echo $notification['icon']; ?> text-xs" 
                                   style="color: <?php echo $notification['color']; ?>"></i>
                            </div>
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-medium text-gray-900 <?php echo !$notification['is_read'] ? 'font-semibold' : ''; ?>">
                                <?php echo htmlspecialchars($notification['title']); ?>
                            </p>
                            <p class="text-xs text-gray-600 mt-0.5 line-clamp-2">
                                <?php echo htmlspecialchars(substr($notification['content'], 0, 80)); ?>...
                            </p>
                            <p class="text-xs text-gray-400 mt-1">
                                <?php 
                                $time = strtotime($notification['created_at']);
                                $diff = time() - $time;
                                if ($diff < 3600) {
                                    echo floor($diff / 60) . ' phút trước';
                                } elseif ($diff < 86400) {
                                    echo floor($diff / 3600) . ' giờ trước';
                                } else {
                                    echo date('d/m/Y', $time);
                                }
                                ?>
                            </p>
                        </div>
                    </div>
                </a>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        
        <div class="p-3 border-t border-gray-200 bg-gray-50">
            <a href="<?php echo url('notifications'); ?>" 
               class="block w-full text-center text-sm text-blue-600 hover:text-blue-700 font-medium py-2 rounded-lg hover:bg-white transition-colors">
                Xem tất cả thông báo →
            </a>
        </div>
    </div>
</div>

<!-- Desktop Notification Dropdown (phải đặt ngoài header) -->
<div id="desktopNotificationDropdown" 
     class="hidden absolute bg-white rounded-lg shadow-2xl border border-gray-200 max-h-96 overflow-hidden z-[9999]"
     style="display: none; width: 24rem;">
    <div class="p-4 border-b border-gray-200 bg-gradient-to-r from-blue-50 to-purple-50">
        <h3 class="font-semibold text-gray-900 flex items-center justify-between">
            <span class="flex items-center">
                <span class="text-lg mr-2">🔔</span>
                <span>Thông báo</span>
            </span>
            <?php if ($unreadNotifications > 0): ?>
            <span class="text-xs text-gray-600 bg-white px-2 py-1 rounded-full">
                <?php echo $unreadNotifications; ?> chưa đọc
            </span>
            <?php endif; ?>
        </h3>
    </div>
    
    <div class="overflow-y-auto max-h-[300px]">
        <?php if (empty($recentNotifications)): ?>
        <div class="px-4 py-8 text-center text-gray-500">
            <svg class="w-12 h-12 mx-auto mb-3 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                      d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"></path>
            </svg>
            <p class="text-sm">Không có thông báo mới</p>
        </div>
        <?php else: ?>
            <?php foreach ($recentNotifications as $notification): ?>
            <a href="<?php echo url('notifications/view.php?id=' . $notification['id']); ?>" 
               class="block px-4 py-3 hover:bg-gray-50 transition-colors border-b border-gray-100 last:border-b-0 <?php echo !$notification['is_read'] ? 'bg-blue-50' : ''; ?>"
               onclick="markNotificationRead(<?php echo $notification['id']; ?>)">
                <div class="flex items-start gap-3">
                    <div class="flex-shrink-0">
                        <div class="w-8 h-8 rounded-full flex items-center justify-center"
                             style="background-color: <?php echo $notification['color']; ?>20;">
                            <i class="<?php echo $notification['icon']; ?> text-xs" 
                               style="color: <?php echo $notification['color']; ?>"></i>
                        </div>
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-medium text-gray-900 <?php echo !$notification['is_read'] ? 'font-semibold' : ''; ?>">
                            <?php echo htmlspecialchars($notification['title']); ?>
                        </p>
                        <p class="text-xs text-gray-600 mt-0.5 line-clamp-2">
                            <?php echo htmlspecialchars(substr($notification['content'], 0, 80)); ?>...
                        </p>
                        <p class="text-xs text-gray-400 mt-1">
                            <?php 
                            $time = strtotime($notification['created_at']);
                            $diff = time() - $time;
                            if ($diff < 3600) {
                                echo floor($diff / 60) . ' phút trước';
                            } elseif ($diff < 86400) {
                                echo floor($diff / 3600) . ' giờ trước';
                            } else {
                                echo date('d/m/Y', $time);
                            }
                            ?>
                        </p>
                    </div>
                </div>
            </a>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
    
    <div class="p-3 border-t border-gray-200 bg-gray-50">
        <a href="<?php echo url('notifications'); ?>" 
           class="block w-full text-center text-sm text-blue-600 hover:text-blue-700 font-medium py-2 rounded-lg hover:bg-white transition-colors">
            Xem tất cả thông báo →
        </a>
    </div>
</div>

<style>
/* Remove all previous conflicting styles and use these clean ones */

/* Ensure body has proper padding for fixed header */
body {
    padding-top: 0 !important;
}

/* Header specific */
header {
    height: 56px;
}

@media (min-width: 640px) {
    header {
        height: 64px;
    }
}

/* Line clamp utility */
.line-clamp-2 {
    overflow: hidden;
    display: -webkit-box;
    -webkit-box-orient: vertical;
    -webkit-line-clamp: 2;
}

/* Animation */
@keyframes pulse {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.5; }
}

.animate-pulse {
    animation: pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
}

/* Mobile modal animation */
@keyframes slideUp {
    from {
        transform: translateY(20px);
        opacity: 0;
    }
    to {
        transform: translateY(0);
        opacity: 1;
    }
}

#mobileNotificationModal.show {
    animation: slideUp 0.3s ease-out;
}

/* Remove tap highlight */
* {
    -webkit-tap-highlight-color: transparent;
}
</style>

<script>
// Clean notification system
function toggleNotificationDropdown() {
    console.log('Toggle notification clicked');
    const isMobile = window.innerWidth < 640;
    
    // Close user dropdown if open
    const userDropdown = document.getElementById('headerUserDropdown');
    if (userDropdown && !userDropdown.classList.contains('hidden')) {
        userDropdown.classList.add('hidden');
    }
    
    if (isMobile) {
        // Show modal on mobile
        const modal = document.getElementById('mobileNotificationModal');
        if (modal) {
            console.log('Opening mobile modal');
            modal.classList.remove('hidden');
            modal.classList.add('show');
            // Prevent body scroll when modal is open
            document.body.style.overflow = 'hidden';
        }
    } else {
        // Toggle dropdown on desktop
        const dropdown = document.getElementById('desktopNotificationDropdown');
        const container = document.getElementById('notificationContainer');
        
        if (dropdown && container) {
            const isHidden = dropdown.style.display === 'none' || !dropdown.style.display;
            
            if (isHidden) {
                console.log('Opening desktop dropdown');
                // Position dropdown relative to button
                const rect = container.getBoundingClientRect();
                dropdown.style.position = 'fixed';
                dropdown.style.top = (rect.bottom + 8) + 'px';
                dropdown.style.right = (window.innerWidth - rect.right) + 'px';
                dropdown.style.display = 'block';
                dropdown.classList.remove('hidden');
            } else {
                console.log('Closing desktop dropdown');
                dropdown.style.display = 'none';
            }
        }
    }
}

function closeNotificationModal() {
    console.log('Closing notification modal');
    const modal = document.getElementById('mobileNotificationModal');
    if (modal) {
        modal.classList.add('hidden');
        modal.classList.remove('show');
        // Re-enable body scroll
        document.body.style.overflow = '';
    }
}

// Handle dropdown for user menu
function handleDropdown() {
    console.log('Toggle user dropdown');
    const dropdown = document.getElementById('headerUserDropdown');
    
    // Close notification dropdown if open
    const notifDropdown = document.getElementById('desktopNotificationDropdown');
    if (notifDropdown && notifDropdown.style.display === 'block') {
        notifDropdown.style.display = 'none';
    }
    
    if (dropdown) {
        dropdown.classList.toggle('hidden');
    }
}

// Handle mobile menu
function handleMobileMenu() {
    console.log('Toggle mobile menu');
    const sidebar = document.querySelector('.lg\\:ml-64');
    if (sidebar) {
        sidebar.classList.toggle('hidden');
    }
}

// Mark notification as read
function markNotificationRead(notificationId) {
    event.preventDefault();
    console.log('Marking notification as read:', notificationId);
    
    fetch('/api/mark-notification-read.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({
            notification_id: notificationId
        })
    }).then(response => response.json())
      .then(data => {
          console.log('Mark as read response:', data);
          updateNotificationBadge();
      })
      .catch(error => {
          console.error('Error marking notification as read:', error);
      });
    
    // Navigate to notification
    window.location.href = event.currentTarget.href;
}

// Update notification badge
function updateNotificationBadge() {
    fetch('/api/notifications.php?action=unread_count')
        .then(response => response.json())
        .then(data => {
            console.log('Unread count:', data);
            const badge = document.getElementById('notification-badge');
            if (data.count > 0) {
                if (badge) {
                    badge.textContent = data.count > 9 ? '9+' : data.count;
                    badge.style.display = 'flex';
                }
            } else {
                if (badge) {
                    badge.style.display = 'none';
                }
            }
        })
        .catch(error => {
            console.error('Error updating badge:', error);
        });
}

// Close dropdowns when clicking outside
document.addEventListener('click', function(e) {
    // Desktop notification dropdown
    const notifDropdown = document.getElementById('desktopNotificationDropdown');
    const notifButton = document.getElementById('notificationButton');
    const notifContainer = document.getElementById('notificationContainer');
    
    if (notifDropdown && 
        notifDropdown.style.display === 'block' && 
        !notifContainer.contains(e.target)) {
        console.log('Clicking outside notification dropdown');
        notifDropdown.style.display = 'none';
    }
    
    // User dropdown
    const userDropdown = document.getElementById('headerUserDropdown');
    const isUserButton = e.target.closest('[onclick="handleDropdown()"]');
    
    if (userDropdown && 
        !userDropdown.classList.contains('hidden') && 
        !isUserButton && 
        !userDropdown.contains(e.target)) {
        console.log('Clicking outside user dropdown');
        userDropdown.classList.add('hidden');
    }
});

// Handle resize
let resizeTimer;
window.addEventListener('resize', function() {
    clearTimeout(resizeTimer);
    resizeTimer = setTimeout(function() {
        // Close all dropdowns on resize
        closeNotificationModal();
        const dropdown = document.getElementById('desktopNotificationDropdown');
        if (dropdown) {
            dropdown.style.display = 'none';
        }
    }, 250);
});

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    console.log('Header notification system initialized');
    
    // Check for notifications on load
    <?php if ($isLoggedIn && $userId): ?>
    updateNotificationBadge();
    
    // Update badge every 30 seconds
    setInterval(updateNotificationBadge, 30000);
    <?php endif; ?>
});

// Debug info
console.log('✅ Notification system loaded');
console.log('Elements found:', {
    mobileModal: !!document.getElementById('mobileNotificationModal'),
    desktopDropdown: !!document.getElementById('desktopNotificationDropdown'),
    notificationButton: !!document.getElementById('notificationButton'),
    notificationContainer: !!document.getElementById('notificationContainer')
});
</script>

<style>
/* Header Styles */
@media (min-width: 1024px) {
    .lg\:ml-64 { margin-left: 16rem; }
}

#headerUserDropdown { 
    z-index: 9999;
    max-height: 80vh;
    overflow-y: auto;
}

button { cursor: pointer !important; }

/* XU Currency Styling - HEADER VERSION */
.xu-currency-header {
    background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
    font-weight: bold;
}

.xu-icon-header {
    background: linear-gradient(135deg, #fbbf24 0%, #f59e0b 100%);
    width: 16px;
    height: 16px;
    border-radius: 50%;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 10px;
    font-weight: bold;
    margin-right: 4px;
    text-shadow: 0 1px 2px rgba(0, 0, 0, 0.2);
    border: 1px solid #f59e0b;
    box-shadow: 0 1px 3px rgba(245, 158, 11, 0.3);
}

.xu-currency-dropdown {
    background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
    font-weight: bold;
}

.xu-icon-dropdown {
    background: linear-gradient(135deg, #fbbf24 0%, #f59e0b 100%);
    width: 12px;
    height: 12px;
    border-radius: 50%;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 8px;
    font-weight: bold;
    margin-right: 4px;
    text-shadow: 0 1px 2px rgba(0, 0, 0, 0.2);
    border: 1px solid #f59e0b;
    box-shadow: 0 1px 2px rgba(245, 158, 11, 0.3);
}

/* Plan Badge Styles */
.text-gray-800 { color: #1f2937; }
.text-blue-800 { color: #1e40af; }
.text-purple-800 { color: #6b21a8; }
.text-yellow-800 { color: #92400e; }
.text-red-800 { color: #991b1b; }

/* Enhanced balance display */
.bg-orange-50 {
    background: linear-gradient(135deg, #fff7ed 0%, #ffedd5 100%);
}

.border-orange-200 {
    border-color: #fed7aa;
}

/* Mobile optimizations */
@media (max-width: 640px) {
    .space-x-1 > :not([hidden]) ~ :not([hidden]) {
        margin-left: 0.25rem;
    }
    
    #headerUserDropdown {
        right: -0.5rem;
        width: calc(100vw - 2rem);
        max-width: 280px;
    }
}

@media (max-width: 480px) {
    .px-3 {
        padding-left: 0.5rem;
        padding-right: 0.5rem;
    }
    
    .xu-icon-header {
        width: 14px;
        height: 14px;
        font-size: 9px;
    }
}

/* Ensure all links are properly styled */
a {
    text-decoration: none;
}

a:hover {
    text-decoration: none;
}

/* Smooth transitions */
.transition-colors {
    transition: background-color 0.2s ease, color 0.2s ease;
}

/* Plan indicator enhancement */
.plan-indicator {
    transition: all 0.2s ease;
}

.plan-indicator:hover {
    transform: scale(1.05);
}

/* Notification Styles */
#notificationDropdown {
    z-index: 9998;
}

#notificationToast {
    z-index: 10000;
}

@keyframes shrink {
    from { width: 100%; }
    to { width: 0%; }
}

.animate-shrink {
    animation: shrink 5s linear forwards;
}

.animate-pulse {
    animation: pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
}

@keyframes pulse {
    0%, 100% {
        opacity: 1;
    }
    50% {
        opacity: 0.5;
    }
}

/* Notification badge bounce animation */
#notification-badge {
    animation: bounce 1s ease-in-out;
}

@keyframes bounce {
    0%, 100% {
        transform: scale(1);
    }
    50% {
        transform: scale(1.2);
    }
}

/* Notification dropdown item hover */
#notificationDropdown a:hover {
    background-color: #f9fafb;
}

#notificationDropdown .bg-blue-50:hover {
    background-color: #dbeafe !important;
}

/* Toast notification styles */
#notificationToast {
    box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
}

/* Make sure notification icon is visible */
button[onclick*="toggleNotificationDropdown"] {
    position: relative;
}

/* Responsive adjustments */
@media (max-width: 640px) {
    #notificationDropdown {
        width: calc(100vw - 2rem);
        right: -0.5rem;
    }
    
    #notificationToast {
        max-width: calc(100vw - 2rem);
        right: 1rem;
    }
}
</style>