<?php
require_once __DIR__ . '/../../app/config.php';
require_once __DIR__ . '/../../app/db.php';
require_once __DIR__ . '/../../app/auth.php';
require_once __DIR__ . '/../../app/middleware.php';
require_once __DIR__ . '/../../app/WalletManager.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

Middleware::requireAdmin();
Middleware::logActivity('admin_wallet_management');

$userData = Auth::getUser();
$walletManager = new WalletManager($db);

// Handle AJAX requests
if (isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    switch ($_POST['action']) {
        case 'get_transactions':
            $page = intval($_POST['page'] ?? 1);
            $perPage = intval($_POST['per_page'] ?? 10);
            $offset = ($page - 1) * $perPage;
            
            // Get total count
            $totalQuery = "
                SELECT COUNT(*) as total
                FROM wallet_transactions wt
                JOIN users u ON wt.user_id = u.id
            ";
            $totalResult = $db->query($totalQuery);
            $total = $totalResult[0]['total'] ?? 0;
            $totalPages = ceil($total / $perPage);
            
            // Get transactions for current page
            $transactions = $db->query("
                SELECT wt.*, u.email as user_email, u.full_name
                FROM wallet_transactions wt
                JOIN users u ON wt.user_id = u.id
                ORDER BY wt.created_at DESC
                LIMIT ? OFFSET ?
            ", [$perPage, $offset]);
            
            echo json_encode([
                'success' => true,
                'transactions' => $transactions,
                'pagination' => [
                    'current_page' => $page,
                    'total_pages' => $totalPages,
                    'total_items' => $total,
                    'per_page' => $perPage
                ]
            ]);
            exit;
            
        case 'update_exchange_rate':
            $newRate = floatval($_POST['rate'] ?? 1000);
            if ($newRate > 0) {
                $walletManager->updateExchangeRate($newRate);
                echo json_encode(['success' => true, 'message' => 'Đã cập nhật tỷ giá']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Tỷ giá không hợp lệ']);
            }
            exit;
            
        case 'manual_deposit':
            $userId = $_POST['user_id'] ?? '';
            $amount = floatval($_POST['amount'] ?? 0);
            $note = $_POST['note'] ?? 'Admin manual deposit';
            
            if ($userId && $amount > 0) {
                $result = $walletManager->deposit($userId, $amount, null, $note);
                echo json_encode($result);
            } else {
                echo json_encode(['success' => false, 'message' => 'Invalid data']);
            }
            exit;
            
        case 'process_pending':
            // Process pending bank logs
            $logId = $_POST['log_id'] ?? '';
            $userId = $_POST['user_id'] ?? '';
            
            if ($logId && $userId) {
                // Get bank log
                $log = $db->query("SELECT * FROM bank_logs WHERE id = ?", [$logId]);
                if (!empty($log)) {
                    $log = $log[0];
                    
                    // Process deposit
                    $result = $walletManager->deposit(
                        $userId,
                        $log['amount'],
                        $log['transaction_id'],
                        "Manual process: " . $log['description']
                    );
                    
                    if ($result['success']) {
                        // Update bank log status
                        $db->query(
                            "UPDATE bank_logs SET status = 'processed', user_id = ?, processed_at = NOW() WHERE id = ?",
                            [$userId, $logId]
                        );
                        
                        echo json_encode(['success' => true, 'message' => 'Đã xử lý thành công']);
                    } else {
                        echo json_encode($result);
                    }
                } else {
                    echo json_encode(['success' => false, 'message' => 'Log not found']);
                }
            } else {
                echo json_encode(['success' => false, 'message' => 'Invalid data']);
            }
            exit;
    }
}

// Get current exchange rate
$exchangeRate = $walletManager->getExchangeRate();

// Get statistics
$stats = [
    'total_wallets' => 0,
    'total_balance' => 0,
    'total_deposited' => 0,
    'pending_logs' => 0
];

try {
    // Wallet stats
    $walletStats = $db->query("
        SELECT 
            COUNT(*) as total_wallets,
            SUM(balance) as total_balance,
            SUM(total_deposited) as total_deposited
        FROM wallets
    ");
    
    if (!empty($walletStats)) {
        $stats['total_wallets'] = $walletStats[0]['total_wallets'] ?? 0;
        $stats['total_balance'] = $walletStats[0]['total_balance'] ?? 0;
        $stats['total_deposited'] = $walletStats[0]['total_deposited'] ?? 0;
    }
    
    // Pending logs
    $pendingCount = $db->query("SELECT COUNT(*) as count FROM bank_logs WHERE status = 'pending'");
    $stats['pending_logs'] = $pendingCount[0]['count'] ?? 0;
    
} catch (Exception $e) {
    error_log('Stats error: ' . $e->getMessage());
}

// Get pending bank logs
$pendingLogs = $db->query("
    SELECT bl.*, u.email as user_email
    FROM bank_logs bl
    LEFT JOIN users u ON bl.user_id = u.id
    WHERE bl.status = 'pending'
    ORDER BY bl.created_at DESC
    LIMIT 20
");

$pageTitle = "Quản Lý Ví & XU - Admin - AIboost.vn";
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }
        
        /* Loading spinner */
        .loading-spinner {
            border: 3px solid #f3f3f3;
            border-top: 3px solid #3498db;
            border-radius: 50%;
            width: 30px;
            height: 30px;
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        /* Pagination styles */
        .pagination-btn {
            transition: all 0.2s ease;
        }
        
        .pagination-btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        
        .pagination-btn.active {
            background: linear-gradient(135deg, #3b82f6, #1d4ed8);
            color: white;
            font-weight: 600;
        }
        
        .pagination-btn.disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        
        .pagination-btn.disabled:hover {
            transform: none;
            box-shadow: none;
        }
        
        /* Smooth transitions for table */
        #transactionsTable {
            transition: opacity 0.3s ease;
        }
        
        #transactionsTable.loading {
            opacity: 0.5;
        }
        
        /* ID Column Styles */
        .id-column {
            width: 120px;
            max-width: 120px;
            min-width: 80px;
        }
        
        .id-text {
            font-family: monospace;
            font-size: 11px;
            max-width: 100px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            cursor: help;
        }
        
        /* Tooltip for full ID */
        .id-tooltip {
            position: relative;
        }
        
        .id-tooltip:hover::after {
            content: attr(data-full-id);
            position: absolute;
            top: -30px;
            left: 0;
            background: #1f2937;
            color: white;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 10px;
            white-space: nowrap;
            z-index: 1000;
            box-shadow: 0 2px 8px rgba(0,0,0,0.2);
        }
        
        /* Mobile responsive adjustments */
        @media (max-width: 768px) {
            .id-column {
                width: 80px;
                max-width: 80px;
                min-width: 60px;
            }
            
            .id-text {
                max-width: 60px;
                font-size: 10px;
            }
        }
    </style>
</head>
<body class="min-h-screen bg-gray-50">
    <?php include __DIR__ . '/partials/sidebar.php'; ?>
    <?php include __DIR__ . '/partials/header.php'; ?>
    
    <div class="lg:ml-64">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
            
            <!-- Page Header -->
            <div class="mb-8">
                <h1 class="text-3xl font-bold text-gray-900">💰 Quản Lý Ví & XU</h1>
                <p class="text-gray-600 mt-2">Quản lý ví điện tử và tỷ giá quy đổi</p>
            </div>
            
            <!-- Stats Cards -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
                <div class="bg-white rounded-xl shadow-sm border p-6">
                    <div class="flex items-center">
                        <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center mr-4">
                            <i class="fas fa-wallet text-blue-600 text-xl"></i>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600">Tổng ví</p>
                            <p class="text-2xl font-bold text-gray-900"><?= number_format($stats['total_wallets']) ?></p>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white rounded-xl shadow-sm border p-6">
                    <div class="flex items-center">
                        <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center mr-4">
                            <i class="fas fa-coins text-green-600 text-xl"></i>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600">Tổng XU</p>
                            <p class="text-2xl font-bold text-green-600"><?= number_format($stats['total_balance']) ?></p>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white rounded-xl shadow-sm border p-6">
                    <div class="flex items-center">
                        <div class="w-12 h-12 bg-purple-100 rounded-lg flex items-center justify-center mr-4">
                            <i class="fas fa-chart-line text-purple-600 text-xl"></i>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600">Tổng nạp</p>
                            <p class="text-2xl font-bold text-purple-600"><?= number_format($stats['total_deposited']) ?>đ</p>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white rounded-xl shadow-sm border p-6">
                    <div class="flex items-center">
                        <div class="w-12 h-12 bg-orange-100 rounded-lg flex items-center justify-center mr-4">
                            <i class="fas fa-clock text-orange-600 text-xl"></i>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600">Chờ xử lý</p>
                            <p class="text-2xl font-bold text-orange-600"><?= number_format($stats['pending_logs']) ?></p>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Exchange Rate Management -->
            <div class="bg-white rounded-xl shadow-sm border mb-8">
                <div class="p-6">
                    <h2 class="text-xl font-bold mb-4">⚙️ Cấu hình tỷ giá</h2>
                    <div class="flex items-center gap-4">
                        <div class="flex-1">
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                Tỷ giá quy đổi (1 XU = ? VND)
                            </label>
                            <div class="flex gap-2">
                                <input type="number" id="exchangeRate" value="<?= $exchangeRate ?>" 
                                       class="flex-1 px-3 py-2 border rounded-lg">
                                <button onclick="updateExchangeRate()" 
                                        class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                                    💾 Cập nhật
                                </button>
                            </div>
                        </div>
                        <div class="bg-gray-50 p-4 rounded-lg">
                            <p class="text-sm text-gray-600">Ví dụ với tỷ giá hiện tại:</p>
                            <p class="font-mono">100,000 VND = <?= number_format(100000 / $exchangeRate) ?> XU</p>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Pending Bank Logs -->
            <?php if (!empty($pendingLogs)): ?>
            <div class="bg-white rounded-xl shadow-sm border mb-8">
                <div class="p-6">
                    <h2 class="text-xl font-bold mb-4">⏳ Giao dịch chờ xử lý</h2>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">ID</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Thời gian</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Số tiền</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Nội dung</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">User</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Hành động</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php foreach ($pendingLogs as $log): ?>
                                <tr>
                                    <td class="px-4 py-3 text-sm">#<?= $log['id'] ?></td>
                                    <td class="px-4 py-3 text-sm"><?= date('d/m H:i', strtotime($log['created_at'])) ?></td>
                                    <td class="px-4 py-3 text-sm font-medium text-green-600">
                                        <?= number_format($log['amount']) ?>đ
                                    </td>
                                    <td class="px-4 py-3 text-sm"><?= htmlspecialchars($log['description']) ?></td>
                                    <td class="px-4 py-3 text-sm">
                                        <?php if ($log['user_id']): ?>
                                            <span class="text-green-600">✓ <?= $log['user_email'] ?></span>
                                        <?php else: ?>
                                            <select id="user_select_<?= $log['id'] ?>" class="text-xs border rounded px-2 py-1">
                                                <option value="">-- Chọn user --</option>
                                                <?php
                                                $users = $db->query("SELECT id, email FROM users WHERE role = 'user' ORDER BY email");
                                                foreach ($users as $user):
                                                ?>
                                                <option value="<?= $user['id'] ?>"><?= $user['email'] ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-4 py-3">
                                        <button onclick="processPending(<?= $log['id'] ?>, '<?= $log['user_id'] ?>')"
                                                class="px-3 py-1 bg-green-600 text-white text-sm rounded hover:bg-green-700">
                                            ✅ Xử lý
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Recent Transactions with Pagination -->
            <div class="bg-white rounded-xl shadow-sm border">
                <div class="p-6">
                    <div class="flex items-center justify-between mb-4">
                        <h2 class="text-xl font-bold">📊 Giao dịch gần đây</h2>
                        
                        <!-- Loading Indicator -->
                        <div id="loadingIndicator" class="hidden flex items-center">
                            <div class="loading-spinner mr-2"></div>
                            <span class="text-sm text-gray-600">Đang tải...</span>
                        </div>
                        
                        <!-- Total Count -->
                        <div id="totalCount" class="text-sm text-gray-600">
                            <!-- Will be populated by JavaScript -->
                        </div>
                    </div>
                    
                    <!-- Transactions Table -->
                    <div class="overflow-x-auto" id="transactionsContainer">
                        <table class="min-w-full divide-y divide-gray-200" id="transactionsTable">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="id-column px-2 py-3 text-left text-xs font-medium text-gray-500 uppercase">ID</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">User</th>
                                    <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase">Loại</th>
                                    <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase">VND</th>
                                    <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase">XU</th>
                                    <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase">Số dư</th>
                                    <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase">Thời gian</th>
                                    <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase">Trạng thái</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200" id="transactionsBody">
                                <!-- Will be populated by JavaScript -->
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- Pagination -->
                    <div class="flex items-center justify-between mt-6 pt-4 border-t border-gray-200">
                        <!-- Page Info -->
                        <div class="flex items-center text-sm text-gray-600">
                            <span id="pageInfo">
                                <!-- Will be populated by JavaScript -->
                            </span>
                        </div>
                        
                        <!-- Pagination Buttons -->
                        <div class="flex items-center space-x-2" id="paginationContainer">
                            <!-- Will be populated by JavaScript -->
                        </div>
                        
                        <!-- Items per page selector -->
                        <div class="flex items-center space-x-2">
                            <label class="text-sm text-gray-600">Hiển thị:</label>
                            <select id="itemsPerPage" class="border rounded px-2 py-1 text-sm" onchange="changeItemsPerPage()">
                                <option value="10" selected>10</option>
                                <option value="20">20</option>
                                <option value="50">50</option>
                            </select>
                            <span class="text-sm text-gray-600">/ trang</span>
                        </div>
                    </div>
                </div>
            </div>
            
        </div>
    </div>
    
    <?php include __DIR__ . '/partials/footer.php'; ?>
    
    <script>
    // Global variables
    let currentPage = 1;
    let itemsPerPage = 10;
    let totalPages = 1;
    let totalItems = 0;
    
    // Function to truncate transaction ID
    function truncateTransactionId(id) {
        if (!id || id === '-') return '-';
        
        // Show first 6 and last 4 characters with ... in between
        if (id.length > 15) {
            return id.substring(0, 8) + '...' + id.substring(id.length - 4);
        }
        return id;
    }
    
    // Function to format transaction type
    function formatTransactionType(type) {
        const typeLabels = {
            'deposit': '<span class="text-green-600">↓ Nạp</span>',
            'withdraw': '<span class="text-red-600">↑ Rút</span>', 
            'purchase': '<span class="text-blue-600">🛒 Mua</span>',
            'refund': '<span class="text-purple-600">↩ Hoàn</span>'
        };
        return typeLabels[type] || type;
    }
    
    // Function to format transaction status
    function formatTransactionStatus(status) {
        const statusColors = {
            'completed': 'bg-green-100 text-green-800',
            'pending': 'bg-yellow-100 text-yellow-800',
            'failed': 'bg-red-100 text-red-800'
        };
        const statusLabels = {
            'completed': 'Thành công',
            'pending': 'Chờ xử lý',
            'failed': 'Thất bại'
        };
        const color = statusColors[status] || 'bg-gray-100 text-gray-800';
        const label = statusLabels[status] || status;
        return `<span class="px-2 py-1 text-xs rounded-full ${color}">${label}</span>`;
    }
    
    // Function to format XU amount based on type
    function formatXUAmount(amount, type) {
        if (type === 'deposit') {
            return `<span class="text-green-600">+${new Intl.NumberFormat('vi-VN').format(amount)}</span>`;
        } else if (type === 'purchase') {
            return `<span class="text-red-600">-${new Intl.NumberFormat('vi-VN').format(amount)}</span>`;
        } else {
            return new Intl.NumberFormat('vi-VN').format(amount);
        }
    }
    
    // Function to load transactions
    function loadTransactions(page = 1) {
        showLoading(true);
        
        fetch('', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: `action=get_transactions&page=${page}&per_page=${itemsPerPage}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                currentPage = data.pagination.current_page;
                totalPages = data.pagination.total_pages;
                totalItems = data.pagination.total_items;
                
                renderTransactions(data.transactions);
                renderPagination();
                updatePageInfo();
            } else {
                console.error('Error loading transactions:', data.message);
            }
        })
        .catch(error => {
            console.error('Network error:', error);
        })
        .finally(() => {
            showLoading(false);
        });
    }
    
    // Function to render transactions table
    function renderTransactions(transactions) {
        const tbody = document.getElementById('transactionsBody');
        
        if (transactions.length === 0) {
            tbody.innerHTML = `
                <tr>
                    <td colspan="8" class="px-4 py-8 text-center text-gray-500">
                        <div class="flex flex-col items-center">
                            <i class="fas fa-inbox text-4xl mb-2 text-gray-300"></i>
                            <p>Không có giao dịch nào</p>
                        </div>
                    </td>
                </tr>
            `;
            return;
        }
        
        tbody.innerHTML = transactions.map(tx => `
            <tr class="hover:bg-gray-50 transition-colors duration-200">
                <td class="id-column px-2 py-3">
                    <div class="id-tooltip id-text" 
                         data-full-id="${tx.transaction_id || '-'}" 
                         title="${tx.transaction_id || '-'}">
                        ${truncateTransactionId(tx.transaction_id)}
                    </div>
                </td>
                <td class="px-4 py-3 text-sm">
                    <div class="flex flex-col">
                        <span class="font-medium text-xs">${tx.user_email}</span>
                        ${tx.full_name ? `<span class="text-xs text-gray-500">${tx.full_name}</span>` : ''}
                    </div>
                </td>
                <td class="px-3 py-3 text-sm">${formatTransactionType(tx.type)}</td>
                <td class="px-3 py-3 text-sm">
                    ${tx.amount_vnd ? new Intl.NumberFormat('vi-VN').format(tx.amount_vnd) + 'đ' : '-'}
                </td>
                <td class="px-3 py-3 text-sm font-medium">
                    ${formatXUAmount(tx.amount_xu, tx.type)}
                </td>
                <td class="px-3 py-3 text-sm">${new Intl.NumberFormat('vi-VN').format(tx.balance_after)}</td>
                <td class="px-3 py-3 text-sm">
                    <div class="flex flex-col">
                        <span class="text-xs">${new Date(tx.created_at).toLocaleDateString('vi-VN')}</span>
                        <span class="text-xs text-gray-500">${new Date(tx.created_at).toLocaleTimeString('vi-VN', {hour: '2-digit', minute: '2-digit'})}</span>
                    </div>
                </td>
                <td class="px-3 py-3">${formatTransactionStatus(tx.status)}</td>
            </tr>
        `).join('');
    }
    
    // Function to render pagination
    function renderPagination() {
        const container = document.getElementById('paginationContainer');
        let html = '';
        
        // Previous button
        html += `
            <button onclick="goToPage(${currentPage - 1})" 
                    class="pagination-btn px-3 py-2 text-sm bg-white border rounded-lg hover:bg-gray-50 ${currentPage <= 1 ? 'disabled' : ''}"
                    ${currentPage <= 1 ? 'disabled' : ''}>
                <i class="fas fa-chevron-left mr-1"></i>
                Trước
            </button>
        `;
        
        // Page numbers
        const startPage = Math.max(1, currentPage - 2);
        const endPage = Math.min(totalPages, currentPage + 2);
        
        // First page
        if (startPage > 1) {
            html += `
                <button onclick="goToPage(1)" 
                        class="pagination-btn px-3 py-2 text-sm bg-white border rounded-lg hover:bg-gray-50">
                    1
                </button>
            `;
            if (startPage > 2) {
                html += '<span class="px-2 text-gray-500">...</span>';
            }
        }
        
        // Page numbers around current page
        for (let i = startPage; i <= endPage; i++) {
            html += `
                <button onclick="goToPage(${i})" 
                        class="pagination-btn px-3 py-2 text-sm border rounded-lg ${i === currentPage ? 'active' : 'bg-white hover:bg-gray-50'}">
                    ${i}
                </button>
            `;
        }
        
        // Last page
        if (endPage < totalPages) {
            if (endPage < totalPages - 1) {
                html += '<span class="px-2 text-gray-500">...</span>';
            }
            html += `
                <button onclick="goToPage(${totalPages})" 
                        class="pagination-btn px-3 py-2 text-sm bg-white border rounded-lg hover:bg-gray-50">
                    ${totalPages}
                </button>
            `;
        }
        
        // Next button
        html += `
            <button onclick="goToPage(${currentPage + 1})" 
                    class="pagination-btn px-3 py-2 text-sm bg-white border rounded-lg hover:bg-gray-50 ${currentPage >= totalPages ? 'disabled' : ''}"
                    ${currentPage >= totalPages ? 'disabled' : ''}>
                Sau
                <i class="fas fa-chevron-right ml-1"></i>
            </button>
        `;
        
        container.innerHTML = html;
    }
    
    // Function to update page info
    function updatePageInfo() {
        const startItem = ((currentPage - 1) * itemsPerPage) + 1;
        const endItem = Math.min(currentPage * itemsPerPage, totalItems);
        
        document.getElementById('pageInfo').innerHTML = `
            Hiển thị <strong>${startItem}-${endItem}</strong> trong <strong>${new Intl.NumberFormat('vi-VN').format(totalItems)}</strong> giao dịch
        `;
        
        document.getElementById('totalCount').innerHTML = `
            Tổng: <strong>${new Intl.NumberFormat('vi-VN').format(totalItems)}</strong> giao dịch
        `;
    }
    
    // Function to show/hide loading
    function showLoading(show) {
        const loadingIndicator = document.getElementById('loadingIndicator');
        const transactionsTable = document.getElementById('transactionsTable');
        
        if (show) {
            loadingIndicator.classList.remove('hidden');
            transactionsTable.classList.add('loading');
        } else {
            loadingIndicator.classList.add('hidden');
            transactionsTable.classList.remove('loading');
        }
    }
    
    // Function to go to specific page
    function goToPage(page) {
        if (page < 1 || page > totalPages) return;
        loadTransactions(page);
    }
    
    // Function to change items per page
    function changeItemsPerPage() {
        itemsPerPage = parseInt(document.getElementById('itemsPerPage').value);
        currentPage = 1; // Reset to first page
        loadTransactions(1);
    }
    
    // Other existing functions
    function updateExchangeRate() {
        const rate = document.getElementById('exchangeRate').value;
        
        fetch('', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: `action=update_exchange_rate&rate=${rate}`
        })
        .then(r => r.json())
        .then(data => {
            alert(data.message);
            if (data.success) location.reload();
        });
    }
    
    function processPending(logId, userId) {
        if (!userId) {
            userId = document.getElementById(`user_select_${logId}`).value;
            if (!userId) {
                alert('Vui lòng chọn user');
                return;
            }
        }
        
        if (!confirm('Xác nhận xử lý giao dịch này?')) return;
        
        fetch('', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: `action=process_pending&log_id=${logId}&user_id=${userId}`
        })
        .then(r => r.json())
        .then(data => {
            alert(data.message);
            if (data.success) location.reload();
        });
    }
    
    // Initialize on page load
    document.addEventListener('DOMContentLoaded', function() {
        updatePageTitle('Quản lý Ví & XU');
        loadTransactions(1); // Load first page
        
        // Add keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            if (e.ctrlKey) {
                switch(e.key) {
                    case 'ArrowLeft':
                        e.preventDefault();
                        if (currentPage > 1) goToPage(currentPage - 1);
                        break;
                    case 'ArrowRight':
                        e.preventDefault();
                        if (currentPage < totalPages) goToPage(currentPage + 1);
                        break;
                }
            }
        });
    });
    
    console.log('✅ Wallets admin page with optimized ID column loaded');
    </script>
</body>
</html>