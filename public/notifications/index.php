<?php
require_once __DIR__ . '/../../app/config.php';
require_once __DIR__ . '/../../app/db.php';
require_once __DIR__ . '/../../app/auth.php';
require_once __DIR__ . '/../../app/middleware.php';
require_once __DIR__ . '/../../app/NotificationManager.php';

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Require login
Middleware::requireLogin();

// Log activity
Middleware::logActivity('view_notifications');

// Get user data
$userData = Auth::getUser();
if (!$userData) {
    Auth::logout();
    header('Location: ' . url('login'));
    exit;
}

$userId = $userData['id'];
$userName = $userData['full_name'] ?? 'User';

// Get notifications with pagination
$page = (int)($_GET['page'] ?? 1);
$filter = $_GET['filter'] ?? 'all';
$limit = 20;

$notificationsData = NotificationManager::getUserNotifications($userId, $page, $limit, $filter);
$notifications = $notificationsData['notifications'] ?? [];
$totalPages = $notificationsData['total_pages'] ?? 1;
$totalNotifications = $notificationsData['total'] ?? 0;

// Get unread count
$unreadCount = NotificationManager::getUnreadCount($userId);

$pageTitle = "Thông Báo - AIboost.vn";
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        body { 
            font-family: 'Inter', sans-serif; 
        }
        .fade-in { 
            animation: fadeIn 0.5s ease-in; 
        }
        @keyframes fadeIn { 
            from { opacity: 0; transform: translateY(20px); } 
            to { opacity: 1; transform: translateY(0); } 
        }
        
        .notification-gradient {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        
        .notification-card {
            transition: all 0.3s ease;
        }
        
        .notification-card:hover {
            transform: translateX(5px);
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
        }
        
        .filter-tab.active {
            background-color: #3b82f6;
            color: white;
        }
        
        .unread-indicator {
            width: 10px;
            height: 10px;
            background: #3b82f6;
            border-radius: 50%;
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0% {
                box-shadow: 0 0 0 0 rgba(59, 130, 246, 0.7);
            }
            70% {
                box-shadow: 0 0 0 10px rgba(59, 130, 246, 0);
            }
            100% {
                box-shadow: 0 0 0 0 rgba(59, 130, 246, 0);
            }
        }
        
        .notification-type-info { background: #dbeafe; color: #1e40af; }
        .notification-type-success { background: #d1fae5; color: #065f46; }
        .notification-type-warning { background: #fed7aa; color: #92400e; }
        .notification-type-error { background: #fee2e2; color: #991b1b; }
        .notification-type-promotion { background: #ede9fe; color: #5b21b6; }
        
        .loading-skeleton {
            background: linear-gradient(90deg, #f0f0f0 25%, #e0e0e0 50%, #f0f0f0 75%);
            background-size: 200% 100%;
            animation: loading 1.5s infinite;
        }
        
        @keyframes loading {
            0% { background-position: 200% 0; }
            100% { background-position: -200% 0; }
        }
    </style>
</head>
<body class="min-h-screen bg-gray-50">
    <!-- Include Sidebar -->
    <?php include __DIR__ . '/../partials/sidebar.php'; ?>
    
    <!-- Include Header -->
    <?php include __DIR__ . '/../partials/header.php'; ?>

    <!-- Main Content -->
    <div class="lg:ml-64 pt-16">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
            
            <!-- Page Header -->
            <div class="mb-6">
                <h1 class="text-2xl font-bold text-gray-900">🔔 Thông Báo</h1>
                <p class="text-gray-600 mt-1">Quản lý và xem tất cả thông báo của bạn</p>
            </div>

            <!-- Stats Cards -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                <div class="bg-white rounded-xl shadow-sm p-6 border fade-in">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-gray-600">Tổng thông báo</p>
                            <p class="text-2xl font-bold text-gray-900"><?= number_format($totalNotifications) ?></p>
                        </div>
                        <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center">
                            <i class="fas fa-bell text-blue-600 text-xl"></i>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white rounded-xl shadow-sm p-6 border fade-in">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-gray-600">Chưa đọc</p>
                            <p class="text-2xl font-bold text-orange-600"><?= number_format($unreadCount) ?></p>
                        </div>
                        <div class="w-12 h-12 bg-orange-100 rounded-lg flex items-center justify-center">
                            <i class="fas fa-envelope text-orange-600 text-xl"></i>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white rounded-xl shadow-sm p-6 border fade-in">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-gray-600">Đã đọc</p>
                            <p class="text-2xl font-bold text-green-600"><?= number_format($totalNotifications - $unreadCount) ?></p>
                        </div>
                        <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center">
                            <i class="fas fa-check-circle text-green-600 text-xl"></i>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Filters and Actions -->
            <div class="bg-white rounded-xl shadow-sm border p-4 mb-6 fade-in">
                <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center space-y-4 sm:space-y-0">
                    <div class="flex flex-wrap gap-2">
                        <button class="filter-tab <?= $filter === 'all' ? 'active' : '' ?> px-4 py-2 text-sm rounded-lg border transition-all"
                                onclick="filterNotifications('all')">
                            Tất cả
                        </button>
                        <button class="filter-tab <?= $filter === 'unread' ? 'active' : '' ?> px-4 py-2 text-sm rounded-lg border hover:bg-gray-50 transition-all"
                                onclick="filterNotifications('unread')">
                            <span class="unread-indicator inline-block mr-2"></span>
                            Chưa đọc (<?= $unreadCount ?>)
                        </button>
                        <button class="filter-tab <?= $filter === 'read' ? 'active' : '' ?> px-4 py-2 text-sm rounded-lg border hover:bg-gray-50 transition-all"
                                onclick="filterNotifications('read')">
                            Đã đọc
                        </button>
                    </div>
                    
                    <div class="flex gap-2">
                        <?php if ($unreadCount > 0): ?>
                        <button onclick="markAllAsRead()" class="px-4 py-2 text-sm bg-green-600 text-white rounded-lg hover:bg-green-700 transition-all">
                            <i class="fas fa-check-double mr-1"></i>
                            Đánh dấu tất cả đã đọc
                        </button>
                        <?php endif; ?>
                        <button onclick="refreshNotifications()" class="px-4 py-2 text-sm bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-all">
                            <i class="fas fa-sync-alt mr-1"></i>
                            Làm mới
                        </button>
                    </div>
                </div>
            </div>

            <!-- Notifications List -->
            <div class="space-y-4" id="notificationsList">
                <?php if (empty($notifications)): ?>
                <div class="bg-white rounded-xl shadow-sm border p-12 text-center fade-in">
                    <div class="text-gray-400 text-6xl mb-4">
                        <i class="far fa-bell-slash"></i>
                    </div>
                    <h3 class="text-lg font-semibold text-gray-900 mb-2">Không có thông báo</h3>
                    <p class="text-gray-500">Bạn chưa có thông báo nào. Khi có thông báo mới, chúng sẽ xuất hiện ở đây.</p>
                </div>
                <?php else: ?>
                    <?php foreach ($notifications as $notification): ?>
                    <div class="notification-card bg-white rounded-xl shadow-sm border p-6 fade-in <?= !$notification['is_read'] ? 'border-l-4 border-l-blue-500' : '' ?>"
                         data-id="<?= $notification['id'] ?>">
                        <div class="flex items-start space-x-4">
                            <!-- Icon -->
                            <div class="flex-shrink-0">
                                <div class="w-12 h-12 rounded-full flex items-center justify-center"
                                     style="background-color: <?= $notification['color'] ?>20;">
                                    <i class="<?= $notification['icon'] ?> text-xl" 
                                       style="color: <?= $notification['color'] ?>"></i>
                                </div>
                            </div>
                            
                            <!-- Content -->
                            <div class="flex-1 min-w-0">
                                <div class="flex items-start justify-between">
                                    <div class="flex-1">
                                        <h3 class="text-lg font-semibold text-gray-900 <?= !$notification['is_read'] ? 'font-bold' : '' ?>">
                                            <?= htmlspecialchars($notification['title']) ?>
                                            <?php if (!$notification['is_read']): ?>
                                            <span class="ml-2 inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                                Mới
                                            </span>
                                            <?php endif; ?>
                                        </h3>
                                        
                                        <div class="mt-1 flex items-center space-x-3 text-sm">
                                            <span class="notification-type-<?= $notification['type'] ?> px-2 py-0.5 rounded-full text-xs font-medium">
                                                <?= ucfirst($notification['type']) ?>
                                            </span>
                                            <span class="text-gray-500">
                                                <i class="far fa-clock"></i>
                                                <?= date('d/m/Y H:i', strtotime($notification['created_at'])) ?>
                                            </span>
                                            <?php if ($notification['expires_at']): ?>
                                            <span class="text-orange-500">
                                                <i class="far fa-calendar-times"></i>
                                                Hết hạn: <?= date('d/m/Y', strtotime($notification['expires_at'])) ?>
                                            </span>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <p class="mt-2 text-gray-600">
                                            <?= nl2br(htmlspecialchars($notification['content'])) ?>
                                        </p>
                                        
                                        <?php if ($notification['url']): ?>
                                        <a href="<?= htmlspecialchars($notification['url']) ?>" 
                                           class="inline-flex items-center mt-3 text-blue-600 hover:text-blue-700 text-sm font-medium">
                                            <i class="fas fa-external-link-alt mr-1"></i>
                                            Xem chi tiết
                                        </a>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <!-- Actions -->
                                    <div class="flex items-center space-x-2 ml-4">
                                        <?php if (!$notification['is_read']): ?>
                                        <button onclick="markAsRead(<?= $notification['id'] ?>)" 
                                                class="text-gray-400 hover:text-green-600 transition-colors"
                                                title="Đánh dấu đã đọc">
                                            <i class="fas fa-check"></i>
                                        </button>
                                        <?php endif; ?>
                                        <button onclick="viewNotification(<?= $notification['id'] ?>)" 
                                                class="text-gray-400 hover:text-blue-600 transition-colors"
                                                title="Xem chi tiết">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <!-- Pagination -->
            <?php if ($totalPages > 1): ?>
            <div class="bg-white rounded-xl shadow-sm border p-4 mt-6 fade-in">
                <div class="flex flex-col sm:flex-row items-center justify-between space-y-4 sm:space-y-0">
                    <div class="text-sm text-gray-500">
                        Hiển thị <span class="font-medium"><?= ($page - 1) * $limit + 1 ?></span> - 
                        <span class="font-medium"><?= min($page * $limit, $totalNotifications) ?></span> 
                        trong tổng số <span class="font-medium"><?= number_format($totalNotifications) ?></span> thông báo
                    </div>
                    
                    <div class="flex items-center space-x-2">
                        <?php if ($page > 1): ?>
                        <a href="?page=<?= $page - 1 ?>&filter=<?= $filter ?>" 
                           class="px-3 py-2 text-sm bg-white border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-all">
                            <i class="fas fa-chevron-left mr-1"></i>Trước
                        </a>
                        <?php endif; ?>
                        
                        <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                        <a href="?page=<?= $i ?>&filter=<?= $filter ?>" 
                           class="px-3 py-2 text-sm rounded-lg font-medium transition-all
                                  <?= $i === $page ? 'bg-blue-600 text-white' : 'bg-white border border-gray-300 text-gray-700 hover:bg-gray-50' ?>">
                            <?= $i ?>
                        </a>
                        <?php endfor; ?>
                        
                        <?php if ($page < $totalPages): ?>
                        <a href="?page=<?= $page + 1 ?>&filter=<?= $filter ?>" 
                           class="px-3 py-2 text-sm bg-white border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-all">
                            Sau<i class="fas fa-chevron-right ml-1"></i>
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Include Footer -->
    <?php include __DIR__ . '/../partials/footer.php'; ?>

    <!-- Notification Detail Modal -->
    <div id="notificationModal" class="fixed inset-0 z-50 hidden overflow-y-auto">
        <div class="flex items-center justify-center min-h-screen px-4">
            <div class="fixed inset-0 bg-black opacity-50" onclick="closeModal()"></div>
            <div class="relative bg-white rounded-xl max-w-2xl w-full p-6 max-h-[90vh] overflow-y-auto">
                <button onclick="closeModal()" class="absolute top-4 right-4 text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times text-xl"></i>
                </button>
                <div id="modalContent">
                    <!-- Content will be loaded here -->
                </div>
            </div>
        </div>
    </div>

    <script>
        // Filter notifications
        function filterNotifications(filter) {
            window.location.href = `?filter=${filter}&page=1`;
        }

        // Mark single notification as read
        async function markAsRead(notificationId) {
            try {
                const response = await fetch('/api/mark-notification-read.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        notification_id: notificationId
                    })
                });
                
                const result = await response.json();
                
                if (result.success) {
                    // Update UI
                    const card = document.querySelector(`[data-id="${notificationId}"]`);
                    if (card) {
                        card.classList.remove('border-l-4', 'border-l-blue-500');
                        card.querySelector('.font-bold')?.classList.remove('font-bold');
                        card.querySelector('.bg-blue-100')?.remove();
                        card.querySelector('[onclick*="markAsRead"]')?.remove();
                    }
                    
                    // Update unread count
                    updateUnreadCount();
                }
            } catch (error) {
                console.error('Error marking as read:', error);
            }
        }

        // Mark all as read
        async function markAllAsRead() {
            if (!confirm('Đánh dấu tất cả thông báo là đã đọc?')) return;
            
            try {
                const response = await fetch('/api/mark-all-notifications-read.php', {
                    method: 'POST'
                });
                
                const result = await response.json();
                
                if (result.success) {
                    location.reload();
                }
            } catch (error) {
                console.error('Error marking all as read:', error);
            }
        }

        // View notification detail
        async function viewNotification(notificationId) {
            try {
                const response = await fetch(`/api/notifications.php?action=get&id=${notificationId}`);
                const result = await response.json();
                
                if (result.success && result.notification) {
                    const n = result.notification;
                    
                    const content = `
                        <div class="space-y-4">
                            <div class="flex items-start space-x-3">
                                <div class="w-12 h-12 rounded-full flex items-center justify-center"
                                     style="background-color: ${n.color}20;">
                                    <i class="${n.icon} text-xl" style="color: ${n.color}"></i>
                                </div>
                                <div class="flex-1">
                                    <h2 class="text-xl font-bold text-gray-900">${n.title}</h2>
                                    <div class="flex items-center space-x-3 text-sm text-gray-500 mt-1">
                                        <span class="notification-type-${n.type} px-2 py-0.5 rounded-full text-xs font-medium">
                                            ${n.type}
                                        </span>
                                        <span><i class="far fa-clock"></i> ${new Date(n.created_at).toLocaleString('vi-VN')}</span>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="prose max-w-none">
                                <p class="text-gray-600 whitespace-pre-wrap">${n.content}</p>
                            </div>
                            
                            ${n.url ? `
                            <div class="pt-4 border-t">
                                <a href="${n.url}" target="_blank" 
                                   class="inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                                    <i class="fas fa-external-link-alt mr-2"></i>
                                    Xem chi tiết
                                </a>
                            </div>
                            ` : ''}
                            
                            <div class="pt-4 border-t text-sm text-gray-500">
                                <p>Người gửi: ${n.created_by}</p>
                                ${n.expires_at ? `<p>Hết hạn: ${new Date(n.expires_at).toLocaleDateString('vi-VN')}</p>` : ''}
                                ${n.read_at ? `<p>Đã đọc lúc: ${new Date(n.read_at).toLocaleString('vi-VN')}</p>` : ''}
                            </div>
                        </div>
                    `;
                    
                    document.getElementById('modalContent').innerHTML = content;
                    document.getElementById('notificationModal').classList.remove('hidden');
                    
                    // Mark as read if not already
                    if (!n.is_read) {
                        markAsRead(notificationId);
                    }
                }
            } catch (error) {
                console.error('Error viewing notification:', error);
            }
        }

        // Close modal
        function closeModal() {
            document.getElementById('notificationModal').classList.add('hidden');
        }

        // Update unread count
        async function updateUnreadCount() {
            try {
                const response = await fetch('/api/notifications.php?action=unread_count');
                const result = await response.json();
                
                if (result.success) {
                    // Update badge in header if exists
                    const badge = document.getElementById('notification-badge');
                    if (badge) {
                        badge.textContent = result.count;
                        badge.style.display = result.count > 0 ? 'flex' : 'none';
                    }
                }
            } catch (error) {
                console.error('Error updating unread count:', error);
            }
        }

        // Refresh notifications
        function refreshNotifications() {
            location.reload();
        }

        // Check for new notifications every 30 seconds
        setInterval(() => {
            updateUnreadCount();
        }, 30000);

        // Initialize
        document.addEventListener('DOMContentLoaded', () => {
            updateUnreadCount();
        });
    </script>
</body>
</html>