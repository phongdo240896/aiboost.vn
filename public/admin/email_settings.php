<?php
session_start();
require_once '../../app/config.php';
require_once '../../app/db.php';
require_once '../../app/auth.php';

// Check admin permission
if (!Auth::isAdmin()) {
    header('Location: /login.php');
    exit;
}

$message = '';
$messageType = 'info';

// Initialize database connection
global $db;

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    
    if ($_POST['action'] === 'save_settings') {
        try {
            // Update each setting in database
            $settings = [
                'smtp_host' => $_POST['smtp_host'] ?? '',
                'smtp_port' => $_POST['smtp_port'] ?? '587',
                'smtp_encryption' => $_POST['smtp_encryption'] ?? 'tls',
                'smtp_username' => $_POST['smtp_username'] ?? '',
                'from_email' => $_POST['from_email'] ?? '',
                'from_name' => $_POST['from_name'] ?? '',
                'debug_mode' => isset($_POST['debug_mode']) ? '1' : '0',
                'daily_limit' => $_POST['daily_limit'] ?? '1000',
                'hourly_limit' => $_POST['hourly_limit'] ?? '100'
            ];
            
            // Only update password if provided
            if (!empty($_POST['smtp_password'])) {
                $settings['smtp_password'] = $_POST['smtp_password'];
            }
            
            // Update settings in database
            foreach ($settings as $key => $value) {
                // Check if setting exists
                $checkResult = $db->query(
                    "SELECT id FROM email_settings WHERE setting_key = ?",
                    [$key]
                );
                
                if (!empty($checkResult)) {
                    // Update existing setting
                    $db->query(
                        "UPDATE email_settings 
                         SET setting_value = ?, updated_at = CURRENT_TIMESTAMP 
                         WHERE setting_key = ?",
                        [$value, $key]
                    );
                } else {
                    // Insert new setting
                    $db->query(
                        "INSERT INTO email_settings (setting_key, setting_value, updated_at) 
                         VALUES (?, ?, CURRENT_TIMESTAMP)",
                        [$key, $value]
                    );
                }
            }
            
            $message = 'Cấu hình đã được lưu thành công!';
            $messageType = 'success';
            
        } catch (Exception $e) {
            $message = 'Lỗi khi lưu cấu hình: ' . $e->getMessage();
            $messageType = 'danger';
        }
    } elseif ($_POST['action'] === 'clear_logs') {
        try {
            $db->query("DELETE FROM email_logs WHERE sent_at < DATE_SUB(NOW(), INTERVAL 30 DAY)");
            $message = 'Đã xóa logs email cũ hơn 30 ngày!';
            $messageType = 'success';
        } catch (Exception $e) {
            $message = 'Lỗi: ' . $e->getMessage();
            $messageType = 'danger';
        }
    }
}

// Load current configuration from database
$config = [];
try {
    $results = $db->query("SELECT setting_key, setting_value FROM email_settings");
    
    if (!empty($results)) {
        foreach ($results as $row) {
            $config[$row['setting_key']] = $row['setting_value'];
        }
    }
} catch (Exception $e) {
    // If table doesn't exist or error, use default values
    $config = [
        'smtp_host' => 'smtp.gmail.com',
        'smtp_port' => '587',
        'smtp_encryption' => 'tls',
        'smtp_username' => '',
        'smtp_password' => '',
        'from_email' => 'noreply@aiboost.vn',
        'from_name' => 'AIboost.vn',
        'debug_mode' => '0',
        'daily_limit' => '1000',
        'hourly_limit' => '100'
    ];
}

// Get email statistics
$stats = ['total' => 0, 'sent' => 0, 'failed' => 0, 'pending' => 0];
$todayStats = ['sent' => 0, 'failed' => 0];

try {
    // Overall stats
    $overallStats = $db->query("
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN status = 'sent' THEN 1 ELSE 0 END) as sent,
            SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed,
            SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending
        FROM email_logs
    ");
    if (!empty($overallStats)) {
        $stats = $overallStats[0];
    }
    
    // Today stats
    $today = date('Y-m-d');
    $todayResult = $db->query(
        "SELECT 
            SUM(CASE WHEN status = 'sent' THEN 1 ELSE 0 END) as sent,
            SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed
        FROM email_logs 
        WHERE DATE(sent_at) = ?",
        [$today]
    );
    if (!empty($todayResult)) {
        $todayStats = $todayResult[0];
    }
    
} catch (Exception $e) {
    // Ignore errors
}

