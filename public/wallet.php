<?php
require_once __DIR__ . '/../app/config.php';
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/auth.php';
require_once __DIR__ . '/../app/middleware.php';

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Require login to access wallet
Middleware::requireLogin();

// Log activity
Middleware::logActivity('view_wallet');

// Get user data
$userData = Auth::getUser();

if (!$userData) {
    Auth::logout();
    header('Location: ' . url('login'));
    exit;
}

$userId = $userData['id'];
$userName = $userData['full_name'] ?? 'User';

// Get current balance from wallets table
$currentBalance = 0;
try {
    $walletInfo = $db->select('wallets', '*', ['user_id' => $userId]);
    if ($walletInfo && count($walletInfo) > 0) {
        $currentBalance = (int)$walletInfo[0]['balance'];
    } else {
        // Create wallet if not exists
        try {
            $db->insert('wallets', [
                'user_id' => $userId,
                'balance' => 500, // Default 500 XU for new users
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ]);
            $currentBalance = 500;
        } catch (Exception $e) {
            error_log('Create wallet error: ' . $e->getMessage());
        }
    }
} catch (Exception $e) {
    error_log('Get wallet balance error: ' . $e->getMessage());
}

// Get wallet statistics from wallet_transactions
$walletStats = [
    'total_credited' => 0,
    'total_spent' => 0,
    'transaction_count' => 0,
    'avg_transaction' => 0,
    'this_month_credited' => 0,
    'this_month_spent' => 0,
    'last_topup' => null
];

try {
    // Get all transactions for stats from wallet_transactions
    $allTransactions = $db->select('wallet_transactions', '*', ['user_id' => $userId]);
    
    if ($allTransactions) {
        $walletStats['transaction_count'] = count($allTransactions);
        
        foreach ($allTransactions as $tx) {
            $amount = (int)$tx['amount_xu'];
            
            // Calculate totals based on transaction type
            switch ($tx['type']) {
                case 'deposit':
                case 'system_gift':
                    $walletStats['total_credited'] += $amount;
                    
                    // Track last topup (exclude system gifts)
                    if ($tx['type'] === 'deposit' && (!$walletStats['last_topup'] || $tx['created_at'] > $walletStats['last_topup'])) {
                        $walletStats['last_topup'] = $tx['created_at'];
                    }
                    
                    // This month credited
                    if (date('Y-m', strtotime($tx['created_at'])) === date('Y-m')) {
                        $walletStats['this_month_credited'] += $amount;
                    }
                    break;
                    
                case 'withdraw':
                case 'payment':
                    $walletStats['total_spent'] += $amount;
                    
                    // This month spent
                    if (date('Y-m', strtotime($tx['created_at'])) === date('Y-m')) {
                        $walletStats['this_month_spent'] += $amount;
                    }
                    break;
            }
        }
        
        if ($walletStats['transaction_count'] > 0) {
            $walletStats['avg_transaction'] = ($walletStats['total_credited'] + $walletStats['total_spent']) / $walletStats['transaction_count'];
        }
    }
} catch (Exception $e) {
    error_log('Wallet stats error: ' . $e->getMessage());
}

// Get recent transactions (paginated) from wallet_transactions
$page = (int)($_GET['page'] ?? 1);
$limit = 10; // 10 giao dịch mỗi trang
$offset = ($page - 1) * $limit;

try {
    // Get transactions with pagination from wallet_transactions
    $pdo = $db->getPdo();
    $stmt = $pdo->prepare("SELECT * FROM wallet_transactions WHERE user_id = ? ORDER BY created_at DESC LIMIT ? OFFSET ?");
    $stmt->execute([$userId, $limit, $offset]);
    $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get total count for pagination
    $countStmt = $pdo->prepare("SELECT COUNT(*) as count FROM wallet_transactions WHERE user_id = ?");
    $countStmt->execute([$userId]);
    $totalTransactions = (int)$countStmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    $totalPages = ceil($totalTransactions / $limit);
    
} catch (Exception $e) {
    error_log('Wallet transactions error: ' . $e->getMessage());
    $transactions = [];
    $totalTransactions = 0;
    $totalPages = 1;
}

