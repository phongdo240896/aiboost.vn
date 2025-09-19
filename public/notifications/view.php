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

// Get user data
$userData = Auth::getUser();
if (!$userData) {
    Auth::logout();
    header('Location: ' . url('login'));
    exit;
}

$userId = $userData['id'];
$userName = $userData['full_name'] ?? 'User';

// Get notification ID from query
$notificationId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$notificationId) {
    header('Location: ' . url('notifications'));
    exit;
}

// Get notification details
$notification = null;
$relatedNotifications = [];

try {
    $pdo = $db->getPdo();
    
    // Get the notification with user read status
    $stmt = $pdo->prepare("
        SELECT n.*, un.is_read, un.read_at 
        FROM notifications n
        INNER JOIN user_notifications un ON n.id = un.notification_id
        WHERE n.id = ? AND un.user_id = ?
    ");
    $stmt->execute([$notificationId, $userId]);
    $notification = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$notification) {
        // Check if notification exists but not for this user
        $stmt = $pdo->prepare("SELECT * FROM notifications WHERE id = ?");
        $stmt->execute([$notificationId]);
        $exists = $stmt->fetch();
        
        if ($exists) {
            // Notification exists but not for this user
            $_SESSION['error'] = 'Bạn không có quyền xem thông báo này.';
        } else {
            $_SESSION['error'] = 'Thông báo không tồn tại.';
        }
        header('Location: ' . url('notifications'));
        exit;
    }
    
    // Mark as read if not already
    if (!$notification['is_read']) {
        NotificationManager::markAsRead($notificationId, $userId);
        $notification['is_read'] = 1;
        $notification['read_at'] = date('Y-m-d H:i:s');
    }
    
    // Log activity
    Middleware::logActivity('view_notification', ['notification_id' => $notificationId]);
    
    // Get related notifications (same type, recent)
    $stmt = $pdo->prepare("
        SELECT n.*, un.is_read
        FROM notifications n
        INNER JOIN user_notifications un ON n.id = un.notification_id
        WHERE un.user_id = ? 
        AND n.type = ?
        AND n.id != ?
        AND n.status = 'active'
        ORDER BY n.created_at DESC
        LIMIT 3
    ");
    $stmt->execute([$userId, $notification['type'], $notificationId]);
    $relatedNotifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    error_log('View notification error: ' . $e->getMessage());
    $_SESSION['error'] = 'Có lỗi xảy ra khi tải thông báo.';
    header('Location: ' . url('notifications'));
    exit;
}

// Get notification type info
function getNotificationTypeInfo($type) {
    $types = [
        'info' => ['label' => 'Thông tin', 'class' => 'bg-blue-100 text-blue-800', 'icon' => 'fas fa-info-circle'],
        'success' => ['label' => 'Thành công', 'class' => 'bg-green-100 text-green-800', 'icon' => 'fas fa-check-circle'],
        'warning' => ['label' => 'Cảnh báo', 'class' => 'bg-yellow-100 text-yellow-800', 'icon' => 'fas fa-exclamation-triangle'],
        'error' => ['label' => 'Lỗi', 'class' => 'bg-red-100 text-red-800', 'icon' => 'fas fa-times-circle'],
        'promotion' => ['label' => 'Khuyến mãi', 'class' => 'bg-purple-100 text-purple-800', 'icon' => 'fas fa-gift']
    ];
    
    return $types[$type] ?? $types['info'];
}

$typeInfo = getNotificationTypeInfo($notification['type']);
$pageTitle = "Thông Báo - " . htmlspecialchars($notification['title']);
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?> - AIboost.vn</title>
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
            background: linear-gradient(135deg, <?= $notification['color'] ?>20 0%, <?= $notification['color'] ?>10 100%);
        }
        
        .prose {
            max-width: none;
            color: #374151;
            line-height: 1.75;
        }
        
        .prose p {
            margin-bottom: 1rem;
        }
        
        .prose a {
            color: #3b82f6;
            text-decoration: underline;
        }
        
        .prose a:hover {
            color: #2563eb;
        }
        
        .share-button {
            transition: all 0.3s ease;
        }
        
        .share-button:hover {
            transform: scale(1.1);
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
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
            
            <!-- Breadcrumb -->
            <nav class="flex mb-6" aria-label="Breadcrumb">
                <ol class="inline-flex items-center space-x-1 md:space-x-3">
                    <li class="inline-flex items-center">
                        <a href="<?= url() ?>" class="inline-flex items-center text-sm font-medium text-gray-700 hover:text-blue-600">
                            <svg class="w-4 h-4 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                <path d="M10.707 2.293a1 1 0 00-1.414 0l-7 7a1 1 0 001.414 1.414L4 10.414V17a1 1 0 001 1h2a1 1 0 001-1v-2a1 1 0 011-1h2a1 1 0 011 1v2a1 1 0 001 1h2a1 1 0 001-1v-6.586l.293.293a1 1 0 001.414-1.414l-7-7z"></path>
                            </svg>
                            Trang chủ
                        </a>
                    </li>
                    <li>
                        <div class="flex items-center">
                            <svg class="w-4 h-4 text-gray-400" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd"></path>
                            </svg>
                            <a href="<?= url('notifications') ?>" class="ml-1 text-sm font-medium text-gray-700 hover:text-blue-600 md:ml-2">
                                Thông báo
                            </a>
                        </div>
                    </li>
                    <li aria-current="page">
                        <div class="flex items-center">
                            <svg class="w-4 h-4 text-gray-400" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd"></path>
                            </svg>
                            <span class="ml-1 text-sm font-medium text-gray-500 md:ml-2">Chi tiết</span>
                        </div>
                    </li>
                </ol>
            </nav>

            <!-- Notification Detail Card -->
            <div class="bg-white rounded-xl shadow-sm border overflow-hidden fade-in">
                <!-- Header with gradient background -->
                <div class="notification-gradient p-6 sm:p-8 border-b">
                    <div class="flex items-start space-x-4">
                        <div class="flex-shrink-0">
                            <div class="w-16 h-16 rounded-full flex items-center justify-center"
                                 style="background-color: <?= $notification['color'] ?>20;">
                                <i class="<?= $notification['icon'] ?> text-2xl" 
                                   style="color: <?= $notification['color'] ?>"></i>
                            </div>
                        </div>
                        <div class="flex-1 min-w-0">
                            <h1 class="text-2xl font-bold text-gray-900 mb-2">
                                <?= htmlspecialchars($notification['title']) ?>
                            </h1>
                            <div class="flex flex-wrap items-center gap-3 text-sm">
                                <span class="<?= $typeInfo['class'] ?> px-3 py-1 rounded-full font-medium">
                                    <i class="<?= $typeInfo['icon'] ?> mr-1"></i>
                                    <?= $typeInfo['label'] ?>
                                </span>
                                <span class="text-gray-600">
                                    <i class="far fa-clock mr-1"></i>
                                    <?= date('d/m/Y H:i', strtotime($notification['created_at'])) ?>
                                </span>
                                <?php if ($notification['expires_at']): ?>
                                    <?php 
                                    $isExpired = strtotime($notification['expires_at']) < time();
                                    ?>
                                    <span class="<?= $isExpired ? 'text-red-600' : 'text-orange-600' ?>">
                                        <i class="far fa-calendar-times mr-1"></i>
                                        <?= $isExpired ? 'Đã hết hạn' : 'Hết hạn' ?>: <?= date('d/m/Y', strtotime($notification['expires_at'])) ?>
                                    </span>
                                <?php endif; ?>
                                <?php if ($notification['read_at']): ?>
                                    <span class="text-green-600">
                                        <i class="fas fa-check-circle mr-1"></i>
                                        Đã đọc lúc <?= date('H:i d/m', strtotime($notification['read_at'])) ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Content -->
                <div class="p-6 sm:p-8">
                    <div class="prose max-w-none text-gray-700 leading-relaxed">
                        <?= nl2br(htmlspecialchars($notification['content'])) ?>
                    </div>

                    <?php if ($notification['url']): ?>
                    <div class="mt-6 p-4 bg-blue-50 rounded-lg border border-blue-200">
                        <p class="text-sm text-blue-700 mb-3">
                            <i class="fas fa-link mr-2"></i>
                            Liên kết liên quan:
                        </p>
                        <a href="<?= htmlspecialchars($notification['url']) ?>" 
                           target="_blank"
                           class="inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
                            <i class="fas fa-external-link-alt mr-2"></i>
                            Truy cập liên kết
                        </a>
                    </div>
                    <?php endif; ?>

                    <!-- Meta Information -->
                    <div class="mt-8 pt-6 border-t border-gray-200">
                        <dl class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm">
                            <div>
                                <dt class="font-medium text-gray-500">Người gửi</dt>
                                <dd class="mt-1 text-gray-900"><?= htmlspecialchars($notification['created_by']) ?></dd>
                            </div>
                            <div>
                                <dt class="font-medium text-gray-500">Độ ưu tiên</dt>
                                <dd class="mt-1">
                                    <?php 
                                    $priority = (int)$notification['priority'];
                                    $priorityClass = $priority >= 7 ? 'text-red-600' : ($priority >= 4 ? 'text-orange-600' : 'text-gray-600');
                                    ?>
                                    <span class="<?= $priorityClass ?>">
                                        <?= str_repeat('⭐', min(5, ceil($priority / 2))) ?>
                                        <span class="ml-1">(<?= $priority ?>/10)</span>
                                    </span>
                                </dd>
                            </div>
                            <div>
                                <dt class="font-medium text-gray-500">Ngày tạo</dt>
                                <dd class="mt-1 text-gray-900"><?= date('H:i:s d/m/Y', strtotime($notification['created_at'])) ?></dd>
                            </div>
                            <div>
                                <dt class="font-medium text-gray-500">Trạng thái</dt>
                                <dd class="mt-1">
                                    <?php if ($notification['status'] === 'active'): ?>
                                        <span class="text-green-600">
                                            <i class="fas fa-check-circle mr-1"></i>
                                            Đang hoạt động
                                        </span>
                                    <?php else: ?>
                                        <span class="text-gray-600">
                                            <i class="fas fa-pause-circle mr-1"></i>
                                            <?= ucfirst($notification['status']) ?>
                                        </span>
                                    <?php endif; ?>
                                </dd>
                            </div>
                        </dl>
                    </div>

                    <!-- Actions -->
                    <div class="mt-6 pt-6 border-t border-gray-200 flex flex-wrap items-center justify-between gap-4">
                        <div class="flex items-center gap-3">
                            <!-- Share buttons -->
                            <button onclick="shareNotification('facebook')" 
                                    class="share-button p-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700"
                                    title="Chia sẻ lên Facebook">
                                <i class="fab fa-facebook-f"></i>
                            </button>
                            <button onclick="shareNotification('twitter')" 
                                    class="share-button p-2 bg-sky-500 text-white rounded-lg hover:bg-sky-600"
                                    title="Chia sẻ lên Twitter">
                                <i class="fab fa-twitter"></i>
                            </button>
                            <button onclick="copyLink()" 
                                    class="share-button p-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700"
                                    title="Sao chép liên kết">
                                <i class="fas fa-link"></i>
                            </button>
                        </div>
                        
                        <a href="<?= url('notifications') ?>" 
                           class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition-colors">
                            <i class="fas fa-arrow-left mr-2"></i>
                            Quay lại danh sách
                        </a>
                    </div>
                </div>
            </div>

            <!-- Related Notifications -->
            <?php if (!empty($relatedNotifications)): ?>
            <div class="mt-8">
                <h2 class="text-lg font-semibold text-gray-900 mb-4">
                    <i class="fas fa-bell mr-2"></i>
                    Thông báo liên quan
                </h2>
                <div class="grid gap-4">
                    <?php foreach ($relatedNotifications as $related): ?>
                    <a href="<?= url('notifications/view.php?id=' . $related['id']) ?>" 
                       class="bg-white rounded-lg shadow-sm border p-4 hover:shadow-md transition-all <?= !$related['is_read'] ? 'border-l-4 border-l-blue-500' : '' ?>">
                        <div class="flex items-start space-x-3">
                            <div class="flex-shrink-0">
                                <div class="w-10 h-10 rounded-full flex items-center justify-center"
                                     style="background-color: <?= $related['color'] ?>20;">
                                    <i class="<?= $related['icon'] ?> text-sm" 
                                       style="color: <?= $related['color'] ?>"></i>
                                </div>
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-medium text-gray-900 <?= !$related['is_read'] ? 'font-semibold' : '' ?>">
                                    <?= htmlspecialchars($related['title']) ?>
                                    <?php if (!$related['is_read']): ?>
                                    <span class="ml-2 text-xs bg-blue-100 text-blue-800 px-2 py-0.5 rounded-full">Mới</span>
                                    <?php endif; ?>
                                </p>
                                <p class="text-xs text-gray-600 mt-1 line-clamp-2">
                                    <?= htmlspecialchars(substr($related['content'], 0, 150)) ?>...
                                </p>
                                <p class="text-xs text-gray-400 mt-2">
                                    <i class="far fa-clock"></i>
                                    <?= date('d/m/Y H:i', strtotime($related['created_at'])) ?>
                                </p>
                            </div>
                        </div>
                    </a>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Include Footer -->
    <?php include __DIR__ . '/../partials/footer.php'; ?>

    <!-- Toast notification for copy link -->
    <div id="copyToast" class="fixed bottom-4 right-4 bg-green-600 text-white px-4 py-2 rounded-lg shadow-lg hidden">
        <i class="fas fa-check-circle mr-2"></i>
        Đã sao chép liên kết!
    </div>

    <script>
        // Share notification
        function shareNotification(platform) {
            const url = window.location.href;
            const title = <?= json_encode($notification['title']) ?>;
            let shareUrl = '';
            
            switch(platform) {
                case 'facebook':
                    shareUrl = `https://www.facebook.com/sharer/sharer.php?u=${encodeURIComponent(url)}`;
                    break;
                case 'twitter':
                    shareUrl = `https://twitter.com/intent/tweet?text=${encodeURIComponent(title)}&url=${encodeURIComponent(url)}`;
                    break;
            }
            
            if (shareUrl) {
                window.open(shareUrl, '_blank', 'width=600,height=400');
            }
        }
        
        // Copy link to clipboard
        function copyLink() {
            const url = window.location.href;
            navigator.clipboard.writeText(url).then(() => {
                const toast = document.getElementById('copyToast');
                toast.classList.remove('hidden');
                setTimeout(() => {
                    toast.classList.add('hidden');
                }, 3000);
            });
        }
        
        // Line clamp utility
        document.querySelectorAll('.line-clamp-2').forEach(el => {
            el.style.overflow = 'hidden';
            el.style.display = '-webkit-box';
            el.style.webkitBoxOrient = 'vertical';
            el.style.webkitLineClamp = '2';
        });
    </script>
</body>
</html>