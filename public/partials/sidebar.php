<?php
// Kiểm tra nếu chưa có session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include required files
require_once __DIR__ . '/../../app/config.php';
require_once __DIR__ . '/../../app/auth.php';
require_once __DIR__ . '/../../app/db.php';

// Helper function để tạo URL
if (!function_exists('url')) {
    function url($path = '') {
        // Remove leading slash if present
        $path = ltrim($path, '/');
        
        // If it's already a full URL, return as is
        if (strpos($path, 'http') === 0) {
            return $path;
        }
        
        // Get base URL from config or construct it
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $baseUrl = $protocol . '://' . $host;
        
        // Add .php extension if not present and not empty
        if (!empty($path) && !strpos($path, '.php') && !strpos($path, '.')) {
            $path .= '.php';
        }
        
        return $baseUrl . '/' . $path;
    }
}

// Chỉ hiển thị sidebar nếu user đã đăng nhập
if (!Auth::isLoggedIn()) {
    return;
}

$userData = Auth::getUser();
$userId = $userData['id'] ?? null;

// Get wallet balance from wallets table
$balance = 0;
$subscriptionData = null;
$planName = 'Free';
$planStatus = 'free';
$endDate = null;
$daysRemaining = 0;

if ($userId) {
    global $db;
    try {
        $pdo = $db->getPdo();
        
        // Get wallet balance
        $stmt = $pdo->prepare("SELECT balance FROM wallets WHERE user_id = ? LIMIT 1");
        $stmt->execute([$userId]);
        $wallet = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($wallet) {
            $balance = $wallet['balance'];
        }
        
        // Get active subscription
        $stmt = $pdo->prepare("
            SELECT * FROM subscriptions 
            WHERE user_id = ? 
            AND status = 'active' 
            AND end_date > NOW() 
            ORDER BY end_date DESC 
            LIMIT 1
        ");
        $stmt->execute([$userId]);
        $subscriptionData = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($subscriptionData) {
            $planName = $subscriptionData['plan_name'];
            $planStatus = 'active';
            $endDate = $subscriptionData['end_date'];
            
            // Calculate days remaining
            $endDateTime = new DateTime($endDate);
            $now = new DateTime();
            $interval = $now->diff($endDateTime);
            $daysRemaining = $interval->days;
            
            // If less than 30 days, show exact days
            // If more, show months
            if ($daysRemaining > 30) {
                $monthsRemaining = floor($daysRemaining / 30);
                $daysRemaining = $monthsRemaining . ' tháng';
            } else {
                $daysRemaining = $daysRemaining . ' ngày';
            }
        }
        
    } catch (Exception $e) {
        error_log("Sidebar data fetch error: " . $e->getMessage());
    }
}

// User info
$userName = $userData['full_name'] ?? 'User';
$userInitials = strtoupper(substr($userName, 0, 1));
$isAdmin = ($userData['role'] ?? '') === 'admin';

// Plan colors and icons
$planColors = [
    'Free' => ['bg' => 'bg-gray-500', 'text' => 'text-gray-100', 'icon' => '🆓'],
    'Standard' => ['bg' => 'bg-blue-500', 'text' => 'text-blue-100', 'icon' => '⚡'],
    'Pro' => ['bg' => 'bg-purple-500', 'text' => 'text-purple-100', 'icon' => '⭐'],
    'Ultra' => ['bg' => 'bg-yellow-500', 'text' => 'text-yellow-100', 'icon' => '👑'],
    'Premium' => ['bg' => 'bg-red-500', 'text' => 'text-red-100', 'icon' => '💎']
];

$currentPlanColor = $planColors[$planName] ?? $planColors['Free'];

// Get current page for active state
$currentPage = basename($_SERVER['PHP_SELF']);

// Navigation items with their pages
$navItems = [
    'dashboard' => [
        'icon' => 'M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2H5a2 2 0 00-2-2V5a2 2 0 012-2h14a2 2 0 012 2v2',
        'label' => 'Dashboard',
        'url' => 'dashboard',
        'badge' => null
    ],
    'image' => [
        'icon' => 'M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z',
        'label' => 'Tạo Ảnh AI',
        'url' => 'image',
        'badge' => null
    ],
    'video' => [
        'icon' => 'M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z',
        'label' => 'Video AI',
        'url' => 'video',
        'badge' => ['text' => 'Mới', 'color' => 'bg-purple-600']
    ],
    'content' => [
        'icon' => 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z',
        'label' => 'Nội Dung AI',
        'url' => 'content',
        'badge' => null
    ],
    'voice' => [
        'icon' => 'M19 11a7 7 0 01-7 7m0 0a7 7 0 01-7-7m7 7v4m0 0H8m4 0h4m-4-8a3 3 0 01-3-3V5a3 3 0 116 0v6a3 3 0 01-3 3z',
        'label' => 'Voice AI',
        'url' => 'voice',
        'badge' => ['text' => 'Sắp ra', 'color' => 'bg-green-600']
    ],
    'assistant' => [
        'icon' => 'M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z',
        'label' => 'AI Assistant',
        'url' => 'assistant',
        'badge' => ['text' => 'Hot', 'color' => 'bg-red-600']
    ]
];

$toolItems = [
    'pricing' => [
        'icon' => 'M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z',
        'label' => 'Gói cước',
        'url' => 'pricing',
        'badge' => ['text' => '💎', 'color' => 'bg-blue-600']
    ],
    'wallet' => [
        'icon' => 'M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z',
        'label' => 'Ví & Lịch Sử',
        'url' => 'wallet',
        'badge' => null
    ],
    'topup' => [
        'icon' => 'M12 6v12m-3-2.818l.879.659c1.171.879 3.07.879 4.242 0 1.172-.879 1.172-2.303 0-3.182C13.536 12.219 12.768 12 12 12s-1.536.219-2.121.659c-1.172.879-1.172 2.303 0 3.182l.879.659z',
        'label' => 'Nạp Xu',
        'url' => 'topup',
        'badge' => ['text' => '+', 'color' => 'bg-green-600']
    ],
    'stats' => [
        'icon' => 'M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z',
        'label' => 'Thống Kê',
        'a href' => 'stats',
        'badge' => null
    ],
    'settings' => [
        'icon' => 'M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z M15 12a3 3 0 11-6 0 3 3 0 016 0z',
        'label' => 'Cài Đặt',
        'a href' => 'settings',
        'badge' => null
    ]
];

$accountItems = [
    'profile' => [
        'icon' => 'M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z',
        'label' => 'Hồ Sơ',
        'url' => 'profile',
        'badge' => null
    ],
    'notification' => [
        'icon'=> 'M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9',
        'label'=> 'Thông báo',
        'url'=> 'notifications',
        'badge'=> null
    ],
    'support' => [
        'icon' => 'M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z',
        'label' => 'Hỗ Trợ',
        'url' => 'support',
        'badge' => null
    ]
];

// Support items cho hướng dẫn
$supportItems = [
    'help' => [
        'icon' => 'M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z',
        'label' => 'Trung tâm trợ giúp',
        'url' => 'help',
        'badge' => null
    ],
    'tutorials' => [
        'icon' => 'M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z',
        'label' => 'Video hướng dẫn',
        'url' => 'tutorials',
        'badge' => null
    ],
    'docs' => [
        'icon' => 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z',
        'label' => 'Tài liệu hướng dẫn',
        'url' => 'docs',
        'badge' => null
    ]
];

// Function to check if current page is active
function isActive($page, $currentPage) {
    // Remove .php extension for comparison
    $page = str_replace('.php', '', $page);
    $currentPage = str_replace('.php', '', $currentPage);
    return $page === $currentPage;
}

// Function to render sidebar item
function renderSidebarItem($key, $item, $currentPage, $isLogout = false) {
    // Check if url key exists
    if (!isset($item['url'])) {
        error_log("Warning: Missing 'url' key for sidebar item: " . $key);
        return;
    }
    
    $isActiveItem = isActive($item['url'], $currentPage);
    $activeClasses = $isActiveItem ? 'bg-blue-600 text-white' : 'text-slate-300 hover:bg-slate-800 hover:text-white';
    $hoverClasses = $isLogout ? 'hover:bg-red-600' : 'hover:bg-slate-800';
    
    if ($isLogout) {
        echo '<button onclick="handleSidebarLogout()" class="sidebar-item w-full flex items-center px-3 py-2 text-slate-300 ' . $hoverClasses . ' hover:text-white rounded-lg transition-colors duration-200 mb-1">';
    } else {
        echo '<a href="' . htmlspecialchars(url($item['url'])) . '" class="sidebar-item flex items-center px-3 py-2 ' . $activeClasses . ' rounded-lg transition-colors duration-200 mb-1" title="' . htmlspecialchars($item['label']) . '">';
    }
    
    echo '<svg class="w-5 h-5 sidebar-icon flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">';
    echo '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="' . $item['icon'] . '"></path>';
    echo '</svg>';
    echo '<span class="sidebar-text ml-3 flex-1 truncate">' . htmlspecialchars($item['label']) . '</span>';
    
    if (isset($item['badge']) && $item['badge']) {
        echo '<span class="sidebar-badge ml-2 flex-shrink-0 ' . $item['badge']['color'] . ' text-white text-xs px-2 py-1 rounded-full">' . htmlspecialchars($item['badge']['text']) . '</span>';
    }
    
    if ($isLogout) {
        echo '</button>';
    } else {
        echo '</a>';
    }
}
?>

<!-- Mobile Menu Button - CHỈ HIỆN KHI KHÔNG CÓ NÚT KHÁC -->
<button 
    onclick="handleMobileMenu()" 
    class="mobile-menu-btn fixed top-4 left-4 z-50 lg:hidden bg-slate-800 hover:bg-slate-700 text-white p-3 rounded-lg shadow-lg transition-all duration-200"
    id="mobileMenuBtn"
    style="display: none;"
>
    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>
    </svg>
</button>

<!-- Sidebar Component -->
<div class="sidebar-container fixed inset-y-0 left-0 z-40 bg-slate-900 transform -translate-x-full lg:translate-x-0 transition-all duration-300 ease-in-out" id="sidebar">
    
    <!-- Mobile Close Button -->
    <button 
        onclick="handleMobileMenu()" 
        class="mobile-close-btn absolute top-4 right-4 z-10 lg:hidden bg-slate-700 hover:bg-slate-600 text-white p-2 rounded-lg transition-colors duration-200"
        id="mobileCloseBtn"
    >
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
        </svg>
    </button>
    
    <!-- Desktop Collapse/Expand Toggle Button -->
    <button onclick="toggleSidebarCollapse()" class="sidebar-toggle absolute -right-3 top-6 w-6 h-6 bg-slate-700 hover:bg-slate-600 text-white rounded-full flex items-center justify-center transition-colors duration-200 z-10 hidden lg:flex" id="sidebarToggle">
        <svg class="w-3 h-3 transition-transform duration-200" id="toggleIcon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
        </svg>
    </button>

    <!-- Header -->
    <div class="flex items-center py-6 px-4 border-b border-slate-700 flex-shrink-0">
        <div class="flex items-center w-full">
            <div class="w-8 h-8 bg-blue-600 rounded-lg flex items-center justify-center sidebar-logo flex-shrink-0">
                <span class="text-white font-bold text-lg">🤖</span>
            </div>
            <span class="sidebar-text text-white text-xl font-bold ml-3 truncate">AI BOOST</span>
        </div>
    </div>

    <!-- User Info - UPDATED WITH SUBSCRIPTION DATA -->
    <div class="px-4 py-4 border-b border-slate-700 flex-shrink-0">
        <div class="flex items-center">
            <div class="w-10 h-10 <?php echo $currentPlanColor['bg']; ?> rounded-full flex items-center justify-center flex-shrink-0">
                <span class="text-white font-bold text-sm"><?php echo $userInitials; ?></span>
            </div>
            <div class="sidebar-text flex-1 min-w-0 ml-3">
                <div class="text-white font-medium text-sm truncate"><?php echo htmlspecialchars($userName); ?></div>
                
                <!-- Plan Info -->
                <div class="flex items-center space-x-1 mb-1">
                    <span class="text-xs"><?php echo $currentPlanColor['icon']; ?></span>
                    <span class="<?php echo $currentPlanColor['text']; ?> text-xs font-semibold">
                        <?php echo htmlspecialchars($planName); ?>
                    </span>
                    <?php if ($subscriptionData && $daysRemaining): ?>
                        <span class="text-slate-400 text-xs">• <?php echo $daysRemaining; ?></span>
                    <?php endif; ?>
                </div>
                
                <!-- XU Balance -->
                <div class="text-orange-400 text-sm font-bold flex items-center">
                    <span class="xu-icon-sidebar">💰</span>
                    <span><?php echo number_format($balance, 0, ',', '.'); ?></span>
                    <span class="xu-currency-sidebar ml-1">XU</span>
                </div>
            </div>
        </div>
        
        <?php if ($planName === 'Free'): ?>
        <!-- Upgrade Button for Free Users -->
        <div class="mt-3">
            <a href="<?php echo url('pricing'); ?>" class="block w-full text-center bg-gradient-to-r from-purple-500 to-blue-600 hover:from-purple-600 hover:to-blue-700 text-white text-xs font-medium px-3 py-2 rounded-lg transition-all duration-200 sidebar-text">
                🚀 Nâng cấp Pro
            </a>
        </div>
        <?php elseif ($subscriptionData && $daysRemaining && is_numeric(str_replace(' ngày', '', $daysRemaining)) && intval($daysRemaining) <= 7): ?>
        <!-- Renew Button for Expiring Subscriptions -->
        <div class="mt-3">
            <a href="<?php echo url('pricing'); ?>" class="block w-full text-center bg-gradient-to-r from-yellow-500 to-orange-600 hover:from-yellow-600 hover:to-orange-700 text-white text-xs font-medium px-3 py-2 rounded-lg transition-all duration-200 sidebar-text animate-pulse">
                ⚠️ Gia hạn ngay
            </a>
        </div>
        <?php endif; ?>
    </div>

    <!-- Scrollable Navigation Container -->
    <div class="flex-1 flex flex-col overflow-hidden">
        
        <!-- Custom Scroll Indicator -->
        <div class="sidebar-scroll-indicator px-4 py-2 text-center hidden" id="scrollIndicator">
            <div class="text-slate-400 text-xs">
                <i class="fas fa-chevron-up"></i> Cuộn để xem thêm <i class="fas fa-chevron-down"></i>
            </div>
        </div>
        
        <!-- Scrollable Navigation -->
        <nav class="flex-1 px-4 py-6 overflow-y-auto custom-scrollbar" id="sidebarNav">
            <!-- Main Menu -->
            <div class="mb-6">
                <h3 class="sidebar-text text-slate-400 text-xs font-semibold uppercase tracking-wider mb-3">Dịch vụ</h3>
                
                <?php foreach ($navItems as $key => $item): ?>
                    <?php renderSidebarItem($key, $item, $currentPage); ?>
                <?php endforeach; ?>
            </div>

            <!-- Tài chính & Gói cước -->
            <div class="mb-6">
                <h3 class="sidebar-text text-slate-400 text-xs font-semibold uppercase tracking-wider mb-3">Tài chính & Gói cước </h3>
                
                <?php foreach ($toolItems as $key => $item): ?>
                    <?php renderSidebarItem($key, $item, $currentPage); ?>
                <?php endforeach; ?>
            </div>

            <!-- Admin Section (Only show for admin users) -->
            <?php if ($isAdmin): ?>
            <div class="mb-6">
                <h3 class="sidebar-text text-slate-400 text-xs font-semibold uppercase tracking-wider mb-3">ADMIN</h3>
                
                <a href="<?php echo url('admin'); ?>" class="sidebar-item flex items-center px-3 py-2 <?php echo isActive('admin', $currentPage) ? 'bg-purple-600 text-white' : 'text-slate-300 hover:bg-purple-800 hover:text-white'; ?> rounded-lg transition-colors duration-200 mb-1" title="Admin Panel">
                    <svg class="w-5 h-5 sidebar-icon flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    <span class="sidebar-text ml-3 flex-1 truncate">Admin Panel</span>
                    <span class="sidebar-badge ml-2 flex-shrink-0 bg-purple-600 text-white text-xs px-2 py-1 rounded-full">👑</span>
                </a>
            </div>
            <?php endif; ?>

            <!-- Account -->
            <div class="mb-6">
                <h3 class="sidebar-text text-slate-400 text-xs font-semibold uppercase tracking-wider mb-3">TÀI KHOẢN</h3>
                
                <?php foreach ($accountItems as $key => $item): ?>
                    <?php renderSidebarItem($key, $item, $currentPage); ?>
                <?php endforeach; ?>
            </div>

            <!-- Support & Help -->
            <div class="mb-6">
                <h3 class="sidebar-text text-slate-400 text-xs font-semibold uppercase tracking-wider mb-3">TÀI LIỆU HỖ TRỢ</h3>
                
                <?php foreach ($supportItems as $key => $item): ?>
                    <?php renderSidebarItem($key, $item, $currentPage); ?>
                <?php endforeach; ?>
            </div>

            <!-- Logout Button -->
            <div class="mt-auto pt-4 border-t border-slate-700">
                <?php 
                $logoutItem = [
                    'icon' => 'M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1',
                    'label' => 'Đăng Xuất',
                    'url' => 'logout'
                ];
                renderSidebarItem('logout', $logoutItem, $currentPage, true);
                ?>
            </div>
        </nav>

        <!-- Scroll to Top Button -->
        <div class="sidebar-scroll-top px-4 py-2 border-t border-slate-700 hidden" id="scrollTopBtn">
            <button onclick="scrollSidebarToTop()" class="w-full flex items-center justify-center px-3 py-2 text-slate-400 hover:text-slate-300 hover:bg-slate-800 rounded-lg transition-colors duration-200">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 10l7-7m0 0l7 7m-7-7v18"></path>
                </svg>
                <span class="sidebar-text text-xs">Lên đầu</span>
            </button>
        </div>
    </div>
</div>

<!-- Mobile overlay -->
<div class="fixed inset-0 bg-black bg-opacity-50 z-30 lg:hidden hidden" id="sidebarOverlay" onclick="handleMobileMenu()"></div>

<!-- JavaScript Functions -->
<script>
// Sidebar collapse state
let sidebarCollapsed = localStorage.getItem('sidebarCollapsed') === 'true';

// Handle sidebar logout
function handleSidebarLogout() {
    if (confirm('Bạn có chắc chắn muốn đăng xuất?')) {
        window.location.href = '<?php echo url('logout'); ?>';
    }
}

// Kiểm tra xem có menu button khác không
function checkForExistingMenuButton() {
    // Tìm các button menu có thể có trong header
    const existingMenuButtons = document.querySelectorAll('button[onclick*="menu"], .menu-btn, .mobile-menu, [data-menu]');
    const ourMenuBtn = document.getElementById('mobileMenuBtn');
    
    if (existingMenuButtons.length > 1 && ourMenuBtn) {
        // Nếu có button khác, ẩn button của chúng ta
        ourMenuBtn.style.display = 'none';
        console.log('🔍 Found existing menu button, hiding our mobile menu button');
    } else if (ourMenuBtn) {
        // Nếu không có button khác, hiện button của chúng ta trên mobile
        if (window.innerWidth < 1024) {
            ourMenuBtn.style.display = 'block';
        } else {
            ourMenuBtn.style.display = 'none';
        }
    }
}

// Handle mobile menu toggle  
function handleMobileMenu() {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebarOverlay');
    const mobileMenuBtn = document.getElementById('mobileMenuBtn');
    
    if (sidebar && overlay) {
        const isHidden = sidebar.classList.contains('-translate-x-full');
        
        if (isHidden) {
            // Show sidebar
            sidebar.classList.remove('-translate-x-full');
            overlay.classList.remove('hidden');
            if (mobileMenuBtn) mobileMenuBtn.style.opacity = '0.5';
            document.body.style.overflow = 'hidden'; // Prevent body scroll
        } else {
            // Hide sidebar
            sidebar.classList.add('-translate-x-full');
            overlay.classList.add('hidden');
            if (mobileMenuBtn) mobileMenuBtn.style.opacity = '1';
            document.body.style.overflow = ''; // Restore body scroll
        }
    }
}

// Toggle sidebar collapse/expand (Desktop only)
function toggleSidebarCollapse() {
    // Only work on desktop
    if (window.innerWidth < 1024) return;
    
    const sidebar = document.getElementById('sidebar');
    const toggleIcon = document.getElementById('toggleIcon');
    const contentElements = document.querySelectorAll('.lg\\:ml-64, .lg\\:ml-16');
    
    sidebarCollapsed = !sidebarCollapsed;
    
    if (sidebarCollapsed) {
        // Collapsed state
        sidebar.classList.add('sidebar-collapsed');
        sidebar.classList.remove('w-64');
        sidebar.classList.add('w-16');
        toggleIcon.style.transform = 'rotate(180deg)';
        
        // Add body class
        document.body.classList.add('sidebar-collapsed');
        
        // Adjust content margins
        contentElements.forEach(element => {
            element.classList.remove('lg:ml-64');
            element.classList.add('lg:ml-16');
        });
        
    } else {
        // Expanded state
        sidebar.classList.remove('sidebar-collapsed');
        sidebar.classList.remove('w-16');
        sidebar.classList.add('w-64');
        toggleIcon.style.transform = 'rotate(0deg)';
        
        // Remove body class
        document.body.classList.remove('sidebar-collapsed');
        
        // Reset content margins
        contentElements.forEach(element => {
            element.classList.remove('lg:ml-16');
            element.classList.add('lg:ml-64');
        });
    }
    
    // Save state and trigger resize
    localStorage.setItem('sidebarCollapsed', sidebarCollapsed);
    window.dispatchEvent(new Event('resize'));
    
    console.log('✅ Sidebar toggle completed, state:', sidebarCollapsed ? 'collapsed' : 'expanded');
}

// Scroll sidebar to top
function scrollSidebarToTop() {
    const sidebarNav = document.getElementById('sidebarNav');
    if (sidebarNav) {
        sidebarNav.scrollTo({
            top: 0,
            behavior: 'smooth'
        });
    }
}

// Handle sidebar scroll
function handleSidebarScroll() {
    const sidebarNav = document.getElementById('sidebarNav');
    const scrollIndicator = document.getElementById('scrollIndicator');
    const scrollTopBtn = document.getElementById('scrollTopBtn');
    
    if (!sidebarNav) return;
    
    const scrollTop = sidebarNav.scrollTop;
    const scrollHeight = sidebarNav.scrollHeight;
    const clientHeight = sidebarNav.clientHeight;
    const isScrollable = scrollHeight > clientHeight;
    
    // Show/hide scroll indicator
    if (scrollIndicator) {
        if (isScrollable && scrollTop < 50) {
            scrollIndicator.classList.remove('hidden');
        } else {
            scrollIndicator.classList.add('hidden');
        }
    }
    
    // Show/hide scroll to top button
    if (scrollTopBtn) {
        if (scrollTop > 200) {
            scrollTopBtn.classList.remove('hidden');
        } else {
            scrollTopBtn.classList.add('hidden');
        }
    }
}

// Initialize sidebar state on page load
function initializeSidebarState() {
    // Check for existing menu buttons first
    checkForExistingMenuButton();
    
    // Apply saved collapsed state (Desktop only)
    if (sidebarCollapsed && window.innerWidth >= 1024) {
        const sidebar = document.getElementById('sidebar');
        const toggleIcon = document.getElementById('toggleIcon');
        const contentElements = document.querySelectorAll('.lg\\:ml-64');
        
        if (sidebar) {
            sidebar.classList.add('sidebar-collapsed');
            sidebar.classList.remove('w-64');
            sidebar.classList.add('w-16');
        }
        
        if (toggleIcon) {
            toggleIcon.style.transform = 'rotate(180deg)';
        }
        
        // Adjust content margins immediately
        contentElements.forEach(element => {
            element.classList.remove('lg:ml-64');
            element.classList.add('lg:ml-16');
        });
        
        // Add body class for global styles
        document.body.classList.add('sidebar-collapsed');
        
        console.log('✅ Initialized collapsed sidebar state');
    }
    
    // Setup scroll handling
    const sidebarNav = document.getElementById('sidebarNav');
    if (sidebarNav) {
        sidebarNav.addEventListener('scroll', handleSidebarScroll);
        setTimeout(handleSidebarScroll, 100);
    }
    
    // Ensure mobile sidebar is hidden on load
    if (window.innerWidth < 1024) {
        const sidebar = document.getElementById('sidebar');
        const overlay = document.getElementById('sidebarOverlay');
        
        if (sidebar) sidebar.classList.add('-translate-x-full');
        if (overlay) overlay.classList.add('hidden');
    }
}

// Enhanced window resize handler
window.addEventListener('resize', function() {
    // Recheck menu button visibility
    checkForExistingMenuButton();
    
    if (window.innerWidth >= 1024) {
        // Desktop - ensure sidebar is visible and content is properly adjusted
        const sidebar = document.getElementById('sidebar');
        const overlay = document.getElementById('sidebarOverlay');
        const mobileMenuBtn = document.getElementById('mobileMenuBtn');
        
        if (sidebar) sidebar.classList.remove('-translate-x-full');
        if (overlay) overlay.classList.add('hidden');
        if (mobileMenuBtn) mobileMenuBtn.style.display = 'none';
        
        // Restore body scroll
        document.body.style.overflow = '';
        
        // Re-apply content margins based on current state
        const contentElements = document.querySelectorAll('.lg\\:ml-64, .lg\\:ml-16');
        contentElements.forEach(element => {
            if (sidebarCollapsed) {
                element.classList.remove('lg:ml-64');
                element.classList.add('lg:ml-16');
            } else {
                element.classList.remove('lg:ml-16');
                element.classList.add('lg:ml-64');
            }
        });
    } else {
        // Mobile - ensure sidebar is hidden and reset content margins
        const sidebar = document.getElementById('sidebar');
        const overlay = document.getElementById('sidebarOverlay');
        const mobileMenuBtn = document.getElementById('mobileMenuBtn');
        
        if (sidebar) sidebar.classList.add('-translate-x-full');
        if (overlay) overlay.classList.add('hidden');
        
        // Show our menu button only if no other exists
        checkForExistingMenuButton();
        
        // Restore body scroll
        document.body.style.overflow = '';
        
        // Reset ALL content margins on mobile để tránh content bị đẩy sang phải
        const contentElements = document.querySelectorAll('.lg\\:ml-64, .lg\\:ml-16, [class*="ml-"], [class*="margin-left"]');
        contentElements.forEach(element => {
            element.style.marginLeft = '0 !important';
        });
        
        // Đảm bảo content full width trên mobile
        document.body.style.marginLeft = '0';
        const mainContent = document.querySelector('main, .main-content, .content, [role="main"]');
        if (mainContent) {
            mainContent.style.marginLeft = '0';
            mainContent.style.width = '100%';
        }
    }
    
    // Recheck scroll after resize
    setTimeout(handleSidebarScroll, 100);
});

// Close sidebar when clicking a link on mobile
document.addEventListener('DOMContentLoaded', function() {
    // Initialize sidebar state
    initializeSidebarState();
    
    const sidebarLinks = document.querySelectorAll('#sidebar a.sidebar-item');
    
    sidebarLinks.forEach(link => {
        link.addEventListener('click', function() {
            // On mobile, close sidebar when clicking a link
            if (window.innerWidth < 1024) {
                handleMobileMenu();
            }
        });
    });
    
    // Handle escape key to close mobile menu
    document.addEventListener('keydown', function(event) {
        if (event.key === 'Escape' && window.innerWidth < 1024) {
            const sidebar = document.getElementById('sidebar');
            if (sidebar && !sidebar.classList.contains('-translate-x-full')) {
                handleMobileMenu();
            }
        }
    });
    
    // Force reset mobile layout after a short delay
    setTimeout(() => {
        if (window.innerWidth < 1024) {
            // Đảm bảo không có margin left nào trên mobile
            const allElements = document.querySelectorAll('*');
            allElements.forEach(el => {
                const computedStyle = window.getComputedStyle(el);
                const marginLeft = computedStyle.marginLeft;
                
                if (marginLeft && marginLeft !== '0px' && marginLeft !== 'auto') {
                    // Nếu có margin-left lớn (có thể do sidebar), reset về 0
                    if (parseInt(marginLeft) > 50) {
                        el.style.marginLeft = '0 !important';
                    }
                }
            });
        }
    }, 500);
});

console.log('✅ Sidebar with subscription info loaded!');
</script>

<style>
/* Custom Scrollbar */
.custom-scrollbar {
    scrollbar-width: thin;
    scrollbar-color: #475569 #1e293b;
}

.custom-scrollbar::-webkit-scrollbar {
    width: 6px;
}

.custom-scrollbar::-webkit-scrollbar-track {
    background: #1e293b;
    border-radius: 3px;
}

.custom-scrollbar::-webkit-scrollbar-thumb {
    background: #475569;
    border-radius: 3px;
    transition: background 0.2s ease;
}

.custom-scrollbar::-webkit-scrollbar-thumb:hover {
    background: #64748b;
}

/* XU Currency Styling - SIDEBAR VERSION */
.xu-currency-sidebar {
    background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
    font-weight: bold;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.xu-icon-sidebar {
    margin-right: 4px;
    font-size: 14px;
}

/* Plan Badge Animation for expiring plans */
@keyframes pulse {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.7; }
}

.animate-pulse {
    animation: pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
}

/* Sidebar Styles */
.sidebar-container {
    width: 16rem;
    transition: transform 0.3s ease-in-out;
    display: flex;
    flex-direction: column;
    height: 100vh;
}

/* Mobile Menu Button - Chỉ hiện khi cần */
.mobile-menu-btn {
    transition: all 0.2s ease;
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
}

.mobile-menu-btn:hover {
    transform: scale(1.05);
    box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
}

.mobile-close-btn {
    transition: all 0.2s ease;
}

.mobile-close-btn:hover {
    transform: scale(1.1);
}

/* Mobile Responsive - Ưu tiên cao nhất */
@media (max-width: 1023px) {
    /* FORCE: Đảm bảo content không bao giờ bị đẩy sang phải */
    * {
        margin-left: 0 !important;
    }
    
    .lg\:ml-64, 
    .lg\:ml-16,
    [class*="ml-64"],
    [class*="ml-16"],
    [style*="margin-left"] {
        margin-left: 0 !important;
    }
    
    /* Sidebar positioning on mobile */
    .sidebar-container {
        width: 16rem;
        max-width: 85vw;
        z-index: 40;
        position: fixed;
        left: 0;
        top: 0;
        bottom: 0;
    }
    
    /* Mobile overlay */
    #sidebarOverlay {
        z-index: 30;
    }
    
    /* Hide desktop toggle on mobile */
    .sidebar-toggle {
        display: none !important;
    }
    
    /* Mobile buttons control */
    .mobile-menu-btn {
        display: block;
        position: fixed;
        top: 1rem;
        left: 1rem;
        z-index: 50;
    }
    
    .mobile-close-btn {
        display: block;
    }
    
    /* Body và main content trên mobile */
    body {
        margin: 0 !important;
        padding: 0 !important;
    }
    
    main, 
    .main-content, 
    .content,
    [role="main"],
    .container {
        margin-left: 0 !important;
        width: 100% !important;
        max-width: 100vw !important;
    }
    
    /* Prevent background scroll when sidebar is open */
    body.sidebar-open {
        overflow: hidden;
    }
}

