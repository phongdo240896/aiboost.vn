<?php
// Get current page for active menu highlighting
$currentPage = basename($_SERVER['PHP_SELF'], '.php');
$isAdmin = Auth::isAdmin();

if (!$isAdmin) {
    header('Location: ' . url('admin/login'));
    exit;
}

$adminUser = Auth::getUser();
?>

<div class="fixed inset-y-0 left-0 z-50 w-64 bg-white shadow-lg transform -translate-x-full lg:translate-x-0 transition-transform duration-200 ease-in-out" id="sidebar">
    <div class="flex items-center justify-center h-16 bg-blue-600">
        <div class="flex items-center">
            <div class="flex-shrink-0">
                <span class="text-2xl">🚀</span>
            </div>
            <div class="ml-3">
                <h1 class="text-xl font-bold text-white">AIboost Admin</h1>
            </div>
        </div>
    </div>
    
    <nav class="mt-8">
        <div class="px-4 space-y-2">
            
            <!-- Dashboard -->
            <a href="<?= url('admin/dashboard') ?>" 
               class="flex items-center px-4 py-3 text-sm font-medium rounded-lg transition-colors <?= $currentPage === 'dashboard' ? 'bg-blue-100 text-blue-700' : 'text-gray-700 hover:bg-gray-100' ?>">
                <i class="fas fa-chart-line mr-3"></i>
                Dashboard
            </a>
            
            <!-- Users Management -->
            <a href="<?= url('admin/users') ?>" 
               class="flex items-center px-4 py-3 text-sm font-medium rounded-lg transition-colors <?= $currentPage === 'users' ? 'bg-blue-100 text-blue-700' : 'text-gray-700 hover:bg-gray-100' ?>">
                <i class="fas fa-users mr-3"></i>
                Quản lý Users
            </a>
            
            <!-- Wallet Management -->
            <a href="<?= url('admin/wallets') ?>" 
               class="flex items-center px-4 py-3 text-sm font-medium rounded-lg transition-colors <?= $currentPage === 'wallets' ? 'bg-blue-100 text-blue-700' : 'text-gray-700 hover:bg-gray-100' ?>">
                <i class="fas fa-wallet mr-3"></i>
                Quản lý Ví & XU
            </a>
            
            <!-- Services bank_accounts -->
            <a href="<?= url('admin/bank_accounts') ?>" 
               class="flex items-center px-4 py-3 text-sm font-medium rounded-lg transition-colors <?= $currentPage === 'bank_accounts' ? 'bg-blue-100 text-blue-700' : 'text-gray-700 hover:bg-gray-100' ?>">
                <i class="fas fa-university mr-3"></i>
                Quản lý bank
            </a>

            <!-- Services package -->
            <a href="<?= url('admin/package') ?>" 
               class="flex items-center px-4 py-3 text-sm font-medium rounded-lg transition-colors <?= $currentPage === 'package' ? 'bg-blue-100 text-blue-700' : 'text-gray-700 hover:bg-gray-100' ?>">
                <i class="fas fa-box mr-3"></i>
                Quản lý gói cước
            </a>

            <!-- Services Management -->
            <a href="<?= url('admin/services') ?>" 
               class="flex items-center px-4 py-3 text-sm font-medium rounded-lg transition-colors <?= $currentPage === 'services' ? 'bg-blue-100 text-blue-700' : 'text-gray-700 hover:bg-gray-100' ?>">
                <i class="fas fa-cog mr-3"></i>
                Quản lý Dịch vụ
            </a>
            
            <!-- Transactions -->
            <a href="<?= url('admin/bank_monitor') ?>" 
               class="flex items-center px-4 py-3 text-sm font-medium rounded-lg transition-colors <?= $currentPage === 'bank_monitor' ? 'bg-blue-100 text-blue-700' : 'text-gray-700 hover:bg-gray-100' ?>">
                <i class="fas fa-exchange-alt mr-3"></i>
                Giao dịch
            </a>
            
            <!-- Promotions -->
            <a href="<?= url('admin/promotion') ?>" 
               class="flex items-center px-4 py-3 text-sm font-medium rounded-lg transition-colors <?= $currentPage === 'promotion' ? 'bg-blue-100 text-blue-700' : 'text-gray-700 hover:bg-gray-100' ?>">
                <i class="fas fa-tags mr-3"></i>
                Khuyến mãi
            </a>

            <!-- Notifications -->
            <a href="<?= url('admin/notifications') ?>" 
               class="flex items-center px-4 py-3 text-sm font-medium rounded-lg transition-colors <?= $currentPage === 'notifications' ? 'bg-blue-100 text-blue-700' : 'text-gray-700 hover:bg-gray-100' ?>">
                <i class="fas fa-bell mr-3"></i>
                Thông báo
            </a>

            <!-- Email Settings -->
            <a href="<?= url('admin/email_settings') ?>" 
               class="flex items-center px-4 py-3 text-sm font-medium rounded-lg transition-colors <?= $currentPage === 'email_settings' ? 'bg-blue-100 text-blue-700' : 'text-gray-700 hover:bg-gray-100' ?>">
                <i class="fas fa-envelope mr-3"></i>
                Cài đặt Email
            </a>

            <!-- Reports -->
            <a href="<?= url('admin/reports') ?>" 
               class="flex items-center px-4 py-3 text-sm font-medium rounded-lg transition-colors <?= $currentPage === 'reports' ? 'bg-blue-100 text-blue-700' : 'text-gray-700 hover:bg-gray-100' ?>">
                <i class="fas fa-chart-bar mr-3"></i>
                Báo cáo
            </a>
            
            <!-- Settings -->
            <a href="<?= url('admin/subscription_reminders') ?>" 
               class="flex items-center px-4 py-3 text-sm font-medium rounded-lg transition-colors <?= $currentPage === 'subscription_reminders' ? 'bg-blue-100 text-blue-700' : 'text-gray-700 hover:bg-gray-100' ?>">
                <i class="fas fa-cogs mr-3"></i>
                Quản lý gói
            </a>
            
        </div>
        
        <!-- Divider -->
        <div class="my-6 border-t border-gray-200"></div>
        
        <!-- Quick Actions -->
        <div class="px-4">
            <p class="text-xs font-semibold text-gray-400 uppercase tracking-wide mb-4">Quick Actions</p>
            
            <a href="<?= url('admin/wallets') ?>#add-xu" 
               class="flex items-center px-4 py-2 text-sm text-green-700 bg-green-50 rounded-lg hover:bg-green-100 transition-colors mb-2">
                <i class="fas fa-plus mr-3"></i>
                Cộng XU
            </a>
            
            <a href="<?= url('admin/users') ?>#new-user" 
               class="flex items-center px-4 py-2 text-sm text-blue-700 bg-blue-50 rounded-lg hover:bg-blue-100 transition-colors mb-2">
                <i class="fas fa-user-plus mr-3"></i>
                Tạo User
            </a>
            
            <a href="<?= url('admin/reports') ?>#export" 
               class="flex items-center px-4 py-2 text-sm text-purple-700 bg-purple-50 rounded-lg hover:bg-purple-100 transition-colors">
                <i class="fas fa-download mr-3"></i>
                Xuất báo cáo
            </a>
        </div>
    </nav>
    
    <!-- Admin Info -->
    <div class="absolute bottom-0 left-0 right-0 p-4 border-t border-gray-200">
        <div class="flex items-center">
            <div class="flex-shrink-0">
                <div class="w-8 h-8 bg-blue-600 rounded-full flex items-center justify-center">
                    <span class="text-sm font-medium text-white">
                        <?= strtoupper(substr($adminUser['full_name'] ?? 'A', 0, 1)) ?>
                    </span>
                </div>
            </div>
            <div class="ml-3 flex-1 min-w-0">
                <p class="text-sm font-medium text-gray-900 truncate">
                    <?= htmlspecialchars($adminUser['full_name'] ?? 'Admin') ?>
                </p>
                <p class="text-xs text-gray-500 truncate">Admin</p>
            </div>
        </div>
    </div>
</div>

<!-- Mobile sidebar overlay -->
<div class="fixed inset-0 z-40 lg:hidden" id="sidebar-overlay" style="display: none;">
    <div class="fixed inset-0 bg-gray-600 bg-opacity-75" onclick="toggleSidebar()"></div>
</div>

<script>
function toggleSidebar() {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebar-overlay');
    
    if (sidebar.classList.contains('-translate-x-full')) {
        sidebar.classList.remove('-translate-x-full');
        overlay.style.display = 'block';
    } else {
        sidebar.classList.add('-translate-x-full');
        overlay.style.display = 'none';
    }
}
</script>