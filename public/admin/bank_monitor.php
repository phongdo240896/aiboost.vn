<?php
require_once __DIR__ . '/../../app/config.php';
require_once __DIR__ . '/../../app/db.php';
require_once __DIR__ . '/../../app/auth.php';
require_once __DIR__ . '/../../app/middleware.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

Middleware::requireAdmin();

// Get statistics
$stats = [
    'total' => $db->query("SELECT COUNT(*) as count FROM bank_logs")[0]['count'],
    'processed' => $db->query("SELECT COUNT(*) as count FROM bank_logs WHERE status = 'processed'")[0]['count'],
    'pending' => $db->query("SELECT COUNT(*) as count FROM bank_logs WHERE status = 'pending'")[0]['count'],
    'manual' => $db->query("SELECT COUNT(*) as count FROM bank_logs WHERE status = 'manual_review'")[0]['count'],
    'today_amount' => $db->query("SELECT SUM(amount) as total FROM bank_logs WHERE DATE(created_at) = CURDATE() AND status = 'processed'")[0]['total'] ?? 0
];

// Get recent transactions with pagination
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$perPage = 50;
$offset = ($page - 1) * $perPage;

$recentTx = $db->query(
    "SELECT bl.*, u.email as user_email 
     FROM bank_logs bl
     LEFT JOIN users u ON bl.user_id = u.id
     ORDER BY bl.created_at DESC 
     LIMIT {$perPage} OFFSET {$offset}"
);

$pageTitle = "Quản Lý Sao Kê - Admin";
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
        body { font-family: 'Inter', sans-serif; }
        .fade-in { animation: fadeIn 0.5s ease-in; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
        
        /* Custom scrollbar */
        .custom-scrollbar::-webkit-scrollbar {
            width: 6px;
            height: 6px;
        }
        .custom-scrollbar::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 10px;
        }
        .custom-scrollbar::-webkit-scrollbar-thumb {
            background: #888;
            border-radius: 10px;
        }
        .custom-scrollbar::-webkit-scrollbar-thumb:hover {
            background: #555;
        }
        
        /* Fixed header table */
        .table-container {
            max-height: calc(100vh - 400px);
            overflow-y: auto;
            overflow-x: auto;
        }
        
        thead th {
            position: sticky;
            top: 0;
            background: #f9fafb;
            z-index: 10;
        }
        
        /* Main content positioning */
        .main-content {
            margin-left: 256px; /* Width of sidebar */
            padding-top: 5px; /* Height of header */
            min-height: 100vh;
            background: #f9fafb;
        }
        
        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
            }
        }
    </style>