/* Desktop Responsive */
@media (min-width: 1024px) {
    .sidebar-container {
        position: fixed;
        z-index: 50;
        width: 16rem;
        transform: translateX(0) !important;
        transition: width 0.3s ease;
    }
    
    .sidebar-container.sidebar-collapsed {
        width: 4rem;
    }
    
    /* Hide mobile buttons on desktop */
    .mobile-menu-btn,
    .mobile-close-btn {
        display: none !important;
    }
    
    /* Show desktop toggle */
    .sidebar-toggle {
        display: flex !important;
    }
    
    /* Content adjustment - chỉ trên desktop */
    .lg\:ml-64 {
        margin-left: 16rem !important;
        transition: margin-left 0.3s ease;
    }
    
    .lg\:ml-16 {
        margin-left: 4rem !important;
        transition: margin-left 0.3s ease;
    }
}

/* Collapsed state (Desktop only) */
@media (min-width: 1024px) {
    .sidebar-container.sidebar-collapsed .sidebar-text {
        opacity: 0;
        visibility: hidden;
        transition: opacity 0.2s ease, visibility 0.2s ease;
    }

    .sidebar-container.sidebar-collapsed .sidebar-badge {
        opacity: 0;
        visibility: hidden;
        transition: opacity 0.2s ease, visibility 0.2s ease;
    }

    .sidebar-container.sidebar-collapsed .sidebar-item {
        justify-content: center;
        padding-left: 0.75rem;
        padding-right: 0.75rem;
    }

    .sidebar-container.sidebar-collapsed .sidebar-icon {
        margin-right: 0;
    }

    .sidebar-container.sidebar-collapsed .px-4 {
        padding-left: 0.5rem;
        padding-right: 0.5rem;
    }

    .sidebar-container.sidebar-collapsed .sidebar-scroll-indicator,
    .sidebar-container.sidebar-collapsed .sidebar-scroll-top {
        display: none;
    }
    
    .sidebar-container.sidebar-collapsed h3 {
        display: none;
    }
    
    .sidebar-container.sidebar-collapsed .mb-6 {
        margin-bottom: 0.5rem;
    }
    
    .sidebar-container.sidebar-collapsed .mb-3 {
        margin-bottom: 0.25rem;
    }
    
    .sidebar-container.sidebar-collapsed .sidebar-logo {
        margin-right: 0;
    }
    
    .sidebar-container.sidebar-collapsed .flex-1 {
        display: none;
    }
}

