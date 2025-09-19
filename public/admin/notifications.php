<?php   
session_start();
require_once '../../app/config.php';
require_once '../../app/db.php';
require_once '../../app/auth.php';
require_once '../../app/NotificationManager.php';

// Initialize Auth with database
if (isset($db)) {
    Auth::init($db);
}

// Kiểm tra quyền admin
if (!Auth::isAdmin()) {
    header('Location: /login.php');
    exit;
}

$currentUser = Auth::getUser();

// Xử lý các action
$action = $_GET['action'] ?? 'list';
$message = '';
$messageType = 'info';

// Xử lý tạo thông báo mới
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'create') {
    if (!Auth::verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $message = 'Token không hợp lệ!';
        $messageType = 'error';
    } else {
        $data = [
            'title' => $_POST['title'] ?? '',
            'content' => $_POST['content'] ?? '',
            'type' => $_POST['type'] ?? 'info',
            'icon' => $_POST['icon'] ?? '',
            'color' => $_POST['color'] ?? '',
            'target_users' => $_POST['target_users'] ?? 'all',
            'target_role' => $_POST['target_role'] ?? null,
            'target_user_ids' => $_POST['target_user_ids'] ?? null,
            'priority' => (int)($_POST['priority'] ?? 0),
            'url' => $_POST['url'] ?? null,
            'is_popup' => isset($_POST['is_popup']) ? 1 : 0,
            'is_email' => isset($_POST['is_email']) ? 1 : 0,
            'expires_at' => !empty($_POST['expires_at']) ? $_POST['expires_at'] : null,
            'created_by' => $currentUser['username'] ?? 'Admin',
            'status' => $_POST['status'] ?? 'active'
        ];
        
        $result = NotificationManager::create($data);
        
        if ($result['success']) {
            $message = 'Tạo thông báo thành công!';
            $messageType = 'success';
            header('Location: notifications?message=' . urlencode($message) . '&type=success');
            exit;
        } else {
            $message = $result['message'];
            $messageType = 'error';
        }
    }
}

// Xử lý xóa thông báo
if ($action === 'delete' && isset($_GET['id'])) {
    $result = NotificationManager::delete($_GET['id']);
    if ($result['success']) {
        header('Location: notifications?message=' . urlencode('Đã xóa thông báo') . '&type=success');
        exit;
    }
}

// Xử lý cập nhật trạng thái
if ($action === 'toggle_status' && isset($_GET['id'])) {
    $status = $_GET['status'] ?? 'inactive';
    $newStatus = $status === 'active' ? 'inactive' : 'active';
    $result = NotificationManager::updateStatus($_GET['id'], $newStatus);
    if ($result['success']) {
        header('Location: notifications?message=' . urlencode('Đã cập nhật trạng thái') . '&type=success');
        exit;
    }
}

// Lấy danh sách thông báo cho admin
$page = (int)($_GET['page'] ?? 1);
$filter = [
    'type' => $_GET['filter_type'] ?? '',
    'status' => $_GET['filter_status'] ?? ''
];

$notificationsData = NotificationManager::getAdminNotifications($page, 20, $filter);
$notifications = $notificationsData['notifications'] ?? [];
$totalPages = $notificationsData['total_pages'] ?? 1;

// Lấy templates
$templates = NotificationManager::getTemplates();

// Message từ query string
if (isset($_GET['message'])) {
    $message = urldecode($_GET['message']);
    $messageType = $_GET['type'] ?? 'info';
}