// Handle AJAX requests
if (isset($_GET['action'])) {
    header('Content-Type: application/json');
    
    switch ($_GET['action']) {
        case 'get_transactions':
            $filterType = $_GET['type'] ?? 'all';
            $filterMonth = $_GET['month'] ?? '';
            $ajaxPage = (int)($_GET['page'] ?? 1);
            $ajaxOffset = ($ajaxPage - 1) * $limit;
            
            try {
                $sql = "SELECT * FROM wallet_transactions WHERE user_id = ?";
                $params = [$userId];
                
                if ($filterType !== 'all') {
                    if ($filterType === 'credit') {
                        $sql .= " AND type IN ('deposit', 'system_gift')";
                    } elseif ($filterType === 'debit') {
                        $sql .= " AND type IN ('withdraw', 'payment')";
                    }
                }
                
                if ($filterMonth) {
                    $sql .= " AND DATE_FORMAT(created_at, '%Y-%m') = ?";
                    $params[] = $filterMonth;
                }
                
                // Get total count for filtered results
                $countSql = str_replace('SELECT *', 'SELECT COUNT(*) as count', $sql);
                $countStmt = $pdo->prepare($countSql);
                $countStmt->execute($params);
                $filteredTotal = (int)$countStmt->fetch(PDO::FETCH_ASSOC)['count'];
                $filteredPages = ceil($filteredTotal / $limit);
                
                $sql .= " ORDER BY created_at DESC LIMIT ? OFFSET ?";
                $params[] = $limit;
                $params[] = $ajaxOffset;
                
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
                $filteredTransactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                echo json_encode([
                    'success' => true,
                    'transactions' => $filteredTransactions,
                    'pagination' => [
                        'current_page' => $ajaxPage,
                        'total_pages' => $filteredPages,
                        'total_records' => $filteredTotal,
                        'per_page' => $limit
                    ]
                ]);
            } catch (Exception $e) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Lỗi khi lấy giao dịch: ' . $e->getMessage()
                ]);
            }
            exit;
            
        case 'export_transactions':
            try {
                // Export all transactions from wallet_transactions
                $stmt = $pdo->prepare("SELECT * FROM wallet_transactions WHERE user_id = ? ORDER BY created_at DESC");
                $stmt->execute([$userId]);
                $exportTransactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                header('Content-Type: text/csv; charset=utf-8');
                header('Content-Disposition: attachment; filename="wallet_transactions_' . date('Y-m-d') . '.csv"');
                
                // Add BOM for UTF-8
                echo "\xEF\xBB\xBF";
                echo "STT,Ngày,Loại,Số XU,Số VND,Tỷ giá,Mô tả,Trạng thái\n";
                
                $stt = 1;
                foreach ($exportTransactions as $tx) {
                    $typeLabel = '';
                    switch ($tx['type']) {
                        case 'deposit': $typeLabel = 'Nạp XU'; break;
                        case 'withdraw': $typeLabel = 'Rút XU'; break;
                        case 'payment': $typeLabel = 'Thanh toán'; break;
                        case 'system_gift': $typeLabel = 'Tặng từ hệ thống'; break;
                        default: $typeLabel = ucfirst($tx['type']);
                    }
                    
                    echo sprintf(
                        "%d,%s,%s,%s,%s,%s,%s,%s\n",
                        $stt++,
                        date('d/m/Y H:i', strtotime($tx['created_at'])),
                        $typeLabel,
                        number_format($tx['amount_xu']),
                        number_format($tx['amount_vnd'] ?? 0),
                        number_format($tx['exchange_rate'] ?? 0),
                        '"' . str_replace('"', '""', $tx['description'] ?? '') . '"',
                        ucfirst($tx['status'] ?? 'completed')
                    );
                }
            } catch (Exception $e) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => 'Lỗi xuất file: ' . $e->getMessage()]);
            }
            exit;
    }
}