</head>
<body class="bg-gray-50">
    <!-- Include Admin Sidebar -->
    <?php include __DIR__ . '/partials/sidebar.php'; ?>
    
    <!-- Include Admin Header -->
    <?php include __DIR__ . '/partials/header.php'; ?>
    
    <!-- Main Content Area -->
    <div class="main-content">
        <div class="p-6">
            <!-- Page Title -->
            <div class="mb-6">
                <h1 class="text-2xl font-bold text-gray-900">📝 Bank Transaction Monitor</h1>
                <p class="text-sm text-gray-600 mt-1">Quản lý và theo dõi giao dịch ngân hàng</p>
            </div>
            
            <!-- Statistics Cards -->
            <div class="grid grid-cols-2 md:grid-cols-5 gap-4 mb-6">
                <div class="bg-white rounded-lg shadow-sm p-4 border border-gray-200">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-xs text-gray-500">Tổng giao dịch</p>
                            <p class="text-2xl font-bold text-gray-900"><?= number_format($stats['total']) ?></p>
                        </div>
                        <div class="text-gray-400">
                            <i class="fas fa-chart-line text-2xl"></i>
                        </div>
                    </div>
                </div>
                
                <div class="bg-green-50 rounded-lg shadow-sm p-4 border border-green-200">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-xs text-green-600">Đã xử lý</p>
                            <p class="text-2xl font-bold text-green-700"><?= number_format($stats['processed']) ?></p>
                        </div>
                        <div class="text-green-500">
                            <i class="fas fa-check-circle text-2xl"></i>
                        </div>
                    </div>
                </div>
                
                <div class="bg-yellow-50 rounded-lg shadow-sm p-4 border border-yellow-200">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-xs text-yellow-600">Đang chờ</p>
                            <p class="text-2xl font-bold text-yellow-700"><?= number_format($stats['pending']) ?></p>
                        </div>
                        <div class="text-yellow-500">
                            <i class="fas fa-clock text-2xl"></i>
                        </div>
                    </div>
                </div>
                
                <div class="bg-orange-50 rounded-lg shadow-sm p-4 border border-orange-200">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-xs text-orange-600">Cần xem xét</p>
                            <p class="text-2xl font-bold text-orange-700"><?= number_format($stats['manual']) ?></p>
                        </div>
                        <div class="text-orange-500">
                            <i class="fas fa-exclamation-triangle text-2xl"></i>
                        </div>
                    </div>
                </div>
                
                <div class="bg-blue-50 rounded-lg shadow-sm p-4 border border-blue-200">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-xs text-blue-600">Hôm nay</p>
                            <p class="text-xl font-bold text-blue-700"><?= number_format($stats['today_amount']) ?> đ</p>
                        </div>
                        <div class="text-blue-500">
                            <i class="fas fa-coins text-2xl"></i>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Action Buttons -->
            <div class="bg-white rounded-lg shadow-sm p-4 mb-6 border border-gray-200">
                <div class="flex flex-wrap gap-3">
                    <button onclick="runCron()" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
                        <i class="fas fa-sync-alt mr-2"></i>Chạy Cron
                    </button>
                    
                    <button onclick="processManual()" class="px-4 py-2 bg-orange-600 text-white rounded-lg hover:bg-orange-700 transition-colors">
                        <i class="fas fa-tasks mr-2"></i>Xử lý thủ công
                    </button>
                    
                    <a href="/admin/bank_accounts" class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors inline-block">
                        <i class="fas fa-university mr-2"></i>Quản lý Bank
                    </a>
                    
                    <a href="/test_api_token.php" class="px-4 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700 transition-colors inline-block">
                        <i class="fas fa-key mr-2"></i>Test API
                    </a>
                </div>
                <div id="actionResult" class="mt-4"></div>
            </div>
            
            <!-- Transactions Table -->
            <div class="bg-white rounded-lg shadow-sm border border-gray-200">
                <div class="px-6 py-4 border-b border-gray-200 flex justify-between items-center">
                    <h2 class="text-lg font-semibold text-gray-900">Giao dịch gần đây</h2>
                    <button onclick="location.reload()" class="text-sm text-blue-600 hover:text-blue-700">
                        <i class="fas fa-refresh mr-1"></i> Làm mới
                    </button>
                </div>
                
                <div class="table-container custom-scrollbar">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Thời gian</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Mã GD</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Ngân hàng</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Số tiền</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider min-w-[200px]">Nội dung</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Người dùng</th>
                                <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Trạng thái</th>
                                <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Thao tác</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($recentTx as $tx): ?>
                            <tr class="hover:bg-gray-50 transition-colors">
                                <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900">
                                    <?= date('H:i:s', strtotime($tx['created_at'])) ?><br>
                                    <span class="text-xs text-gray-500"><?= date('d/m/Y', strtotime($tx['created_at'])) ?></span>
                                </td>
                                <td class="px-4 py-3 text-sm">
                                    <div class="font-mono text-xs text-gray-600" title="<?= htmlspecialchars($tx['transaction_id']) ?>">
                                        <?= substr($tx['transaction_id'], 0, 12) ?>...
                                    </div>
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap">
                                    <span class="px-2 py-1 text-xs font-medium bg-gray-100 text-gray-800 rounded">
                                        <?= $tx['bank_code'] ?>
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-right whitespace-nowrap">
                                    <span class="font-semibold text-sm">
                                        <?= number_format($tx['amount']) ?> 
                                        <span class="text-xs text-gray-500">VNĐ</span>
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-sm">
                                    <div class="max-w-xs truncate" title="<?= htmlspecialchars($tx['description']) ?>">
                                        <?= htmlspecialchars($tx['description']) ?>
                                    </div>
                                </td>
                                <td class="px-4 py-3 text-sm">
                                    <?php if ($tx['user_email']): ?>
                                        <div class="flex items-center">
                                            <span class="text-green-600 mr-1">✓</span>
                                            <span class="text-gray-900"><?= $tx['user_email'] ?></span>
                                        </div>
                                    <?php else: ?>
                                        <span class="text-gray-400">Chưa có</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <?php
                                    $statusConfig = [
                                        'processed' => ['bg' => 'bg-green-100', 'text' => 'text-green-800', 'label' => 'Đã xử lý'],
                                        'pending' => ['bg' => 'bg-yellow-100', 'text' => 'text-yellow-800', 'label' => 'Đang chờ'],
                                        'manual_review' => ['bg' => 'bg-orange-100', 'text' => 'text-orange-800', 'label' => 'Cần xem'],
                                        'failed' => ['bg' => 'bg-red-100', 'text' => 'text-red-800', 'label' => 'Lỗi']
                                    ];
                                    $status = $statusConfig[$tx['status']] ?? ['bg' => 'bg-gray-100', 'text' => 'text-gray-800', 'label' => $tx['status']];
                                    ?>
                                    <span class="px-2 py-1 text-xs font-medium rounded-full <?= $status['bg'] ?> <?= $status['text'] ?>">
                                        <?= $status['label'] ?>
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-center text-sm">
                                    <?php if ($tx['status'] === 'manual_review'): ?>
                                    <button onclick="assignUser('<?= $tx['id'] ?>')" 
                                            class="text-blue-600 hover:text-blue-800 font-medium">
                                        <i class="fas fa-user-plus mr-1"></i>Gán
                                    </button>
                                    <?php elseif ($tx['status'] === 'pending'): ?>
                                    <button onclick="processSingle('<?= $tx['id'] ?>')"
                                            class="text-green-600 hover:text-green-800 font-medium">
                                        <i class="fas fa-play mr-1"></i>Xử lý
                                    </button>
                                    <?php else: ?>
                                    <span class="text-gray-400">-</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Pagination -->
                <div class="px-6 py-4 border-t border-gray-200 flex justify-between items-center">
                    <div class="text-sm text-gray-700">
                        Trang <?= $page ?> - Hiển thị <?= count($recentTx) ?> / <?= $stats['total'] ?> giao dịch
                    </div>
                    <div class="flex gap-2">
                        <?php if ($page > 1): ?>
                        <a href="?page=<?= $page - 1 ?>" class="px-3 py-1 bg-gray-100 text-gray-700 rounded hover:bg-gray-200">
                            <i class="fas fa-chevron-left"></i>
                        </a>
                        <?php endif; ?>
                        
                        <?php if (count($recentTx) === $perPage): ?>
                        <a href="?page=<?= $page + 1 ?>" class="px-3 py-1 bg-gray-100 text-gray-700 rounded hover:bg-gray-200">
                            <i class="fas fa-chevron-right"></i>
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Include Admin Footer -->
    <?php include __DIR__ . '/partials/footer.php'; ?>

    <script>
    function runCron() {
        const resultDiv = document.getElementById('actionResult');
        resultDiv.innerHTML = '<div class="bg-blue-50 border border-blue-200 rounded-lg p-3 text-blue-700"><i class="fas fa-spinner fa-spin mr-2"></i>Đang chạy cron job...</div>';
        
        fetch('/cron/check_bank_transactions.php?test=1')
            .then(response => response.text())
            .then(result => {
                resultDiv.innerHTML = `<div class="bg-green-50 border border-green-200 rounded-lg p-4">
                    <div class="text-green-800 font-semibold mb-2"><i class="fas fa-check-circle mr-2"></i>Cron đã chạy xong</div>
                    <pre class="mt-2 text-xs bg-white p-2 rounded overflow-x-auto max-h-40">${result.substring(0, 500)}...</pre>
                </div>`;
                setTimeout(() => location.reload(), 3000);
            })
            .catch(error => {
                resultDiv.innerHTML = `<div class="bg-red-50 border border-red-200 rounded-lg p-3 text-red-700">
                    <i class="fas fa-exclamation-circle mr-2"></i>Lỗi: ${error.message}
                </div>`;
            });
    }
    
    function assignUser(logId) {
        const email = prompt('Nhập email người dùng:');
        if (!email) return;
        
        // TODO: Implementation for assigning user
        console.log('Assign user to log:', logId, email);
    }
    
    function processSingle(logId) {
        // TODO: Implementation for processing single transaction
        console.log('Process transaction:', logId);
    }
    
    function processManual() {
        // TODO: Implementation for processing all manual reviews
        console.log('Process all manual reviews');
    }
    
    document.addEventListener('DOMContentLoaded', function() {
        updatePageTitle('Giao dịch');
    });
    </script>
</body>
</html>