$pageTitle = "Cấu Hình Email - Admin - AIboost.vn";
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?></title>
    
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    
    <style>
        :root {
            --primary-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        
        .card {
            border: none;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            transition: transform 0.3s;
        }
        
        .card:hover {
            transform: translateY(-5px);
        }
        
        .btn-gradient {
            background: var(--primary-gradient);
            color: white;
            border: none;
            transition: all 0.3s;
        }
        
        .btn-gradient:hover {
            background: var(--primary-gradient);
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }
        
        .form-control:focus, .form-select:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }
        
        .alert {
            border-radius: 10px;
            border: none;
            animation: slideIn 0.5s;
        }
        
        @keyframes slideIn {
            from {
                transform: translateY(-20px);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }
        
        .stat-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            text-align: center;
            transition: all 0.3s;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
        }
        
        .stat-icon {
            width: 50px;
            height: 50px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 10px;
            font-size: 24px;
        }
        
        .password-strength {
            height: 5px;
            border-radius: 3px;
            margin-top: 5px;
            transition: all 0.3s;
        }
        
        .test-email-animation {
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0% { opacity: 1; }
            50% { opacity: 0.5; }
            100% { opacity: 1; }
        }
    </style>
</head>
<body class="min-h-screen bg-gray-50">
    <?php include __DIR__ . '/partials/sidebar.php'; ?>
    <?php include __DIR__ . '/partials/header.php'; ?>
    
    <div class="lg:ml-64 pt-16">
        <div class="container py-5">
            <!-- Page Header -->
            <div class="mb-4">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h1 class="h3 mb-1"><i class="fas fa-envelope-open-text text-primary"></i> Cấu Hình Email</h1>
                        <nav aria-label="breadcrumb">
                            <ol class="breadcrumb mb-0">
                                <li class="breadcrumb-item"><a href="/admin">Dashboard</a></li>
                                <li class="breadcrumb-item active">Email Settings</li>
                            </ol>
                        </nav>
                    </div>
                    <div>
                        <span class="badge bg-<?= !empty($config['smtp_username']) ? 'success' : 'warning' ?> px-3 py-2">
                            <i class="fas fa-<?= !empty($config['smtp_username']) ? 'check-circle' : 'exclamation-circle' ?>"></i>
                            <?= !empty($config['smtp_username']) ? 'Đã cấu hình' : 'Chưa cấu hình' ?>
                        </span>
                    </div>
                </div>
            </div>
            
            <?php if ($message): ?>
            <div class="alert alert-<?= $messageType ?> alert-dismissible fade show">
                <i class="fas fa-<?= $messageType === 'success' ? 'check-circle' : 'exclamation-circle' ?>"></i>
                <?= $message ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>
            
            <!-- Statistics Cards -->
            <div class="row mb-4">
                <div class="col-md-3 mb-3">
                    <div class="stat-card">
                        <div class="stat-icon bg-primary bg-opacity-10 text-primary">
                            <i class="fas fa-paper-plane"></i>
                        </div>
                        <h4 class="mb-1"><?= number_format($stats['total'] ?? 0) ?></h4>
                        <small class="text-muted">Tổng email</small>
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="stat-card">
                        <div class="stat-icon bg-success bg-opacity-10 text-success">
                            <i class="fas fa-check"></i>
                        </div>
                        <h4 class="mb-1"><?= number_format($stats['sent'] ?? 0) ?></h4>
                        <small class="text-muted">Thành công</small>
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="stat-card">
                        <div class="stat-icon bg-danger bg-opacity-10 text-danger">
                            <i class="fas fa-times"></i>
                        </div>
                        <h4 class="mb-1"><?= number_format($stats['failed'] ?? 0) ?></h4>
                        <small class="text-muted">Thất bại</small>
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="stat-card">
                        <div class="stat-icon bg-info bg-opacity-10 text-info">
                            <i class="fas fa-calendar-day"></i>
                        </div>
                        <h4 class="mb-1"><?= number_format($todayStats['sent'] ?? 0) ?></h4>
                        <small class="text-muted">Gửi hôm nay</small>
                    </div>
                </div>
            </div>
            
            <div class="row">
                <div class="col-lg-8">
                    <!-- SMTP Configuration -->
                    <div class="card">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center mb-4">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-server text-primary"></i> Cấu hình SMTP
                                </h5>
                                <button class="btn btn-sm btn-outline-primary" onclick="testConnection()">
                                    <i class="fas fa-plug"></i> Test kết nối
                                </button>
                            </div>
                            
                            <form method="POST" id="emailConfigForm">
                                <input type="hidden" name="action" value="save_settings">
                                
                                <!-- SMTP Server Settings -->
                                <div class="row mb-4">
                                    <div class="col-md-6">
                                        <label class="form-label fw-semibold">
                                            <i class="fas fa-server"></i> SMTP Host
                                        </label>
                                        <input type="text" class="form-control" name="smtp_host" 
                                               value="<?= htmlspecialchars($config['smtp_host'] ?? '') ?>" 
                                               placeholder="smtp.gmail.com" required>
                                        <small class="text-muted">Gmail | Outlook | Yahoo | Custom</small>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label fw-semibold">
                                            <i class="fas fa-door-open"></i> Port
                                        </label>
                                        <input type="number" class="form-control" name="smtp_port" 
                                               value="<?= $config['smtp_port'] ?? '587' ?>" required>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label fw-semibold">
                                            <i class="fas fa-lock"></i> Mã hóa
                                        </label>
                                        <select class="form-select" name="smtp_encryption">
                                            <option value="tls" <?= ($config['smtp_encryption'] ?? '') === 'tls' ? 'selected' : '' ?>>TLS</option>
                                            <option value="ssl" <?= ($config['smtp_encryption'] ?? '') === 'ssl' ? 'selected' : '' ?>>SSL</option>
                                            <option value="" <?= empty($config['smtp_encryption']) ? 'selected' : '' ?>>None</option>
                                        </select>
                                    </div>
                                </div>
                                
                                <!-- Authentication -->
                                <div class="row mb-4">
                                    <div class="col-md-6">
                                        <label class="form-label fw-semibold">
                                            <i class="fas fa-user"></i> Email đăng nhập
                                        </label>
                                        <input type="email" class="form-control" name="smtp_username" 
                                               value="<?= htmlspecialchars($config['smtp_username'] ?? '') ?>" 
                                               placeholder="your-email@gmail.com" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label fw-semibold">
                                            <i class="fas fa-key"></i> Mật khẩu
                                        </label>
                                        <div class="input-group">
                                            <input type="password" class="form-control" name="smtp_password" 
                                                   id="smtp_password"
                                                   placeholder="<?= !empty($config['smtp_password']) ? '••••••••' : 'App Password' ?>">
                                            <button class="btn btn-outline-secondary" type="button" 
                                                    onclick="togglePassword()">
                                                <i class="fas fa-eye" id="password-icon"></i>
                                            </button>
                                        </div>
                                        <div class="password-strength" id="passwordStrength"></div>
                                        <small class="text-muted">
                                            <a href="https://myaccount.google.com/apppasswords" target="_blank" class="text-decoration-none">
                                                <i class="fas fa-external-link-alt"></i> Tạo App Password
                                            </a>
                                        </small>
                                    </div>
                                </div>
                                
                                <!-- Sender Info -->
                                <div class="row mb-4">
                                    <div class="col-md-6">
                                        <label class="form-label fw-semibold">
                                            <i class="fas fa-at"></i> Email gửi
                                        </label>
                                        <input type="email" class="form-control" name="from_email" 
                                               value="<?= htmlspecialchars($config['from_email'] ?? '') ?>" 
                                               placeholder="noreply@aiboost.vn" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label fw-semibold">
                                            <i class="fas fa-signature"></i> Tên hiển thị
                                        </label>
                                        <input type="text" class="form-control" name="from_name" 
                                               value="<?= htmlspecialchars($config['from_name'] ?? '') ?>" 
                                               placeholder="AIboost.vn" required>
                                    </div>
                                </div>
                                
                                <!-- Limits -->
                                <div class="row mb-4">
                                    <div class="col-md-6">
                                        <label class="form-label fw-semibold">
                                            <i class="fas fa-tachometer-alt"></i> Giới hạn email/ngày
                                        </label>
                                        <input type="number" class="form-control" name="daily_limit" 
                                               value="<?= $config['daily_limit'] ?? '1000' ?>">
                                        <small class="text-muted">Gmail: 500/ngày, G Suite: 2000/ngày</small>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label fw-semibold">
                                            <i class="fas fa-clock"></i> Giới hạn email/giờ
                                        </label>
                                        <input type="number" class="form-control" name="hourly_limit" 
                                               value="<?= $config['hourly_limit'] ?? '100' ?>">
                                        <small class="text-muted">Tránh bị đánh dấu spam</small>
                                    </div>
                                </div>
                                
                                <!-- Options -->
                                <div class="mb-4">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" name="debug_mode" 
                                               id="debugMode" <?= ($config['debug_mode'] ?? '0') === '1' ? 'checked' : '' ?>>
                                        <label class="form-check-label" for="debugMode">
                                            <i class="fas fa-bug"></i> Chế độ Debug
                                            <small class="text-muted d-block">Email sẽ được lưu vào log thay vì gửi thật</small>
                                        </label>
                                    </div>
                                </div>
                                
                                <div class="d-flex gap-2">
                                    <button type="submit" class="btn btn-gradient">
                                        <i class="fas fa-save"></i> Lưu cấu hình
                                    </button>
                                    <button type="button" class="btn btn-outline-secondary" onclick="resetForm()">
                                        <i class="fas fa-undo"></i> Đặt lại
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-4">
                    <!-- Quick Test -->
                    <div class="card mb-4">
                        <div class="card-body">
                            <h5 class="card-title mb-4">
                                <i class="fas fa-flask text-success"></i> Test nhanh
                            </h5>
                            
                            <div class="mb-3">
                                <label class="form-label">Email nhận</label>
                                <input type="email" class="form-control" id="testEmail" 
                                       placeholder="example@email.com"
                                       value="<?= $_SESSION['user']['email'] ?? '' ?>">
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Nội dung</label>
                                <textarea class="form-control" id="testContent" rows="3">Xin chào! Đây là email test từ AIboost.vn 🚀</textarea>
                            </div>
                            
                            <button class="btn btn-success w-100" onclick="sendTestEmail()" id="testBtn">
                                <i class="fas fa-paper-plane"></i> Gửi test
                            </button>
                            
                            <div id="testResult" class="mt-3"></div>
                        </div>
                    </div>
                    
                    <!-- Recent Emails -->
                    <div class="card mb-4">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-history text-info"></i> Email gần đây
                                </h5>
                                <a href="/admin/email_logs.php" class="btn btn-sm btn-link">
                                    Xem tất cả
                                </a>
                            </div>
                            
                            <?php
                            try {
                                $recentEmails = $db->query(
                                    "SELECT email, subject, status, sent_at, error_message 
                                     FROM email_logs 
                                     ORDER BY sent_at DESC 
                                     LIMIT 5"
                                );
                            } catch (Exception $e) {
                                $recentEmails = [];
                            }
                            ?>
                            
                            <?php if (!empty($recentEmails)): ?>
                            <div class="list-group list-group-flush">
                                <?php foreach ($recentEmails as $email): ?>
                                <div class="list-group-item px-0">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div class="flex-grow-1" style="min-width: 0;">
                                            <div class="text-truncate fw-semibold">
                                                <?= htmlspecialchars($email['subject'] ?? 'No subject') ?>
                                            </div>
                                            <small class="text-muted d-block text-truncate">
                                                <i class="fas fa-user"></i> <?= htmlspecialchars($email['email']) ?>
                                            </small>
                                            <small class="text-muted">
                                                <i class="fas fa-clock"></i> <?= date('H:i d/m', strtotime($email['sent_at'])) ?>
                                            </small>
                                        </div>
                                        <span class="badge bg-<?= $email['status'] === 'sent' ? 'success' : ($email['status'] === 'failed' ? 'danger' : 'warning') ?>">
                                            <?= $email['status'] ?>
                                        </span>
                                    </div>
                                    <?php if ($email['status'] === 'failed' && !empty($email['error_message'])): ?>
                                    <small class="text-danger d-block mt-1">
                                        <i class="fas fa-exclamation-triangle"></i> 
                                        <?= htmlspecialchars(substr($email['error_message'], 0, 50)) ?>...
                                    </small>
                                    <?php endif; ?>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <?php else: ?>
                            <p class="text-muted text-center mb-0">
                                <i class="fas fa-inbox"></i> Chưa có email
                            </p>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Quick Actions -->
                    <div class="card">
                        <div class="card-body">
                            <h5 class="card-title mb-3">
                                <i class="fas fa-bolt text-warning"></i> Thao tác nhanh
                            </h5>
                            
                            <div class="d-grid gap-2">
                                <button class="btn btn-outline-primary" onclick="exportLogs()">
                                    <i class="fas fa-download"></i> Xuất báo cáo
                                </button>
                                <form method="POST" class="d-grid" onsubmit="return confirm('Xác nhận xóa logs cũ?')">
                                    <input type="hidden" name="action" value="clear_logs">
                                    <button type="submit" class="btn btn-outline-danger">
                                        <i class="fas fa-trash"></i> Xóa logs cũ
                                    </button>
                                </form>
                                <a href="/admin/email_templates.php" class="btn btn-outline-info">
                                    <i class="fas fa-palette"></i> Quản lý mẫu email
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <?php include __DIR__ . '/partials/footer.php'; ?>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    // Toggle password visibility
    function togglePassword() {
        const passwordField = document.getElementById('smtp_password');
        const icon = document.getElementById('password-icon');
        
        if (passwordField.type === 'password') {
            passwordField.type = 'text';
            icon.className = 'fas fa-eye-slash';
        } else {
            passwordField.type = 'password';
            icon.className = 'fas fa-eye';
        }
    }
    
    // Send test email
    function sendTestEmail() {
        const email = document.getElementById('testEmail').value;
        const content = document.getElementById('testContent').value;
        const resultDiv = document.getElementById('testResult');
        const testBtn = document.getElementById('testBtn');
        
        if (!email) {
            resultDiv.innerHTML = '<div class="alert alert-warning"><i class="fas fa-exclamation-triangle"></i> Vui lòng nhập email</div>';
            return;
        }
        
        // Validate email format
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if (!emailRegex.test(email)) {
            resultDiv.innerHTML = '<div class="alert alert-warning"><i class="fas fa-exclamation-triangle"></i> Email không hợp lệ</div>';
            return;
        }
        
        // Disable button and show loading
        testBtn.disabled = true;
        testBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Đang gửi...';
        resultDiv.innerHTML = '<div class="alert alert-info"><i class="fas fa-spinner fa-spin"></i> Đang xử lý...</div>';
        
        // Use AJAX to call PHP directly
        const xhr = new XMLHttpRequest();
        xhr.open('POST', 'ajax_send_test_email.php', true);
        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
        
        xhr.onreadystatechange = function() {
            if (xhr.readyState === 4) {
                try {
                    const response = JSON.parse(xhr.responseText);
                    
                    if (response.success) {
                        resultDiv.innerHTML = `
                            <div class="alert alert-success">
                                <i class="fas fa-check-circle"></i> ${response.message || 'Email đã gửi thành công!'}
                                <small class="d-block mt-1">Kiểm tra hộp thư của bạn (cả thư mục Spam)</small>
                            </div>`;
                        
                        // Reload page after 3 seconds
                        setTimeout(() => location.reload(), 3000);
                    } else {
                        resultDiv.innerHTML = `
                            <div class="alert alert-danger">
                                <i class="fas fa-exclamation-circle"></i> ${response.message || 'Có lỗi xảy ra'}
                            </div>`;
                    }
                } catch (e) {
                    console.error('Parse error:', e);
                    resultDiv.innerHTML = `
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-circle"></i> Lỗi: ${xhr.responseText}
                        </div>`;
                }
                
                // Re-enable button
                testBtn.disabled = false;
                testBtn.innerHTML = '<i class="fas fa-paper-plane"></i> Gửi test';
            }
        };
        
        xhr.send('email=' + encodeURIComponent(email) + '&content=' + encodeURIComponent(content));
    }
    
    // Test SMTP connection
    function testConnection() {
        alert('Đang kiểm tra kết nối SMTP...');
        // TODO: Implement SMTP connection test
    }
    
    // Reset form
    function resetForm() {
        if (confirm('Đặt lại form về mặc định?')) {
            document.getElementById('emailConfigForm').reset();
        }
    }
    
    // Export logs
    function exportLogs() {
        window.location.href = '/admin/export_email_logs.php';
    }
    
    // Password strength checker
    document.getElementById('smtp_password').addEventListener('input', function() {
        const password = this.value;
        const strengthBar = document.getElementById('passwordStrength');
        
        if (password.length === 0) {
            strengthBar.style.width = '0%';
            return;
        }
        
        let strength = 0;
        if (password.length >= 8) strength += 25;
        if (password.length >= 12) strength += 25;
        if (/[a-z]/.test(password) && /[A-Z]/.test(password)) strength += 25;
        if (/[0-9]/.test(password)) strength += 25;
        
        strengthBar.style.width = strength + '%';
        
        if (strength <= 25) {
            strengthBar.className = 'password-strength bg-danger';
        } else if (strength <= 50) {
            strengthBar.className = 'password-strength bg-warning';
        } else if (strength <= 75) {
            strengthBar.className = 'password-strength bg-info';
        } else {
            strengthBar.className = 'password-strength bg-success';
        }
    });
    </script>
</body>
</html>