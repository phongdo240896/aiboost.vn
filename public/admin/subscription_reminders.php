<?php
session_start();
require_once '../../app/config.php';
require_once '../../app/db.php';
require_once '../../app/auth.php';

// Initialize Auth
Auth::init($db);

if (!Auth::isAdmin()) {
    header('Location: /login.php');
    exit;
}

// Get subscription statistics - FIX QUERY
$statsQuery = $db->query("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active,
        SUM(CASE WHEN status = 'expired' THEN 1 ELSE 0 END) as expired,
        SUM(CASE WHEN DATE(end_date) = DATE_ADD(CURDATE(), INTERVAL 5 DAY) THEN 1 ELSE 0 END) as expiring_5_days,
        SUM(CASE WHEN DATE(end_date) = CURDATE() THEN 1 ELSE 0 END) as expiring_today
    FROM subscriptions
");

// Check if query returns results
$stats = !empty($statsQuery) ? $statsQuery[0] : [
    'total' => 0,
    'active' => 0, 
    'expired' => 0,
    'expiring_5_days' => 0,
    'expiring_today' => 0
];

// Get recent reminder emails
$recentEmails = $db->query("
    SELECT * FROM email_logs 
    WHERE subject LIKE '%hết hạn%' OR subject LIKE '%gia hạn%'
    ORDER BY sent_at DESC 
    LIMIT 20
");

// Get upcoming expirations - FIX JOIN
$upcomingExpirations = $db->query("
    SELECT 
        s.*,
        u.email,
        u.full_name,
        s.plan_name
    FROM subscriptions s
    LEFT JOIN users u ON s.user_id = u.id
    WHERE s.status = 'active'
    AND s.end_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)
    ORDER BY s.end_date ASC
");

// Make sure arrays are initialized
$recentEmails = $recentEmails ?: [];
$upcomingExpirations = $upcomingExpirations ?: [];

$pageTitle = "Quản Lý Nhắc Nhở Gia Hạn - Admin";
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?></title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        .statistics-card {
            border-left: 4px solid;
            transition: transform 0.2s;
        }
        .statistics-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        .statistics-card.total { border-left-color: #3b82f6; }
        .statistics-card.active { border-left-color: #10b981; }
        .statistics-card.warning { border-left-color: #f59e0b; }
        .statistics-card.danger { border-left-color: #ef4444; }
    </style>
</head>
<body class="min-h-screen bg-gray-50">
    <?php include __DIR__ . '/partials/sidebar.php'; ?>
    <?php include __DIR__ . '/partials/header.php'; ?>
    
    <div class="lg:ml-64 pt-16">
        <div class="container py-4">
            <h1 class="h3 mb-4">
                <i class="fas fa-clock text-warning"></i> Quản Lý Nhắc Nhở Gia Hạn
            </h1>
            
            <!-- Statistics -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="card statistics-card total">
                        <div class="card-body text-center">
                            <h4 class="mb-1"><?= intval($stats['total']) ?></h4>
                            <small class="text-muted">Tổng subscriptions</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card statistics-card active">
                        <div class="card-body text-center">
                            <h4 class="text-success mb-1"><?= intval($stats['active']) ?></h4>
                            <small class="text-muted">Đang hoạt động</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card statistics-card warning">
                        <div class="card-body text-center">
                            <h4 class="text-warning mb-1"><?= intval($stats['expiring_5_days']) ?></h4>
                            <small class="text-muted">Sắp hết hạn (5 ngày)</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card statistics-card danger">
                        <div class="card-body text-center">
                            <h4 class="text-danger mb-1"><?= intval($stats['expired']) ?></h4>
                            <small class="text-muted">Đã hết hạn</small>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Manual trigger -->
            <div class="card mb-4">
                <div class="card-body">
                    <h5 class="card-title">Chạy thủ công</h5>
                    <button class="btn btn-primary" id="runManualBtn" onclick="runManualCheck()">
                        <i class="fas fa-play"></i> Kiểm tra và gửi email nhắc nhở
                    </button>
                    <div id="checkResult" class="mt-3"></div>
                </div>
            </div>
            
            <!-- Upcoming expirations -->
            <div class="card mb-4">
                <div class="card-header bg-white">
                    <h5 class="mb-0">Sắp hết hạn trong 7 ngày</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($upcomingExpirations)): ?>
                        <p class="text-muted text-center py-3">Không có subscription nào sắp hết hạn trong 7 ngày tới.</p>
                    <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>User</th>
                                    <th>Email</th>
                                    <th>Gói</th>
                                    <th>Hết hạn</th>
                                    <th>Còn lại</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($upcomingExpirations as $sub): ?>
                                <?php 
                                    $daysLeft = ceil((strtotime($sub['end_date']) - time()) / 86400);
                                    $badgeClass = $daysLeft <= 1 ? 'danger' : ($daysLeft <= 5 ? 'warning' : 'info');
                                ?>
                                <tr>
                                    <td><?= htmlspecialchars($sub['full_name'] ?: 'User #' . $sub['user_id']) ?></td>
                                    <td><?= htmlspecialchars($sub['email'] ?: 'N/A') ?></td>
                                    <td><?= htmlspecialchars($sub['plan_name'] ?: 'N/A') ?></td>
                                    <td><?= date('d/m/Y', strtotime($sub['end_date'])) ?></td>
                                    <td>
                                        <span class="badge bg-<?= $badgeClass ?>">
                                            <?= $daysLeft ?> ngày
                                        </span>
                                    </td>
                                    <td>
                                        <button class="btn btn-sm btn-outline-primary" 
                                                onclick="sendSingleReminder(<?= $sub['id'] ?>)"
                                                id="sendBtn-<?= $sub['id'] ?>">
                                            <i class="fas fa-envelope"></i> Gửi nhắc nhở
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Recent emails -->
            <div class="card">
                <div class="card-header bg-white">
                    <h5 class="mb-0">Email nhắc nhở gần đây</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($recentEmails)): ?>
                        <p class="text-muted text-center py-3">Chưa có email nhắc nhở nào được gửi.</p>
                    <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Thời gian</th>
                                    <th>Email</th>
                                    <th>Tiêu đề</th>
                                    <th>Trạng thái</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recentEmails as $email): ?>
                                <tr>
                                    <td><?= date('d/m H:i', strtotime($email['sent_at'])) ?></td>
                                    <td><?= htmlspecialchars($email['email'] ?: 'N/A') ?></td>
                                    <td><?= htmlspecialchars($email['subject'] ?: 'N/A') ?></td>
                                    <td>
                                        <span class="badge bg-<?= $email['status'] == 'sent' ? 'success' : 'danger' ?>">
                                            <?= $email['status'] ?>
                                        </span>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
function runManualCheck() {
    if (!confirm('Chạy kiểm tra và gửi email nhắc nhở cho tất cả subscription sắp hết hạn?')) {
        return;
    }
    
    const btn = document.getElementById('runManualBtn');
    const resultDiv = document.getElementById('checkResult');
    
    // Disable button and show loading
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Đang xử lý...';
    resultDiv.innerHTML = '';
    
    fetch('/admin/ajax/run_subscription_check.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        }
    })
    .then(response => {
        // Check if response is ok
        if (!response.ok) {
            throw new Error('Network response was not ok: ' + response.status);
        }
        return response.text(); // Get text first
    })
    .then(text => {
        // Try to parse JSON
        try {
            return JSON.parse(text);
        } catch (e) {
            console.error('Response text:', text);
            throw new Error('Invalid JSON response');
        }
    })
    .then(data => {
        // Re-enable button
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-play"></i> Kiểm tra và gửi email nhắc nhở';
        
        // Show result
        if (data.success) {
            let statsHtml = '';
            if (data.stats) {
                statsHtml = `
                    <div class="mt-2">
                        <span class="badge bg-success">Gửi thành công: ${data.stats.sent}</span>
                        <span class="badge bg-danger">Thất bại: ${data.stats.failed}</span>
                    </div>
                `;
            }
            
            resultDiv.innerHTML = `
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i> ${data.message}
                    ${statsHtml}
                    ${data.output ? '<pre class="mt-2 small bg-light p-2 rounded">' + data.output + '</pre>' : ''}
                </div>
            `;
            
            // Reload page after 3 seconds
            if (data.stats && data.stats.sent > 0) {
                setTimeout(() => location.reload(), 3000);
            }
        } else {
            resultDiv.innerHTML = `
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i> ${data.message}
                </div>
            `;
        }
    })
    .catch(error => {
        console.error('Error:', error);
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-play"></i> Kiểm tra và gửi email nhắc nhở';
        resultDiv.innerHTML = `
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-triangle"></i> Lỗi: ${error.message}
                <br><small>Vui lòng kiểm tra Console để xem chi tiết lỗi</small>
            </div>
        `;
    });
}

function sendSingleReminder(subId) {
    if (!confirm('Gửi email nhắc nhở cho subscription này?')) {
        return;
    }
    
    const btn = document.getElementById('sendBtn-' + subId);
    const originalHtml = btn.innerHTML;
    
    // Disable button and show loading
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Đang gửi...';
    
    fetch('/admin/ajax/send_single_reminder.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({subscription_id: subId})
    })
    .then(response => {
        if (!response.ok) {
            throw new Error('Network response was not ok');
        }
        return response.text();
    })
    .then(text => {
        try {
            return JSON.parse(text);
        } catch (e) {
            console.error('Response text:', text);
            throw new Error('Invalid JSON response');
        }
    })
    .then(data => {
        // Re-enable button
        btn.disabled = false;
        btn.innerHTML = originalHtml;
        
        if (data.success) {
            // Show success with toast or alert
            const toast = `
                <div class="position-fixed bottom-0 end-0 p-3" style="z-index: 11">
                    <div class="toast show" role="alert">
                        <div class="toast-header bg-success text-white">
                            <i class="fas fa-check-circle me-2"></i>
                            <strong class="me-auto">Thành công</strong>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="toast"></button>
                        </div>
                        <div class="toast-body">
                            ${data.message}
                        </div>
                    </div>
                </div>
            `;
            document.body.insertAdjacentHTML('beforeend', toast);
            
            // Remove toast after 3 seconds and reload
            setTimeout(() => {
                document.querySelector('.toast').remove();
                location.reload();
            }, 3000);
        } else {
            alert('❌ ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        btn.disabled = false;
        btn.innerHTML = originalHtml;
        alert('❌ Lỗi: ' + error.message);
    });
}
    </script>
</body>
</html>