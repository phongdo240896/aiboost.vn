<?php
$adminUser = Auth::getUser();
?>

<header class="lg:ml-64 bg-white shadow-sm border-b border-gray-200">
    <div class="flex items-center justify-between px-4 py-4">
        
        <!-- Mobile menu button -->
        <button class="lg:hidden p-2 rounded-md text-gray-400 hover:text-gray-500 hover:bg-gray-100" onclick="toggleSidebar()">
            <i class="fas fa-bars text-xl"></i>
        </button>
        
        <!-- Page Title (will be updated by individual pages) -->
        <div class="flex-1 lg:flex-none">
            <h1 class="text-xl font-semibold text-gray-900" id="pageTitle">
                Admin Dashboard
            </h1>
        </div>
        
        <!-- Header Actions -->
        <div class="flex items-center space-x-4">
            
            <!-- Notifications -->
            <div class="relative">
                <button class="p-2 text-gray-400 hover:text-gray-500 hover:bg-gray-100 rounded-full">
                    <i class="fas fa-bell text-lg"></i>
                    <span class="absolute top-0 right-0 block h-2 w-2 bg-red-400 rounded-full"></span>
                </button>
            </div>
            
            <!-- Quick Stats -->
            <div class="hidden md:flex items-center space-x-4 text-sm">
                <div class="text-center">
                    <p class="text-gray-500">Users Online</p>
                    <p class="font-semibold text-green-600">24</p>
                </div>
                <div class="text-center">
                    <p class="text-gray-500">Revenue Today</p>
                    <p class="font-semibold text-blue-600">2.5M ₫</p>
                </div>
            </div>
            
            <!-- User Menu -->
            <div class="relative" id="userMenuContainer">
                <button class="flex items-center space-x-2 p-2 rounded-lg hover:bg-gray-100" onclick="toggleUserMenu()">
                    <div class="w-8 h-8 bg-blue-600 rounded-full flex items-center justify-center">
                        <span class="text-sm font-medium text-white">
                            <?= strtoupper(substr($adminUser['full_name'] ?? 'A', 0, 1)) ?>
                        </span>
                    </div>
                    <div class="hidden md:block text-left">
                        <p class="text-sm font-medium text-gray-900">
                            <?= htmlspecialchars($adminUser['full_name'] ?? 'Admin') ?>
                        </p>
                        <p class="text-xs text-gray-500">Administrator</p>
                    </div>
                    <i class="fas fa-chevron-down text-xs text-gray-400"></i>
                </button>
                
                <!-- Dropdown Menu -->
                <div id="userMenu" class="absolute right-0 mt-2 w-48 bg-white rounded-md shadow-lg border border-gray-200 z-50" style="display: none;">
                    <div class="py-1">
                        <a href="<?= url('admin/profile') ?>" class="flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                            <i class="fas fa-user mr-3"></i>
                            Hồ sơ
                        </a>
                        <a href="<?= url('admin/settings') ?>" class="flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                            <i class="fas fa-cog mr-3"></i>
                            Cài đặt
                        </a>
                        <div class="border-t border-gray-100"></div>
                        <a href="<?= url('') ?>" target="_blank" class="flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                            <i class="fas fa-external-link-alt mr-3"></i>
                            Xem trang chính
                        </a>
                        <div class="border-t border-gray-100"></div>
                        <a href="<?= url('admin/logout') ?>" class="flex items-center px-4 py-2 text-sm text-red-600 hover:bg-red-50">
                            <i class="fas fa-sign-out-alt mr-3"></i>
                            Đăng xuất
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</header>

<script>
function toggleUserMenu() {
    const menu = document.getElementById('userMenu');
    menu.style.display = menu.style.display === 'none' ? 'block' : 'none';
}

// Close menu when clicking outside
document.addEventListener('click', function(event) {
    const container = document.getElementById('userMenuContainer');
    const menu = document.getElementById('userMenu');
    
    if (!container.contains(event.target)) {
        menu.style.display = 'none';
    }
});

// Update page title
function updatePageTitle(title) {
    document.getElementById('pageTitle').textContent = title;
}
</script>