$pageTitle = "Ví & Lịch Sử - AIboost.vn";
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
        
        .balance-gradient {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        
        .transaction-row:hover {
            background-color: #f8fafc;
        }
        
        .filter-tab.active {
            background-color: #3b82f6;
            color: white;
        }
        
        .xu-currency {
            background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            font-weight: bold;
        }
        
        .xu-icon {
            background: linear-gradient(135deg, #fbbf24 0%, #f59e0b 100%);
            border: 2px solid #f59e0b;
            width: 20px;
            height: 20px;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 12px;
            font-weight: bold;
            margin-right: 6px;
            box-shadow: 0 2px 4px rgba(245, 158, 11, 0.3);
            text-shadow: 0 1px 2px rgba(0, 0, 0, 0.2);
        }
        
        .xu-icon-large {
            width: 24px;
            height: 24px;
            font-size: 14px;
            margin-right: 8px;
            border: 3px solid #f59e0b;
            box-shadow: 0 3px 6px rgba(245, 158, 11, 0.4);
        }
        
        .xu-icon-balance {
            background: linear-gradient(135deg, #fbbf24 0%, #f59e0b 100%);
            border: 3px solid #ffffff;
            width: 28px;
            height: 28px;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 16px;
            font-weight: bold;
            margin-right: 8px;
            box-shadow: 0 4px 8px rgba(245, 158, 11, 0.5);
            text-shadow: 0 1px 2px rgba(0, 0, 0, 0.3);
        }
        
        .xu-icon-stats {
            background: linear-gradient(135deg, #fbbf24 0%, #f59e0b 100%);
            border: 2px solid #f59e0b;
            width: 18px;
            height: 18px;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 11px;
            font-weight: bold;
            margin-right: 5px;
            box-shadow: 0 2px 4px rgba(245, 158, 11, 0.3);
            text-shadow: 0 1px 2px rgba(0, 0, 0, 0.2);
        }

        /* Custom scrollbar for table */
        .table-container {
            max-height: 600px;
            overflow-y: auto;
            scrollbar-width: thin;
            scrollbar-color: #cbd5e1 #f1f5f9;
        }
        
        .table-container::-webkit-scrollbar {
            width: 6px;
        }
        
        .table-container::-webkit-scrollbar-track {
            background: #f1f5f9;
            border-radius: 3px;
        }
        
        .table-container::-webkit-scrollbar-thumb {
            background: #cbd5e1;
            border-radius: 3px;
        }
        
        .table-container::-webkit-scrollbar-thumb:hover {
            background: #94a3b8;
        }

        .pagination-button {
            transition: all 0.2s ease;
        }
        
        .pagination-button:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }
        
        .pagination-button.active {
            background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%);
            color: white;
            box-shadow: 0 4px 12px rgba(59, 130, 246, 0.4);
        }
        
        .loading-overlay {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(255, 255, 255, 0.9);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 10;
        }
    </style>
</head>
<body class="min-h-screen bg-gray-50">
    <!-- Include Sidebar -->
    <?php include __DIR__ . '/partials/sidebar.php'; ?>
    
    <!-- Include Header -->
    <?php include __DIR__ . '/partials/header.php'; ?>

    <!-- Main Content -->
    <div class="lg:ml-64 pt-16">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
            
            <!-- Page Header -->
            <div class="mb-6">
                <h1 class="text-2xl font-bold text-gray-900">💼 Ví & Lịch Sử Giao Dịch</h1>
                <p class="text-gray-600 mt-1">Quản lý số dư và theo dõi các giao dịch của bạn</p>
            </div>

            <!-- Balance & Quick Actions -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
                
                <!-- Main Balance Card -->
                <div class="lg:col-span-2">
                    <div class="balance-gradient rounded-xl text-white p-6 fade-in">
                        <div class="flex items-center justify-between mb-4">
                            <div>
                                <p class="text-blue-100 text-sm">Số dư hiện tại</p>
                                <p class="text-3xl font-bold flex items-center">
                                    <span class="xu-icon-balance">X</span>
                                    <?= number_format($currentBalance) ?> <span class="text-white font-bold ml-2">XU</span>
                                </p>
                            </div>
                            <div class="text-5xl opacity-80">
                                <span class="xu-icon-balance" style="width: 60px; height: 60px; font-size: 32px; border: 4px solid white;">X</span>
                            </div>
                        </div>
                        
                        <div class="grid grid-cols-2 gap-4 pt-4 border-t border-blue-300 border-opacity-30">
                            <div>
                                <p class="text-blue-100 text-xs">Tổng đã nạp</p>
                                <p class="text-lg font-semibold flex items-center">
                                    <span class="xu-icon" style="border: 2px solid white;">X</span>
                                    +<?= number_format($walletStats['total_credited']) ?> <span class="text-white font-bold">XU</span>
                                </p>
                            </div>
                            <div>
                                <p class="text-blue-100 text-xs">Tổng đã chi</p>
                                <p class="text-lg font-semibold flex items-center">
                                    <span class="xu-icon" style="border: 2px solid white;">X</span>
                                    -<?= number_format($walletStats['total_spent']) ?> <span class="text-white font-bold">XU</span>
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Quick Actions -->
                <div class="space-y-4">
                    <a href="<?= url('topup') ?>" class="block bg-green-600 text-white rounded-xl p-4 hover:bg-green-700 transition-colors fade-in">
                        <div class="flex items-center">
                            <div class="mr-3">
                                <span class="xu-icon-balance" style="width: 40px; height: 40px; font-size: 20px; border: 3px solid white;">X</span>
                            </div>
                            <div>
                                <div class="font-semibold">Nạp XU</div>
                                <div class="text-sm text-green-100">Thêm XU vào ví</div>
                            </div>
                        </div>
                    </a>
                    
                    <a href="<?= url('pricing') ?>" class="block bg-blue-600 text-white rounded-xl p-4 hover:bg-blue-700 transition-colors fade-in">
                        <div class="flex items-center">
                            <div class="text-3xl mr-3">📦</div>
                            <div>
                                <div class="font-semibold">Mua Gói</div>
                                <div class="text-sm text-blue-100">Nâng cấp dịch vụ</div>
                            </div>
                        </div>
                    </a>
                    
                    <button onclick="exportTransactions()" class="w-full bg-gray-600 text-white rounded-xl p-4 hover:bg-gray-700 transition-colors fade-in">
                        <div class="flex items-center">
                            <div class="text-3xl mr-3">📊</div>
                            <div>
                                <div class="font-semibold">Xuất Excel</div>
                                <div class="text-sm text-gray-100">Tải lịch sử giao dịch</div>
                            </div>
                        </div>
                    </button>
                </div>
            </div>

            <!-- Statistics Cards -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
                <div class="bg-white rounded-xl shadow-sm p-6 border fade-in">
                    <div class="flex items-center">
                        <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center mr-4">
                            <i class="fas fa-exchange-alt text-blue-600 text-xl"></i>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600">Tổng giao dịch</p>
                            <p class="text-2xl font-bold text-gray-900"><?= number_format($walletStats['transaction_count']) ?></p>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white rounded-xl shadow-sm p-6 border fade-in">
                    <div class="flex items-center">
                        <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center mr-4">
                            <i class="fas fa-arrow-up text-green-600 text-xl"></i>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600">Trung bình/giao dịch</p>
                            <p class="text-xl font-bold text-gray-900 flex items-center">
                                <span class="xu-icon-stats">X</span>
                                <?= number_format($walletStats['avg_transaction']) ?> <span class="xu-currency">XU</span>
                            </p>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white rounded-xl shadow-sm p-6 border fade-in">
                    <div class="flex items-center">
                        <div class="w-12 h-12 bg-orange-100 rounded-lg flex items-center justify-center mr-4">
                            <i class="fas fa-calendar-alt text-orange-600 text-xl"></i>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600">Chi tiêu tháng này</p>
                            <p class="text-xl font-bold text-gray-900 flex items-center">
                                <span class="xu-icon-stats">X</span>
                                <?= number_format($walletStats['this_month_spent']) ?> <span class="xu-currency">XU</span>
                            </p>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white rounded-xl shadow-sm p-6 border fade-in">
                    <div class="flex items-center">
                        <div class="w-12 h-12 bg-purple-100 rounded-lg flex items-center justify-center mr-4">
                            <i class="fas fa-clock text-purple-600 text-xl"></i>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600">Lần nạp cuối</p>
                            <p class="text-lg font-bold text-gray-900">
                                <?= $walletStats['last_topup'] ? date('d/m/Y', strtotime($walletStats['last_topup'])) : 'Chưa có' ?>
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Transaction History -->
            <div class="bg-white rounded-xl shadow-sm border fade-in">
                <div class="px-6 py-4 border-b border-gray-200">
                    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center space-y-4 sm:space-y-0">
                        <h3 class="text-lg font-semibold text-gray-900">📋 Lịch Sử Giao Dịch</h3>
                        
                        <!-- Filters -->
                        <div class="flex flex-wrap gap-2">
                            <button class="filter-tab active px-3 py-2 text-sm rounded-lg border" data-type="all">
                                Tất cả
                            </button>
                            <button class="filter-tab px-3 py-2 text-sm rounded-lg border hover:bg-gray-50" data-type="credit">
                                Nạp XU
                            </button>
                            <button class="filter-tab px-3 py-2 text-sm rounded-lg border hover:bg-gray-50" data-type="debit">
                                Chi tiêu
                            </button>
                            <button onclick="refreshTransactions()" class="px-3 py-2 text-sm bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                                <i class="fas fa-sync-alt mr-1"></i>
                                Làm mới
                            </button>
                        </div>
                    </div>
                </div>
                
                <!-- Table Container with loading -->
                <div class="relative">
                    <div id="loadingOverlay" class="loading-overlay hidden">
                        <div class="flex items-center space-x-2">
                            <div class="animate-spin rounded-full h-6 w-6 border-b-2 border-blue-600"></div>
                            <span class="text-gray-600 font-medium">Đang tải...</span>
                        </div>
                    </div>
                    
                    <div class="table-container">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50 sticky top-0 z-10">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        STT
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Thời gian
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Loại
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Số XU
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Số tiền (VND)
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Mô tả
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Trạng thái
                                    </th>
                                </tr>
                            </thead>
                            <tbody id="transactionTableBody" class="bg-white divide-y divide-gray-200">
                                <?php if (empty($transactions)): ?>
                                <tr>
                                    <td colspan="7" class="px-6 py-12 text-center text-gray-500">
                                        <div class="text-center mb-4">
                                            <span class="xu-icon-balance" style="width: 80px; height: 80px; font-size: 40px; border: 4px solid #f59e0b;">X</span>
                                        </div>
                                        <p class="text-lg font-medium mb-2">Chưa có giao dịch nào</p>
                                        <p class="text-sm">Lịch sử giao dịch của bạn sẽ hiển thị ở đây</p>
                                        <a href="<?= url('topup') ?>" class="inline-flex items-center mt-4 px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                                            <i class="fas fa-plus mr-2"></i>
                                            Nạp XU ngay
                                        </a>
                                    </td>
                                </tr>
                                <?php else: ?>
                                <?php foreach ($transactions as $index => $tx): ?>
                                <tr class="transaction-row">
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                        <?= ($page - 1) * $limit + $index + 1 ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        <?= date('d/m/Y H:i', strtotime($tx['created_at'])) ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <?php
                                        $typeInfo = getTransactionTypeInfo($tx['type']);
                                        ?>
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?= $typeInfo['class'] ?>">
                                            <?= $typeInfo['label'] ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium <?= in_array($tx['type'], ['deposit', 'system_gift']) ? 'text-green-600' : 'text-red-600' ?>">
                                        <?= in_array($tx['type'], ['deposit', 'system_gift']) ? '+' : '-' ?><?= number_format($tx['amount_xu']) ?> <span class="xu-currency">XU</span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        <?= $tx['amount_vnd'] ? number_format($tx['amount_vnd']) . ' ₫' : '-' ?>
                                    </td>
                                    <td class="px-6 py-4 text-sm text-gray-900 max-w-xs truncate">
                                        <?= htmlspecialchars($tx['description'] ?? 'Không có mô tả') ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                            <?php 
                                            switch($tx['status'] ?? 'completed') {
                                                case 'completed': echo 'bg-green-100 text-green-800'; break;
                                                case 'pending': echo 'bg-yellow-100 text-yellow-800'; break;
                                                case 'failed': echo 'bg-red-100 text-red-800'; break;
                                                default: echo 'bg-gray-100 text-gray-800';
                                            }
                                            ?>">
                                            <?= ucfirst($tx['status'] ?? 'completed') ?>
                                        </span>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                
                <!-- Pagination -->
                <div id="paginationContainer" class="px-6 py-4 border-t border-gray-200">
                    <?php if ($totalPages > 1): ?>
                    <div class="flex flex-col sm:flex-row items-center justify-between space-y-4 sm:space-y-0">
                        <div class="text-sm text-gray-500">
                            Hiển thị <span class="font-medium"><?= ($page - 1) * $limit + 1 ?></span> - 
                            <span class="font-medium"><?= min($page * $limit, $totalTransactions) ?></span> 
                            trong tổng số <span class="font-medium"><?= number_format($totalTransactions) ?></span> giao dịch
                        </div>
                        
                        <div class="flex items-center space-x-2">
                            <!-- First page -->
                            <?php if ($page > 2): ?>
                            <button onclick="goToPage(1)" class="pagination-button px-3 py-2 text-sm bg-white border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50">
                                1
                            </button>
                            <?php if ($page > 3): ?>
                            <span class="px-2 text-gray-400">...</span>
                            <?php endif; ?>
                            <?php endif; ?>
                            
                            <!-- Previous page -->
                            <?php if ($page > 1): ?>
                            <button onclick="goToPage(<?= $page - 1 ?>)" class="pagination-button px-3 py-2 text-sm bg-white border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50">
                                <i class="fas fa-chevron-left mr-1"></i>Trước
                            </button>
                            <?php endif; ?>
                            
                            <!-- Current and nearby pages -->
                            <?php for ($i = max(1, $page - 1); $i <= min($totalPages, $page + 1); $i++): ?>
                            <button onclick="goToPage(<?= $i ?>)" 
                                    class="pagination-button px-3 py-2 text-sm rounded-lg font-medium <?= $i === $page ? 'active' : 'bg-white border border-gray-300 text-gray-700 hover:bg-gray-50' ?>">
                                <?= $i ?>
                            </button>
                            <?php endfor; ?>
                            
                            <!-- Next page -->
                            <?php if ($page < $totalPages): ?>
                            <button onclick="goToPage(<?= $page + 1 ?>)" class="pagination-button px-3 py-2 text-sm bg-white border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50">
                                Sau<i class="fas fa-chevron-right ml-1"></i>
                            </button>
                            <?php endif; ?>
                            
                            <!-- Last page -->
                            <?php if ($page < $totalPages - 1): ?>
                            <?php if ($page < $totalPages - 2): ?>
                            <span class="px-2 text-gray-400">...</span>
                            <?php endif; ?>
                            <button onclick="goToPage(<?= $totalPages ?>)" class="pagination-button px-3 py-2 text-sm bg-white border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50">
                                <?= $totalPages ?>
                            </button>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Include Footer -->
    <?php include __DIR__ . '/partials/footer.php'; ?>

    <script>
        let currentFilter = 'all';
        let currentPage = <?= $page ?>;
        let isLoading = false;

        // Filter functionality
        document.querySelectorAll('.filter-tab').forEach(tab => {
            tab.addEventListener('click', function() {
                if (isLoading) return;
                
                // Remove active class from all tabs
                document.querySelectorAll('.filter-tab').forEach(t => {
                    t.classList.remove('active', 'bg-blue-600', 'text-white');
                    t.classList.add('hover:bg-gray-50');
                });
                
                // Add active class to clicked tab
                this.classList.add('active', 'bg-blue-600', 'text-white');
                this.classList.remove('hover:bg-gray-50');
                
                // Filter transactions
                currentFilter = this.dataset.type;
                currentPage = 1;
                filterTransactions(currentFilter, 1);
            });
        });

        async function filterTransactions(type, page = 1) {
            if (isLoading) return;
            
            isLoading = true;
            showLoading();
            
            try {
                const response = await fetch(`?action=get_transactions&type=${type}&page=${page}`);
                const result = await response.json();
                
                if (result.success) {
                    updateTransactionTable(result.transactions, (page - 1) * 10);
                    updatePagination(result.pagination, type);
                    currentPage = page;
                } else {
                    console.error('Filter error:', result.message);
                    showError('Lỗi khi tải dữ liệu');
                }
            } catch (error) {
                console.error('Filter error:', error);
                showError('Lỗi kết nối');
            } finally {
                hideLoading();
                isLoading = false;
            }
        }

        function updateTransactionTable(transactions, startIndex = 0) {
            const tbody = document.getElementById('transactionTableBody');
            
            if (transactions.length === 0) {
                tbody.innerHTML = `
                    <tr>
                        <td colspan="7" class="px-6 py-12 text-center text-gray-500">
                            <div class="text-center mb-4">
                                <span class="xu-icon-balance" style="width: 80px; height: 80px; font-size: 40px; border: 4px solid #f59e0b;">X</span>
                            </div>
                            <p class="text-lg font-medium mb-2">Không tìm thấy giao dịch</p>
                            <p class="text-sm">Thử thay đổi bộ lọc</p>
                        </td>
                    </tr>
                `;
                return;
            }
            
            tbody.innerHTML = transactions.map((tx, index) => {
                const typeInfo = getTransactionTypeInfoJS(tx.type);
                const isCredit = ['deposit', 'system_gift'].includes(tx.type);
                const amountClass = isCredit ? 'text-green-600' : 'text-red-600';
                const statusClass = getStatusClass(tx.status);
                const stt = startIndex + index + 1;
                
                return `
                    <tr class="transaction-row">
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                            ${stt}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                            ${new Date(tx.created_at).toLocaleString('vi-VN')}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${typeInfo.class}">
                                ${typeInfo.label}
                            </span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium ${amountClass}">
                            ${isCredit ? '+' : '-'}${parseInt(tx.amount_xu).toLocaleString('vi-VN')} <span class="xu-currency">XU</span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                            ${tx.amount_vnd ? parseInt(tx.amount_vnd).toLocaleString('vi-VN') + ' ₫' : '-'}
                        </td>
                        <td class="px-6 py-4 text-sm text-gray-900 max-w-xs truncate">
                            ${tx.description || 'Không có mô tả'}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${statusClass}">
                                ${tx.status ? tx.status.charAt(0).toUpperCase() + tx.status.slice(1) : 'Completed'}
                            </span>
                        </td>
                    </tr>
                `;
            }).join('');
        }

        function updatePagination(pagination, filterType) {
            const container = document.getElementById('paginationContainer');
            
            if (pagination.total_pages <= 1) {
                container.innerHTML = `
                    <div class="px-6 py-4 text-center text-sm text-gray-500">
                        Hiển thị tất cả ${pagination.total_records} giao dịch
                    </div>
                `;
                return;
            }
            
            const currentPage = pagination.current_page;
            const totalPages = pagination.total_pages;
            const startRecord = (currentPage - 1) * pagination.per_page + 1;
            const endRecord = Math.min(currentPage * pagination.per_page, pagination.total_records);
            
            let paginationHTML = `
                <div class="flex flex-col sm:flex-row items-center justify-between space-y-4 sm:space-y-0">
                    <div class="text-sm text-gray-500">
                        Hiển thị <span class="font-medium">${startRecord}</span> - 
                        <span class="font-medium">${endRecord}</span> 
                        trong tổng số <span class="font-medium">${pagination.total_records.toLocaleString('vi-VN')}</span> giao dịch
                    </div>
                    
                    <div class="flex items-center space-x-2">
            `;
            
            // First page
            if (currentPage > 2) {
                paginationHTML += `<button onclick="goToFilterPage(1, '${filterType}')" class="pagination-button px-3 py-2 text-sm bg-white border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50">1</button>`;
                if (currentPage > 3) {
                    paginationHTML += `<span class="px-2 text-gray-400">...</span>`;
                }
            }
            
            // Previous page
            if (currentPage > 1) {
                paginationHTML += `<button onclick="goToFilterPage(${currentPage - 1}, '${filterType}')" class="pagination-button px-3 py-2 text-sm bg-white border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50"><i class="fas fa-chevron-left mr-1"></i>Trước</button>`;
            }
            
            // Current and nearby pages
            for (let i = Math.max(1, currentPage - 1); i <= Math.min(totalPages, currentPage + 1); i++) {
                const activeClass = i === currentPage ? 'active' : 'bg-white border border-gray-300 text-gray-700 hover:bg-gray-50';
                paginationHTML += `<button onclick="goToFilterPage(${i}, '${filterType}')" class="pagination-button px-3 py-2 text-sm rounded-lg font-medium ${activeClass}">${i}</button>`;
            }
            
            // Next page
            if (currentPage < totalPages) {
                paginationHTML += `<button onclick="goToFilterPage(${currentPage + 1}, '${filterType}')" class="pagination-button px-3 py-2 text-sm bg-white border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50">Sau<i class="fas fa-chevron-right ml-1"></i></button>`;
            }
            
            // Last page
            if (currentPage < totalPages - 1) {
                if (currentPage < totalPages - 2) {
                    paginationHTML += `<span class="px-2 text-gray-400">...</span>`;
                }
                paginationHTML += `<button onclick="goToFilterPage(${totalPages}, '${filterType}')" class="pagination-button px-3 py-2 text-sm bg-white border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50">${totalPages}</button>`;
            }
            
            paginationHTML += `
                    </div>
                </div>
            `;
            
            container.innerHTML = paginationHTML;
        }

        function goToPage(page) {
            if (currentFilter === 'all') {
                window.location.href = `?page=${page}`;
            } else {
                goToFilterPage(page, currentFilter);
            }
        }

        function goToFilterPage(page, filterType) {
            filterTransactions(filterType, page);
        }

        function getTransactionTypeInfoJS(type) {
            switch(type) {
                case 'deposit': return { label: 'Nạp XU', class: 'bg-green-100 text-green-800' };
                case 'withdraw': return { label: 'Rút XU', class: 'bg-red-100 text-red-800' };
                case 'payment': return { label: 'Thanh toán', class: 'bg-red-100 text-red-800' };
                case 'system_gift': return { label: 'Tặng HT', class: 'bg-blue-100 text-blue-800' };
                default: return { label: type, class: 'bg-gray-100 text-gray-800' };
            }
        }

        function getStatusClass(status) {
            switch(status) {
                case 'completed': return 'bg-green-100 text-green-800';
                case 'pending': return 'bg-yellow-100 text-yellow-800';
                case 'failed': return 'bg-red-100 text-red-800';
                default: return 'bg-gray-100 text-gray-800';
            }
        }

        function showLoading() {
            document.getElementById('loadingOverlay').classList.remove('hidden');
        }

        function hideLoading() {
            document.getElementById('loadingOverlay').classList.add('hidden');
        }

        function showError(message) {
            // Simple error display - you can enhance this
            console.error(message);
        }

        function refreshTransactions() {
            if (currentFilter === 'all') {
                window.location.reload();
            } else {
                filterTransactions(currentFilter, currentPage);
            }
        }

        function exportTransactions() {
            window.open('?action=export_transactions', '_blank');
        }

        // Auto refresh every 2 minutes
        setInterval(() => {
            if (!isLoading) {
                refreshTransactions();
            }
        }, 120000);

        console.log('✅ Wallet page loaded with enhanced pagination!');
        console.log('Current balance from wallets table: <?= number_format($currentBalance) ?> XU');
        console.log('Total transactions from wallet_transactions: <?= $walletStats["transaction_count"] ?>');
        console.log('Current page: <?= $page ?> / <?= $totalPages ?>');
    </script>
</body>
</html>

<?php
// Helper function for transaction type info
function getTransactionTypeInfo($type) {
    switch($type) {
        case 'deposit':
            return ['label' => 'Nạp XU', 'class' => 'bg-green-100 text-green-800'];
        case 'withdraw':
            return ['label' => 'Rút XU', 'class' => 'bg-red-100 text-red-800'];
        case 'payment':
            return ['label' => 'Thanh toán', 'class' => 'bg-red-100 text-red-800'];
        case 'system_gift':
            return ['label' => 'Tặng HT', 'class' => 'bg-blue-100 text-blue-800'];
        default:
            return ['label' => ucfirst($type), 'class' => 'bg-gray-100 text-gray-800'];
    }
}
?>