$csrfToken = Auth::generateCSRFToken();
$pageTitle = "Quản Lý Thông Báo - Admin - AIboost.vn";
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?></title>
    
    <!-- CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/summernote@0.8.18/dist/summernote-lite.min.css" rel="stylesheet">
    
    <style>
        :root {
            --primary-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --card-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
        }
        
        body {
            background: #f3f4f6;
            font-family: 'Inter', sans-serif;
        }
        
        .admin-header {
            background: var(--primary-gradient);
            color: white;
            padding: 2rem 0;
            margin-bottom: 2rem;
            box-shadow: var(--card-shadow);
        }
        
        .notification-card {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            margin-bottom: 1rem;
            transition: all 0.3s ease;
        }
        
        .notification-card:hover {
            transform: translateY(-2px);
            box-shadow: var(--card-shadow);
        }
        
        .notification-type {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 500;
        }
        
        .type-info { background: #dbeafe; color: #1e40af; }
        .type-success { background: #d1fae5; color: #065f46; }
        .type-warning { background: #fed7aa; color: #92400e; }
        .type-error { background: #fee2e2; color: #991b1b; }
        .type-promotion { background: #ede9fe; color: #5b21b6; }
        
        .status-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 500;
        }
        
        .status-active { background: #10b981; color: white; }
        .status-inactive { background: #6b7280; color: white; }
        .status-draft { background: #f59e0b; color: white; }
        
        .btn-gradient {
            background: var(--primary-gradient);
            color: white;
            border: none;
            padding: 0.75rem 2rem;
            border-radius: 8px;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        
        .btn-gradient:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(102, 126, 234, 0.4);
            color: white;
        }
        
        .stats-card {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            text-align: center;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        }
        
        .stats-card h3 {
            color: #667eea;
            font-size: 2rem;
            margin: 0;
        }
        
        .filter-card {
            background: white;
            border-radius: 12px;
            padding: 1rem;
            margin-bottom: 2rem;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        }
        
        .modal-header {
            background: var(--primary-gradient);
            color: white;
            border-radius: 12px 12px 0 0;
        }
        
        .form-label {
            font-weight: 600;
            color: #374151;
            margin-bottom: 0.5rem;
        }
        
        .form-control, .form-select {
            border: 2px solid #e5e7eb;
            border-radius: 8px;
            padding: 0.75rem;
        }
        
        .form-control:focus, .form-select:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }
        
        .target-options {
            display: none;
        }
        
        .target-options.active {
            display: block;
        }
        
        .icon-preview {
            font-size: 2rem;
            margin-left: 1rem;
        }
        
        .template-selector {
            cursor: pointer;
            padding: 0.5rem;
            border: 2px solid #e5e7eb;
            border-radius: 8px;
            margin-bottom: 0.5rem;
            transition: all 0.3s ease;
        }
        
        .template-selector:hover {
            border-color: #667eea;
            background: #f3f4f6;
        }
        
        .recipient-stats {
            display: flex;
            gap: 1rem;
            font-size: 0.875rem;
        }
        
        .recipient-stats span {
            padding: 0.25rem 0.5rem;
            background: #f3f4f6;
            border-radius: 4px;
        }
        
        .action-buttons {
            display: flex;
            gap: 0.5rem;
        }
        
        .btn-icon {
            width: 32px;
            height: 32px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 6px;
            border: none;
            transition: all 0.3s ease;
        }
        
        .btn-view { background: #3b82f6; color: white; }
        .btn-edit { background: #10b981; color: white; }
        .btn-delete { background: #ef4444; color: white; }
        
        .btn-icon:hover {
            transform: scale(1.1);
        }
        
        @media (max-width: 768px) {
            .notification-card {
                padding: 1rem;
            }
            
            .admin-header h1 {
                font-size: 1.5rem;
            }
        }
    </style>
</head>
<body class="min-h-screen bg-gray-50">
    <?php include __DIR__ . '/partials/sidebar.php'; ?>
    <?php include __DIR__ . '/partials/header.php'; ?>
    
    <div class="lg:ml-64">
        <!-- Original admin-header section -->
        <div class="admin-header">
            <div class="container">
                <div class="row align-items-center">
                    <div class="col-md-6">
                        <h1><i class="fas fa-bell"></i> Quản Lý Thông Báo</h1>
                        <p class="mb-0">Tạo và quản lý thông báo hệ thống</p>
                    </div>
                    <div class="col-md-6 text-md-end">
                        <button class="btn btn-light" data-bs-toggle="modal" data-bs-target="#createModal">
                            <i class="fas fa-plus"></i> Tạo Thông Báo Mới
                        </button>
                        <a href="/admin" class="btn btn-outline-light ms-2">
                            <i class="fas fa-arrow-left"></i> Quay lại
                        </a>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="container">
            <!-- Messages -->
            <?php if ($message): ?>
            <div class="alert alert-<?php echo $messageType === 'error' ? 'danger' : $messageType; ?> alert-dismissible fade show" role="alert">
                <?php echo htmlspecialchars($message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>
            
            <!-- Statistics -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="stats-card">
                        <h3><?php echo $notificationsData['total'] ?? 0; ?></h3>
                        <p class="text-muted mb-0">Tổng thông báo</p>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stats-card">
                        <h3><?php echo count(array_filter($notifications, fn($n) => $n['status'] === 'active')); ?></h3>
                        <p class="text-muted mb-0">Đang hoạt động</p>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stats-card">
                        <h3><?php echo array_sum(array_column($notifications, 'total_recipients')); ?></h3>
                        <p class="text-muted mb-0">Tổng người nhận</p>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stats-card">
                        <h3><?php echo array_sum(array_column($notifications, 'total_read')); ?></h3>
                        <p class="text-muted mb-0">Đã đọc</p>
                    </div>
                </div>
            </div>
            
            <!-- Filter -->
            <div class="filter-card">
                <form method="GET" class="row g-3">
                    <div class="col-md-3">
                        <select name="filter_type" class="form-select" onchange="this.form.submit()">
                            <option value="">Tất cả loại</option>
                            <option value="info" <?php echo $filter['type'] === 'info' ? 'selected' : ''; ?>>Thông tin</option>
                            <option value="success" <?php echo $filter['type'] === 'success' ? 'selected' : ''; ?>>Thành công</option>
                            <option value="warning" <?php echo $filter['type'] === 'warning' ? 'selected' : ''; ?>>Cảnh báo</option>
                            <option value="error" <?php echo $filter['type'] === 'error' ? 'selected' : ''; ?>>Lỗi</option>
                            <option value="promotion" <?php echo $filter['type'] === 'promotion' ? 'selected' : ''; ?>>Khuyến mãi</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <select name="filter_status" class="form-select" onchange="this.form.submit()">
                            <option value="">Tất cả trạng thái</option>
                            <option value="active" <?php echo $filter['status'] === 'active' ? 'selected' : ''; ?>>Hoạt động</option>
                            <option value="inactive" <?php echo $filter['status'] === 'inactive' ? 'selected' : ''; ?>>Tạm dừng</option>
                            <option value="draft" <?php echo $filter['status'] === 'draft' ? 'selected' : ''; ?>>Nháp</option>
                        </select>
                    </div>
                    <div class="col-md-6 text-end">
                        <span>Hiển thị: <?php echo count($notifications); ?> / <?php echo $notificationsData['total'] ?? 0; ?> thông báo</span>
                    </div>
                </form>
            </div>
            
            <!-- Notifications List -->
            <div class="notifications-list">
                <?php if (empty($notifications)): ?>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i> Chưa có thông báo nào
                </div>
                <?php else: ?>
                    <?php foreach ($notifications as $notification): ?>
                    <div class="notification-card">
                        <div class="row align-items-center">
                            <div class="col-md-1 text-center">
                                <i class="<?php echo htmlspecialchars($notification['icon']); ?> fa-2x" 
                                   style="color: <?php echo htmlspecialchars($notification['color']); ?>"></i>
                            </div>
                            <div class="col-md-7">
                                <h5 class="mb-1">
                                    <?php echo htmlspecialchars($notification['title']); ?>
                                    <span class="notification-type type-<?php echo $notification['type']; ?>">
                                        <?php echo ucfirst($notification['type']); ?>
                                    </span>
                                </h5>
                                <p class="text-muted mb-2">
                                    <?php echo nl2br(htmlspecialchars(substr($notification['content'], 0, 150))); ?>
                                    <?php if (strlen($notification['content']) > 150): ?>...<?php endif; ?>
                                </p>
                                <div class="recipient-stats">
                                    <span><i class="fas fa-users"></i> <?php echo $notification['total_recipients']; ?> người nhận</span>
                                    <span><i class="fas fa-eye"></i> <?php echo $notification['total_read']; ?> đã đọc</span>
                                    <span><i class="fas fa-percentage"></i> <?php 
                                        $readRate = $notification['total_recipients'] > 0 
                                            ? round(($notification['total_read'] / $notification['total_recipients']) * 100) 
                                            : 0;
                                        echo $readRate; ?>% tỷ lệ đọc
                                    </span>
                                    <span><i class="fas fa-calendar"></i> <?php echo date('d/m/Y H:i', strtotime($notification['created_at'])); ?></span>
                                </div>
                            </div>
                            <div class="col-md-2 text-center">
                                <span class="status-badge status-<?php echo $notification['status']; ?>">
                                    <?php 
                                    $statusText = [
                                        'active' => 'Hoạt động',
                                        'inactive' => 'Tạm dừng',
                                        'draft' => 'Nháp'
                                    ];
                                    echo $statusText[$notification['status']] ?? 'Unknown';
                                    ?>
                                </span>
                                <?php if ($notification['expires_at']): ?>
                                <div class="mt-2 small text-muted">
                                    <i class="fas fa-clock"></i> Hết hạn: <?php echo date('d/m/Y', strtotime($notification['expires_at'])); ?>
                                </div>
                                <?php endif; ?>
                            </div>
                            <div class="col-md-2">
                                <div class="action-buttons justify-content-end">
                                    <button class="btn-icon btn-view" onclick="viewNotification(<?php echo $notification['id']; ?>)" title="Xem chi tiết">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <button class="btn-icon btn-edit" onclick="editNotification(<?php echo $notification['id']; ?>)" title="Sửa">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <a href="?action=toggle_status&id=<?php echo $notification['id']; ?>&status=<?php echo $notification['status']; ?>" 
                                       class="btn-icon btn-edit" title="Đổi trạng thái">
                                        <i class="fas fa-power-off"></i>
                                    </a>
                                    <button class="btn-icon btn-delete" onclick="deleteNotification(<?php echo $notification['id']; ?>)" title="Xóa">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            
            <!-- Pagination -->
            <?php if ($totalPages > 1): ?>
            <nav aria-label="Page navigation" class="mt-4">
                <ul class="pagination justify-content-center">
                    <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                        <a class="page-link" href="?page=<?php echo $page - 1; ?>&filter_type=<?php echo $filter['type']; ?>&filter_status=<?php echo $filter['status']; ?>">
                            <i class="fas fa-chevron-left"></i>
                        </a>
                    </li>
                    
                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                    <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                        <a class="page-link" href="?page=<?php echo $i; ?>&filter_type=<?php echo $filter['type']; ?>&filter_status=<?php echo $filter['status']; ?>">
                            <?php echo $i; ?>
                        </a>
                    </li>
                    <?php endfor; ?>
                    
                    <li class="page-item <?php echo $page >= $totalPages ? 'disabled' : ''; ?>">
                        <a class="page-link" href="?page=<?php echo $page + 1; ?>&filter_type=<?php echo $filter['type']; ?>&filter_status=<?php echo $filter['status']; ?>">
                            <i class="fas fa-chevron-right"></i>
                        </a>
                    </li>
                </ul>
            </nav>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Create Modal -->
    <div class="modal fade" id="createModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-plus-circle"></i> Tạo Thông Báo Mới</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="?action=create">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                    <div class="modal-body">
                        
                        <div class="row">
                            <div class="col-md-8">
                                <div class="mb-3">
                                    <label class="form-label">Tiêu đề <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" name="title" id="notification-title" required>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label">Loại thông báo</label>
                                    <select class="form-select" name="type" id="notification-type">
                                        <option value="info">Thông tin</option>
                                        <option value="success">Thành công</option>
                                        <option value="warning">Cảnh báo</option>
                                        <option value="error">Lỗi</option>
                                        <option value="promotion">Khuyến mãi</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Nội dung <span class="text-danger">*</span></label>
                            <textarea class="form-control" name="content" id="notification-content" rows="5" required></textarea>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Đối tượng nhận</label>
                            <select class="form-select" name="target_users" id="target-users" onchange="toggleTargetOptions()">
                                <option value="all">Tất cả người dùng</option>
                                <option value="specific">Người dùng cụ thể</option>
                            </select>
                        </div>
                        
                        <div class="target-options" id="target-specific-options">
                            <div class="mb-3">
                                <label class="form-label">Chọn người dùng</label>
                                <div class="user-select-container" style="max-height: 300px; overflow-y: auto; border: 2px solid #e5e7eb; border-radius: 8px; padding: 1rem;">
                                    <?php 
                                    // Lấy danh sách users từ database
                                    try {
                                        $pdo = $db->getPdo();
                                        
                                        // Sử dụng email thay vì username nếu không có cột username
                                        $stmt = $pdo->query("
                                            SELECT 
                                                id, 
                                                email, 
                                                email as username,  -- Dùng email làm username nếu không có cột username
                                                full_name, 
                                                role,
                                                status
                                            FROM users 
                                            WHERE status = 'active' 
                                            ORDER BY created_at DESC
                                        ");
                                        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
                                        
                                    } catch (Exception $e) {
                                        error_log("ERROR loading users: " . $e->getMessage());
                                        $users = [];
                                        
                                        // Thử query đơn giản hơn nếu lỗi
                                        try {
                                            $stmt = $pdo->query("SELECT * FROM users LIMIT 10");
                                            $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
                                        } catch (Exception $e2) {
                                            error_log("ERROR: Cannot query users table at all: " . $e2->getMessage());
                                        }
                                    }
                                    ?>
                                    
                                    <div class="mb-3">
                                        <input type="text" class="form-control" id="user-search" placeholder="Tìm kiếm người dùng..." onkeyup="filterUsers()">
                                    </div>
                                    
                                    <div class="form-check mb-2">
                                        <input class="form-check-input" type="checkbox" id="select-all-users" onchange="toggleAllUsers()">
                                        <label class="form-check-label" for="select-all-users">
                                            <strong>Chọn tất cả (<?php echo count($users); ?> người dùng)</strong>
                                        </label>
                                    </div>
                                    
                                    <hr>
                                    
                                    <div id="users-list">
                                        <?php foreach ($users as $user): ?>
                                        <div class="form-check user-item mb-2" data-search="<?php echo strtolower($user['username'] . ' ' . $user['email'] . ' ' . $user['full_name']); ?>">
                                            <input class="form-check-input user-checkbox" type="checkbox" 
                                                   name="selected_users[]" 
                                                   value="<?php echo htmlspecialchars($user['id']); ?>" 
                                                   id="user-<?php echo htmlspecialchars($user['id']); ?>">
                                            <label class="form-check-label" for="user-<?php echo htmlspecialchars($user['id']); ?>">
                                                <div class="d-flex justify-content-between align-items-start">
                                                    <div>
                                                        <strong><?php echo htmlspecialchars($user['email']); ?></strong>
                                                        <?php if ($user['full_name']): ?>
                                                            <br><span class="text-muted"><?php echo htmlspecialchars($user['full_name']); ?></span>
                                                        <?php endif; ?>
                                                    </div>
                                                    <span class="badge bg-<?php echo $user['role'] === 'admin' ? 'danger' : 'info'; ?> ms-2">
                                                        <?php echo ucfirst($user['role']); ?>
                                                    </span>
                                                </div>
                                            </label>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                    
                                    <div class="mt-3 text-muted">
                                        <small>Đã chọn: <span id="selected-count">0</span> người dùng</small>
                                    </div>
                                    
                                    <div class="mt-2" id="selected-preview" style="display: none;">
                                        <strong>Người dùng được chọn:</strong> <span id="selected-user-names"></span>
                                    </div>
                                </div>
                                <input type="hidden" name="target_user_ids" id="target_user_ids">
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Hết hạn (tùy chọn)</label>
                                    <input type="datetime-local" class="form-control" name="expires_at">
                                    <small class="text-muted">Để trống nếu thông báo không hết hạn</small>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Trạng thái</label>
                                    <select class="form-select" name="status">
                                        <option value="active">Hoạt động ngay</option>
                                        <option value="draft">Lưu nháp</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Độ ưu tiên</label>
                                    <input type="number" class="form-control" name="priority" value="0" min="0" max="10">
                                    <small class="text-muted">0-10 (cao hơn = ưu tiên hơn)</small>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Tùy chọn</label>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="is_popup" id="is_popup" checked>
                                        <label class="form-check-label" for="is_popup">
                                            Hiển thị popup khi người dùng đăng nhập
                                        </label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="is_email" id="is_email">
                                        <label class="form-check-label" for="is_email">
                                            Gửi email thông báo
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                        <button type="submit" class="btn btn-gradient" onclick="prepareUserIds()">
                            <i class="fas fa-paper-plane"></i> Tạo Thông Báo
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- View Modal -->
    <div class="modal fade" id="viewModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Chi Tiết Thông Báo</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="view-modal-content">
                    <!-- Content will be loaded via AJAX -->
                </div>
            </div>
        </div>
    </div>
    
    <?php include __DIR__ . '/partials/footer.php'; ?>
    
    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/summernote@0.8.18/dist/summernote-lite.min.js"></script>
    
    <script>
        // Initialize Summernote for rich text editor
        $(document).ready(function() {
            // Có thể enable summernote cho textarea nếu muốn
            // $('#notification-content').summernote({
            //     height: 200,
            //     toolbar: [
            //         ['style', ['bold', 'italic', 'underline']],
            //         ['para', ['ul', 'ol', 'paragraph']],
            //         ['insert', ['link']]
            //     ]
            // });
        });
        
        // Toggle target options
        function toggleTargetOptions() {
            const targetUsers = document.getElementById('target-users').value;
            document.querySelectorAll('.target-options').forEach(el => {
                el.classList.remove('active');
            });
            
            if (targetUsers === 'specific') {
                document.getElementById('target-specific-options').classList.add('active');
            }
        }
        
        // Filter users trong danh sách
        function filterUsers() {
            const searchTerm = document.getElementById('user-search').value.toLowerCase();
            const userItems = document.querySelectorAll('.user-item');
            
            userItems.forEach(item => {
                const searchData = item.getAttribute('data-search');
                if (searchData.includes(searchTerm)) {
                    item.style.display = 'block';
                } else {
                    item.style.display = 'none';
                }
            });
        }
        
        // Toggle all users
        function toggleAllUsers() {
            const selectAll = document.getElementById('select-all-users');
            const checkboxes = document.querySelectorAll('.user-checkbox');
            
            checkboxes.forEach(checkbox => {
                checkbox.checked = selectAll.checked;
            });
            
            updateSelectedCount();
        }
        
        // Update selected count và hiển thị preview
        function updateSelectedCount() {
            const checkboxes = document.querySelectorAll('.user-checkbox:checked');
            const count = checkboxes.length;
            document.getElementById('selected-count').textContent = count;
            
            // Hiển thị preview users được chọn
            if (count > 0) {
                const names = Array.from(checkboxes).slice(0, 3).map(cb => {
                    const label = cb.parentElement.querySelector('strong').textContent;
                    return label;
                });
                
                let preview = names.join(', ');
                if (count > 3) {
                    preview += ` và ${count - 3} người khác`;
                }
                
                // Hiển thị preview nếu có element
                const previewEl = document.getElementById('selected-preview');
                if (previewEl) {
                    previewEl.textContent = preview;
                    previewEl.style.display = 'block';
                }
            } else {
                // Ẩn preview nếu không có người dùng nào được chọn
                const previewEl = document.getElementById('selected-preview');
                if (previewEl) {
                    previewEl.style.display = 'none';
                }
            }
        }
        
        // Prepare user IDs trước khi submit
        function prepareUserIds() {
            const targetUsers = document.getElementById('target-users').value;
            
            if (targetUsers === 'specific') {
                const checkboxes = document.querySelectorAll('.user-checkbox:checked');
                const userIds = Array.from(checkboxes).map(cb => cb.value).join(',');
                document.getElementById('target_user_ids').value = userIds;
            }
        }
        
        // Listen for checkbox changes
        document.addEventListener('DOMContentLoaded', function() {
            const checkboxes = document.querySelectorAll('.user-checkbox');
            checkboxes.forEach(checkbox => {
                checkbox.addEventListener('change', updateSelectedCount);
            });
        });
        
        // View notification detail
        function viewNotification(id) {
            // Load notification detail via AJAX
            fetch(`/api/notifications.php?action=get&id=${id}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.notification) {
                        const n = data.notification;
                        const content = `
                            <div class="notification-detail">
                                <h4>${n.title}</h4>
                                <div class="mb-3">
                                    <span class="notification-type type-${n.type}">${n.type}</span>
                                    <span class="status-badge status-${n.status}">${n.status}</span>
                                </div>
                                <p>${n.content}</p>
                                <hr>
                                <div class="row">
                                    <div class="col-md-6">
                                        <p><strong>Người tạo:</strong> ${n.created_by}</p>
                                        <p><strong>Ngày tạo:</strong> ${n.created_at}</p>
                                        <p><strong>Đối tượng:</strong> ${n.target_users}</p>
                                    </div>
                                    <div class="col-md-6">
                                        <p><strong>Tổng người nhận:</strong> ${n.total_recipients || 0}</p>
                                        <p><strong>Đã đọc:</strong> ${n.total_read || 0}</p>
                                        <p><strong>Hết hạn:</strong> ${n.expires_at || 'Không'}</p>
                                    </div>
                                </div>
                            </div>
                        `;
                        document.getElementById('view-modal-content').innerHTML = content;
                        new bootstrap.Modal(document.getElementById('viewModal')).show();
                    }
                })
                .catch(error => {
                    alert('Không thể tải thông báo');
                });
        }
        
        // Edit notification
        function editNotification(id) {
            // TODO: Implement edit functionality
            alert('Tính năng đang phát triển');
        }
        
        // Delete notification
        function deleteNotification(id) {
            if (confirm('Bạn có chắc muốn xóa thông báo này?')) {
                window.location.href = `?action=delete&id=${id}`;
            }
        }
    </script>
</body>
</html>