/* Sidebar Items */
.sidebar-item {
    transition: all 0.2s ease;
    position: relative;
    display: flex;
    align-items: center;
}

.sidebar-item:hover {
    transform: translateX(2px);
}

.sidebar-item.active {
    background-color: #3b82f6;
    color: white;
}

/* Tooltip for collapsed state (Desktop only) */
@media (min-width: 1024px) {
    .sidebar-container.sidebar-collapsed .sidebar-item:hover::after {
        content: attr(title);
        position: absolute;
        left: 100%;
        top: 50%;
        transform: translateY(-50%);
        margin-left: 0.5rem;
        padding: 0.5rem 0.75rem;
        background-color: #1f2937;
        color: white;
        border-radius: 0.375rem;
        font-size: 0.875rem;
        white-space: nowrap;
        z-index: 1000;
        opacity: 1;
        visibility: visible;
        transition: opacity 0.2s ease;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
    }
}

/* Scroll Indicator */
.sidebar-scroll-indicator {
    background: linear-gradient(to bottom, transparent, rgba(30, 41, 59, 0.8), transparent);
    animation: pulse 2s infinite;
}

@keyframes pulse {
    0%, 100% { opacity: 0.5; }
    50% { opacity: 1; }
}

/* Toggle button */
.sidebar-toggle {
    transition: all 0.2s ease;
}

.sidebar-toggle:hover {
    transform: scale(1.1);
}

/* Smooth transitions */
.lg\:ml-64, .lg\:ml-16 {
    transition: margin-left 0.3s ease;
}

#sidebarOverlay {
    transition: opacity 0.3s ease-in-out;
}

.sidebar-text, .sidebar-badge {
    transition: opacity 0.2s ease, visibility 0.2s ease;
}

.sidebar-icon {
    transition: margin 0.3s ease;
}

/* Force content to respect sidebar changes (Desktop only) */
@media (min-width: 1024px) {
    body:not(.sidebar-collapsed) .lg\:ml-64 {
        margin-left: 16rem !important;
    }

    body.sidebar-collapsed .lg\:ml-64 {
        margin-left: 4rem !important;
    }
}

/* Flex utilities */
.flex-shrink-0 {
    flex-shrink: 0;
}

.truncate {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

/* Scroll to top button */
.sidebar-scroll-top button:hover {
    transform: translateY(-1px);
}

/* Navigation container */
#sidebarNav {
    scroll-behavior: smooth;
}

/* Safe area for mobile notch */
@supports (padding: env(safe-area-inset-left)) {
    @media (max-width: 1023px) {
        .mobile-menu-btn {
            top: calc(1rem + env(safe-area-inset-top));
            left: calc(1rem + env(safe-area-inset-left));
        }
    }
}

/* Prevent text selection on mobile */
@media (max-width: 1023px) {
    .sidebar-container {
        -webkit-user-select: none;
        -moz-user-select: none;
        -ms-user-select: none;
        user-select: none;
    }
}

/* Additional Mobile Layout Fixes */
@media (max-width: 1023px) {
    /* Ensure full width on mobile */
    html, body {
        width: 100% !important;
        overflow-x: hidden;
    }
    
    /* Reset any potential margin/padding issues */
    .container-fluid,
    .container,
    .main-wrapper,
    .app-wrapper {
        margin-left: 0 !important;
        padding-left: 15px !important;
        padding-right: 15px !important;
        width: 100% !important;
        max-width: 100% !important;
    }
    
    /* Specific overrides for common layout classes */
    .row {
        margin-left: 0 !important;
        margin-right: 0 !important;
    }
    
    .col, .col-md, .col-lg, .col-xl, .col-xxl,
    [class*="col-"] {
        padding-left: 15px !important;
        padding-right: 15px !important;
    }
}
</style>