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
Middleware::logActivity('admin_users_management');

$userData = Auth::getUser();
$walletManager = new WalletManager($db);

// Handle AJAX requests
if (isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    switch ($_POST['action']) {
        case 'get_users':
            $page = intval($_POST['page'] ?? 1);
            $perPage = intval($_POST['per_page'] ?? 10);
            $search = $_POST['search'] ?? '';
            $offset = ($page - 1) * $perPage;
            
            $whereClause = "WHERE u.role = 'user'";
            $params = [];
            
            if (!empty($search)) {
                $whereClause .= " AND (u.email LIKE ? OR u.full_name LIKE ? OR u.id LIKE ?)";
                $searchTerm = '%' . $search . '%';
                $params = [$searchTerm, $searchTerm, $searchTerm];
            }
            
            // Get total count
            $totalQuery = "
                SELECT COUNT(*) as total
                FROM users u
                $whereClause
            ";
            $totalResult = $db->query($totalQuery, $params);
            $total = $totalResult[0]['total'] ?? 0;
            $totalPages = ceil($total / $perPage);
            
            // Get users for current page with complete data
            $users = $db->query("
                SELECT 
                    u.id,
                    u.email,
                    u.full_name,
                    u.phone,
                    u.role,
                    u.status,
                    u.created_at,
                    w.balance,
                    s.plan_name,
                    s.end_date as subscription_end,
                    s.status as subscription_status,
                    COALESCE(
                        (SELECT SUM(wt.amount_vnd) 
                         FROM wallet_transactions wt 
                         WHERE wt.user_id = u.id 
                         AND wt.type = 'deposit' 
                         AND wt.status = 'completed'
                         AND DATE(wt.created_at) >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
                        ), 0
                    ) as total_deposited_month,
                    COALESCE(
                        (SELECT SUM(wt.amount_vnd) 
                         FROM wallet_transactions wt 
                         WHERE wt.user_id = u.id 
                         AND wt.type = 'deposit' 
                         AND wt.status = 'completed'
                        ), 0
                    ) as total_deposited_all
                FROM users u
                LEFT JOIN wallets w ON u.id = w.user_id
                LEFT JOIN subscriptions s ON u.id = s.user_id AND s.status = 'active' AND s.end_date > NOW()
                $whereClause
                ORDER BY u.created_at DESC
                LIMIT ? OFFSET ?
            ", array_merge($params, [$perPage, $offset]));
            
            echo json_encode([
                'success' => true,
                'users' => $users,
                'pagination' => [
                    'current_page' => $page,
                    'total_pages' => $totalPages,
                    'total_items' => $total,
                    'per_page' => $perPage
                ]
            ]);
            exit;
            
        case 'add_xu':
            $userId = $_POST['user_id'] ?? '';
            $amount = intval($_POST['amount'] ?? 0);
            $reason = $_POST['reason'] ?? 'Admin thêm XU';
            
            if ($userId && $amount > 0) {
                try {
                    $db->beginTransaction();
                    
                    // Get current balance
                    $wallet = $db->query("SELECT balance FROM wallets WHERE user_id = ?", [$userId]);
                    $currentBalance = $wallet[0]['balance'] ?? 0;
                    $newBalance = $currentBalance + $amount;
                    
                    // Update wallet balance
                    $db->query(
                        "UPDATE wallets SET balance = ?, updated_at = NOW() WHERE user_id = ?",
                        [$newBalance, $userId]
                    );
                    
                    // Create transaction record (XU only - no VND amount)
                    $transactionId = 'ADMIN_ADD_' . time() . '_' . uniqid();
                    $db->query(
                        "INSERT INTO wallet_transactions (
                            transaction_id, user_id, type, amount_vnd, amount_xu, 
                            exchange_rate, balance_before, balance_after, 
                            description, status, created_at
                        ) VALUES (?, ?, 'deposit', NULL, ?, 0, ?, ?, ?, 'completed', NOW())",
                        [
                            $transactionId,
                            $userId,
                            $amount,
                            $currentBalance,
                            $newBalance,
                            $reason
                        ]
                    );
                    
                    $db->commit();
                    echo json_encode(['success' => true, 'message' => 'Thêm XU thành công']);
                    
                } catch (Exception $e) {
                    $db->rollback();
                    echo json_encode(['success' => false, 'message' => 'Lỗi: ' . $e->getMessage()]);
                }
            } else {
                echo json_encode(['success' => false, 'message' => 'Dữ liệu không hợp lệ']);
            }
            exit;
            
        case 'subtract_xu':
            $userId = $_POST['user_id'] ?? '';
            $amount = intval($_POST['amount'] ?? 0);
            $reason = $_POST['reason'] ?? 'Admin trừ XU';
            
            if ($userId && $amount > 0) {
                try {
                    $db->beginTransaction();
                    
                    // Get current balance
                    $wallet = $db->query("SELECT balance FROM wallets WHERE user_id = ?", [$userId]);
                    if (empty($wallet)) {
                        throw new Exception('Không tìm thấy ví người dùng');
                    }
                    
                    $currentBalance = $wallet[0]['balance'];
                    if ($currentBalance < $amount) {
                        throw new Exception('Số dư không đủ');
                    }
                    
                    $newBalance = $currentBalance - $amount;
                    
                    // Update wallet balance
                    $db->query(
                        "UPDATE wallets SET balance = ?, updated_at = NOW() WHERE user_id = ?",
                        [$newBalance, $userId]
                    );
                    
                    // Create transaction record (XU only - no VND amount)
                    $transactionId = 'ADMIN_SUB_' . time() . '_' . uniqid();
                    $db->query(
                        "INSERT INTO wallet_transactions (
                            transaction_id, user_id, type, amount_vnd, amount_xu, 
                            exchange_rate, balance_before, balance_after, 
                            description, status, created_at
                        ) VALUES (?, ?, 'withdraw', NULL, ?, 0, ?, ?, ?, 'completed', NOW())",
                        [
                            $transactionId,
                            $userId,
                            $amount,
                            $currentBalance,
                            $newBalance,
                            $reason
                        ]
                    );
                    
                    $db->commit();
                    echo json_encode(['success' => true, 'message' => 'Trừ XU thành công']);
                    
                } catch (Exception $e) {
                    $db->rollback();
                    echo json_encode(['success' => false, 'message' => 'Lỗi: ' . $e->getMessage()]);
                }
            } else {
                echo json_encode(['success' => false, 'message' => 'Dữ liệu không hợp lệ']);
            }
            exit;
            
        case 'update_user':
            $userId = $_POST['user_id'] ?? '';
            $fullName = $_POST['full_name'] ?? '';
            $phone = $_POST['phone'] ?? '';
            $status = $_POST['status'] ?? 'active';
            $role = $_POST['role'] ?? 'user';
            
            if ($userId && $fullName) {
                try {
                    $db->query(
                        "UPDATE users SET full_name = ?, phone = ?, status = ?, role = ?, updated_at = NOW() WHERE id = ?",
                        [$fullName, $phone, $status, $role, $userId]
                    );
                    echo json_encode(['success' => true, 'message' => 'Cập nhật thành công']);
                } catch (Exception $e) {
                    echo json_encode(['success' => false, 'message' => 'Lỗi: ' . $e->getMessage()]);
                }
            } else {
                echo json_encode(['success' => false, 'message' => 'Dữ liệu không hợp lệ']);
            }
            exit;
            
        case 'get_user_detail':
            $userId = $_POST['user_id'] ?? '';
            if ($userId) {
                $user = $db->query("
                    SELECT 
                        u.*,
                        w.balance,
                        s.plan_name,
                        s.end_date as subscription_end,
                        COALESCE(
                            (SELECT SUM(wt.amount_vnd) 
                             FROM wallet_transactions wt 
                             WHERE wt.user_id = u.id 
                             AND wt.type = 'deposit' 
                             AND wt.status = 'completed'
                            ), 0
                        ) as total_deposited
                    FROM users u
                    LEFT JOIN wallets w ON u.id = w.user_id
                    LEFT JOIN subscriptions s ON u.id = s.user_id AND s.status = 'active'
                    WHERE u.id = ?
                ", [$userId]);
                
                if (!empty($user)) {
                    echo json_encode(['success' => true, 'user' => $user[0]]);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Không tìm thấy user']);
                }
            } else {
                echo json_encode(['success' => false, 'message' => 'User ID không hợp lệ']);
            }
            exit;
    }
}

// Get statistics - cũng sửa để tính từ wallet_transactions
$stats = [
    'total_users' => 0,
    'active_users' => 0,
    'banned_users' => 0,
    'total_balance' => 0,
    'total_deposited' => 0
];

try {
    $userStats = $db->query("
        SELECT 
            COUNT(*) as total_users,
            SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_users,
            SUM(CASE WHEN status = 'banned' THEN 1 ELSE 0 END) as banned_users
        FROM users
        WHERE role = 'user'
    ");
    
    if (!empty($userStats)) {
        $stats['total_users'] = $userStats[0]['total_users'] ?? 0;
        $stats['active_users'] = $userStats[0]['active_users'] ?? 0;
        $stats['banned_users'] = $userStats[0]['banned_users'] ?? 0;
    }
    
    $balanceStats = $db->query("SELECT SUM(balance) as total_balance FROM wallets");
    if (!empty($balanceStats)) {
        $stats['total_balance'] = $balanceStats[0]['total_balance'] ?? 0;
    }
    
    // Tính tổng nạp từ wallet_transactions
    $depositStats = $db->query("
        SELECT SUM(amount_vnd) as total_deposited 
        FROM wallet_transactions 
        WHERE type = 'deposit' AND status = 'completed'
    ");
    if (!empty($depositStats)) {
        $stats['total_deposited'] = $depositStats[0]['total_deposited'] ?? 0;
    }
    
} catch (Exception $e) {
    error_log('Stats error: ' . $e->getMessage());
}

$pageTitle = "Danh Sách Thành Viên - Admin - AIboost.vn";
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
            width: 20px;
            height: 20px;
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        /* Table styles */
        .users-table {
            transition: opacity 0.3s ease;
        }
        
        .users-table.loading {
            opacity: 0.5;
        }
        
        /* Fixed column widths */
        .col-stt { width: 50px; min-width: 50px; max-width: 50px; }
        .col-username { width: 200px; min-width: 180px; max-width: 220px; }
        .col-role { width: 80px; min-width: 80px; max-width: 80px; }
        .col-name { width: 150px; min-width: 130px; max-width: 170px; }
        .col-status { width: 100px; min-width: 100px; max-width: 100px; }
        .col-balance { width: 120px; min-width: 120px; max-width: 120px; }
        .col-plan { width: 120px; min-width: 120px; max-width: 120px; }
        .col-month { width: 110px; min-width: 110px; max-width: 110px; }
        .col-total { width: 110px; min-width: 110px; max-width: 110px; }
        .col-created { width: 120px; min-width: 120px; max-width: 120px; }
        .col-actions { width: 120px; min-width: 120px; max-width: 120px; }
        
        .status-badge {
            padding: 4px 8px;
            border-radius: 9999px;
            font-size: 11px;
            font-weight: 600;
        }
        
        .status-active { background: #dcfce7; color: #166534; }
        .status-inactive { background: #f3f4f6; color: #6b7280; }
        .status-banned { background: #fee2e2; color: #dc2626; }
        
        .role-badge {
            padding: 2px 6px;
            border-radius: 4px;
            font-size: 10px;
            font-weight: 500;
        }
        
        .role-user { background: #dbeafe; color: #1e40af; }
        .role-admin { background: #fef3c7; color: #d97706; }
        
        .plan-badge {
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 10px;
            font-weight: 600;
            text-align: center;
        }
        
        .plan-free { background: #f3f4f6; color: #6b7280; }
        .plan-standard { background: #dbeafe; color: #1d4ed8; }
        .plan-pro { background: #dcfce7; color: #166534; }
        .plan-ultra { background: #fef3c7; color: #d97706; }
        
        .action-btn {
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 11px;
            cursor: pointer;
            transition: all 0.2s;
            border: none;
        }
        
        .action-btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .btn-add-xu { background: #10b981; color: white; }
        .btn-subtract-xu { background: #f59e0b; color: white; }
        .btn-edit { background: #6366f1; color: white; }
        
        /* Modal styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
        }
        
        .modal-content {
            background: white;
            margin: 10% auto;
            padding: 0;
            border-radius: 8px;
            width: 90%;
            max-width: 500px;
            animation: slideIn 0.3s ease;
        }
        
        @keyframes slideIn {
            from { opacity: 0; transform: translateY(-30px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        /* Pagination */
        .pagination-btn {
            padding: 8px 12px;
            border: 1px solid #e5e7eb;
            background: white;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .pagination-btn:hover {
            background: #f9fafb;
        }
        
        .pagination-btn.active {
            background: #3b82f6;
            color: white;
            border-color: #3b82f6;
        }
        
        .pagination-btn.disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        
        /* Responsive adjustments */
        @media (max-width: 1024px) {
            .col-username { width: 160px; min-width: 140px; max-width: 180px; }
            .col-name { width: 120px; min-width: 100px; max-width: 140px; }
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
                <h1 class="text-3xl font-bold text-gray-900">👥 Danh Sách Thành Viên</h1>
                <p class="text-gray-600 mt-2">Quản lý thành viên và ví XU</p>
            </div>
            
            <!-- Stats Cards -->
            <div class="grid grid-cols-1 md:grid-cols-5 gap-4 mb-8">
                <div class="bg-white rounded-xl shadow-sm border p-6">
                    <div class="flex items-center">
                        <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center mr-4">
                            <i class="fas fa-users text-blue-600 text-xl"></i>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600">Tổng thành viên</p>
                            <p class="text-2xl font-bold text-gray-900"><?= number_format($stats['total_users']) ?></p>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white rounded-xl shadow-sm border p-6">
                    <div class="flex items-center">
                        <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center mr-4">
                            <i class="fas fa-user-check text-green-600 text-xl"></i>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600">Đang hoạt động</p>
                            <p class="text-2xl font-bold text-green-600"><?= number_format($stats['active_users']) ?></p>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white rounded-xl shadow-sm border p-6">
                    <div class="flex items-center">
                        <div class="w-12 h-12 bg-red-100 rounded-lg flex items-center justify-center mr-4">
                            <i class="fas fa-user-times text-red-600 text-xl"></i>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600">Bị khóa</p>
                            <p class="text-2xl font-bold text-red-600"><?= number_format($stats['banned_users']) ?></p>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white rounded-xl shadow-sm border p-6">
                    <div class="flex items-center">
                        <div class="w-12 h-12 bg-yellow-100 rounded-lg flex items-center justify-center mr-4">
                            <i class="fas fa-coins text-yellow-600 text-xl"></i>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600">Tổng XU</p>
                            <p class="text-2xl font-bold text-yellow-600"><?= number_format($stats['total_balance']) ?></p>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white rounded-xl shadow-sm border p-6">
                    <div class="flex items-center">
                        <div class="w-12 h-12 bg-purple-100 rounded-lg flex items-center justify-center mr-4">
                            <i class="fas fa-money-bill-wave text-purple-600 text-xl"></i>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600">Tổng nạp</p>
                            <p class="text-xl font-bold text-purple-600"><?= number_format($stats['total_deposited']) ?>đ</p>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Users Management -->
            <div class="bg-white rounded-xl shadow-sm border">
                <div class="p-6">
                    <div class="flex items-center justify-between mb-6">
                        <h2 class="text-xl font-bold">Danh Sách Thành Viên</h2>
                        
                        <!-- Search & Controls -->
                        <div class="flex items-center gap-4">
                            <!-- Search -->
                            <div class="relative">
                                <input type="text" id="searchInput" placeholder="Tìm kiếm email, tên, ID..." 
                                       class="pl-10 pr-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 w-64">
                                <i class="fas fa-search absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                            </div>
                            
                            <!-- Add User Button -->
                            <button class="bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 transition-colors">
                                <i class="fas fa-plus mr-2"></i>Thêm người dùng mới
                            </button>
                            
                            <!-- Loading Indicator -->
                            <div id="loadingIndicator" class="hidden flex items-center">
                                <div class="loading-spinner mr-2"></div>
                                <span class="text-sm text-gray-600">Đang tải...</span>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Users Table with Fixed Layout -->
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 users-table table-fixed" id="usersTable" style="width: 1400px;">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="col-stt px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase">STT</th>
                                    <th class="col-username px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Username</th>
                                    <th class="col-role px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase">Chức vụ</th>
                                    <th class="col-name px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Tên</th>
                                    <th class="col-status px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase">Trạng thái</th>
                                    <th class="col-balance px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase">Số Dư</th>
                                    <th class="col-plan px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Gói cước</th>
                                    <th class="col-month px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase">Nạp tháng</th>
                                    <th class="col-total px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase">Tổng nạp</th>
                                    <th class="col-created px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Tạo lúc</th>
                                    <th class="col-actions px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Hành Động</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200" id="usersBody">
                                <!-- Will be populated by JavaScript -->
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- Pagination -->
                    <div class="flex items-center justify-between mt-6 pt-4 border-t border-gray-200">
                        <!-- Page Info -->
                        <div class="flex items-center text-sm text-gray-600">
                            <span>Xem</span>
                            <select id="itemsPerPage" class="mx-2 border rounded px-2 py-1" onchange="changeItemsPerPage()">
                                <option value="10" selected>10</option>
                                <option value="20">20</option>
                                <option value="50">50</option>
                                <option value="100">100</option>
                            </select>
                            <span>mục</span>
                            <span id="pageInfo" class="ml-4">
                                <!-- Will be populated by JavaScript -->
                            </span>
                        </div>
                        
                        <!-- Pagination Buttons -->
                        <div class="flex items-center" id="paginationContainer">
                            <!-- Will be populated by JavaScript -->
                        </div>
                        
                        <!-- Total Info -->
                        <div class="text-sm text-gray-600">
                            <span>Tìm: <strong id="totalCount">0</strong></span>
                        </div>
                    </div>
                </div>
            </div>
            
        </div>
    </div>
    
    <!-- Modals -->
    
    <!-- Add/Subtract XU Modal -->
    <div id="xuModal" class="modal">
        <div class="modal-content">
            <div class="bg-white rounded-lg">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h3 class="text-lg font-semibold" id="xuModalTitle">Thêm/Trừ XU</h3>
                </div>
                <div class="p-6">
                    <form id="xuForm">
                        <input type="hidden" id="xuUserId">
                        <input type="hidden" id="xuAction">
                        
                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Số lượng XU:</label>
                            <input type="number" id="xuAmount" class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" min="1" required>
                            <p class="text-xs text-gray-500 mt-1">💡 Chỉ cộng/trừ XU thuần túy, không ảnh hưởng đến số tiền VND</p>
                        </div>
                        
                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Lý do:</label>
                            <textarea id="xuReason" class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" rows="3" required></textarea>
                        </div>
                        
                        <div class="flex justify-end gap-3">
                            <button type="button" onclick="closeXuModal()" class="px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-50">
                                Hủy
                            </button>
                            <button type="submit" id="xuSubmitBtn" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                                Xác nhận
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Edit User Modal -->
    <div id="editUserModal" class="modal">
        <div class="modal-content">
            <div class="bg-white rounded-lg">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h3 class="text-lg font-semibold">Sửa Thành Viên</h3>
                </div>
                <div class="p-6">
                    <form id="editUserForm">
                        <input type="hidden" id="editUserId">
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Họ tên:</label>
                                <input type="text" id="editFullName" class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Số điện thoại:</label>
                                <input type="tel" id="editPhone" class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                            </div>
                        </div>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Trạng thái:</label>
                                <select id="editStatus" class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                                    <option value="active">Hoạt động</option>
                                    <option value="inactive">Tạm khóa</option>
                                    <option value="banned">Cấm vĩnh viễn</option>
                                </select>
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Quyền hạn:</label>
                                <select id="editRole" class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                                    <option value="user">Thành viên</option>
                                    <option value="admin">Quản trị viên</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="flex justify-end gap-3 mt-6">
                            <button type="button" onclick="closeEditUserModal()" class="px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-50">
                                Hủy
                            </button>
                            <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                                Cập nhật
                            </button>
                        </div>
                    </form>
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
    let currentSearch = '';
    
    // Function to format status
    function formatStatus(status) {
        const statusMap = {
            'active': '<span class="status-badge status-active">Bình thường</span>',
            'inactive': '<span class="status-badge status-inactive">Tạm khóa</span>',
            'banned': '<span class="status-badge status-banned">Cấm vĩnh viễn</span>'
        };
        return statusMap[status] || status;
    }
    
    // Function to format role
    function formatRole(role) {
        const roleMap = {
            'user': '<span class="role-badge role-user">Thành Viên</span>',
            'admin': '<span class="role-badge role-admin">Admin</span>'
        };
        return roleMap[role] || role;
    }
    
    // Function to format plan
    function formatPlan(planName) {
        if (!planName) return '<span class="plan-badge plan-free">Free</span>';
        
        const planMap = {
            'Free': '<span class="plan-badge plan-free">Free</span>',
            'Standard': '<span class="plan-badge plan-standard">Standard</span>',
            'Pro': '<span class="plan-badge plan-pro">Pro</span>',
            'Ultra': '<span class="plan-badge plan-ultra">Ultra</span>'
        };
        return planMap[planName] || `<span class="plan-badge plan-free">${planName}</span>`;
    }
    
    // Function to truncate text
    function truncateText(text, maxLength = 15) {
        if (!text) return '-';
        return text.length > maxLength ? text.substring(0, maxLength) + '...' : text;
    }
    
    // Function to load users
    function loadUsers(page = 1, search = '') {
        showLoading(true);
        currentSearch = search;
        
        fetch('', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: `action=get_users&page=${page}&per_page=${itemsPerPage}&search=${encodeURIComponent(search)}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                currentPage = data.pagination.current_page;
                totalPages = data.pagination.total_pages;
                totalItems = data.pagination.total_items;
                
                renderUsers(data.users);
                renderPagination();
                updatePageInfo();
            } else {
                console.error('Error loading users:', data.message);
            }
        })
        .catch(error => {
            console.error('Network error:', error);
        })
        .finally(() => {
            showLoading(false);
        });
    }
    
    // Function to render users table
    function renderUsers(users) {
        const tbody = document.getElementById('usersBody');
        
        if (users.length === 0) {
            tbody.innerHTML = `
                <tr>
                    <td colspan="11" class="px-4 py-8 text-center text-gray-500">
                        <div class="flex flex-col items-center">
                            <i class="fas fa-users text-4xl mb-2 text-gray-300"></i>
                            <p>Không có thành viên nào</p>
                        </div>
                    </td>
                </tr>
            `;
            return;
        }
        
        tbody.innerHTML = users.map((user, index) => `
            <tr class="hover:bg-gray-50 transition-colors duration-200">
                <td class="col-stt px-3 py-3 text-sm text-center">${((currentPage - 1) * itemsPerPage) + index + 1}</td>
                <td class="col-username px-4 py-3 text-sm">
                    <div class="flex flex-col">
                        <span class="font-medium text-blue-600 text-xs" title="${user.email}">${truncateText(user.email, 20)}</span>
                        <span class="text-xs text-gray-500">${user.id.substring(0, 8)}...</span>
                    </div>
                </td>
                <td class="col-role px-3 py-3">${formatRole(user.role)}</td>
                <td class="col-name px-4 py-3 text-sm font-medium" title="${user.full_name || '-'}">${truncateText(user.full_name)}</td>
                <td class="col-status px-3 py-3">${formatStatus(user.status)}</td>
                <td class="col-balance px-3 py-3 text-sm">
                    <span class="font-bold text-green-600">${new Intl.NumberFormat('vi-VN').format(user.balance || 0)}</span>
                </td>
                <td class="col-plan px-4 py-3 text-sm text-center">
                    ${formatPlan(user.plan_name)}
                </td>
                <td class="col-month px-3 py-3 text-sm">
                    <span class="text-blue-600 font-medium">${new Intl.NumberFormat('vi-VN').format(user.total_deposited_month || 0)}đ</span>
                </td>
                <td class="col-total px-3 py-3 text-sm">
                    <span class="text-purple-600 font-medium">${new Intl.NumberFormat('vi-VN').format(user.total_deposited_all || 0)}đ</span>
                </td>
                <td class="col-created px-4 py-3 text-sm">
                    <div class="flex flex-col">
                        <span class="text-green-600 font-medium text-xs">${new Date(user.created_at).toLocaleDateString('vi-VN')}</span>
                        <span class="text-xs text-gray-500">${new Date(user.created_at).toLocaleTimeString('vi-VN', {hour: '2-digit', minute: '2-digit'})}</span>
                    </div>
                </td>
                <td class="col-actions px-4 py-3">
                    <div class="flex items-center gap-1">
                        <button onclick="showXuModal('${user.id}', 'add')" 
                                class="action-btn btn-add-xu" title="Thêm XU">
                            <i class="fas fa-plus"></i>
                        </button>
                        <button onclick="showXuModal('${user.id}', 'subtract')" 
                                class="action-btn btn-subtract-xu" title="Trừ XU">
                            <i class="fas fa-minus"></i>
                        </button>
                        <button onclick="showEditUserModal('${user.id}')" 
                                class="action-btn btn-edit" title="Sửa thành viên">
                            <i class="fas fa-edit"></i>
                        </button>
                    </div>
                </td>
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
                    class="pagination-btn ${currentPage <= 1 ? 'disabled' : ''}"
                    ${currentPage <= 1 ? 'disabled' : ''}>
                Trước
            </button>
        `;
        
        // Page numbers
        const startPage = Math.max(1, currentPage - 2);
        const endPage = Math.min(totalPages, currentPage + 2);
        
        if (startPage > 1) {
            html += `<button onclick="goToPage(1)" class="pagination-btn">1</button>`;
            if (startPage > 2) {
                html += '<span class="px-2 text-gray-500">...</span>';
            }
        }
        
        for (let i = startPage; i <= endPage; i++) {
            html += `
                <button onclick="goToPage(${i})" 
                        class="pagination-btn ${i === currentPage ? 'active' : ''}">
                    ${i}
                </button>
            `;
        }
        
        if (endPage < totalPages) {
            if (endPage < totalPages - 1) {
                html += '<span class="px-2 text-gray-500">...</span>';
            }
            html += `<button onclick="goToPage(${totalPages})" class="pagination-btn">${totalPages}</button>`;
        }
        
        // Next button
        html += `
            <button onclick="goToPage(${currentPage + 1})" 
                    class="pagination-btn ${currentPage >= totalPages ? 'disabled' : ''}"
                    ${currentPage >= totalPages ? 'disabled' : ''}>
                Sau
            </button>
        `;
        
        container.innerHTML = html;
    }
    
    // Function to update page info
    function updatePageInfo() {
        const startItem = ((currentPage - 1) * itemsPerPage) + 1;
        const endItem = Math.min(currentPage * itemsPerPage, totalItems);
        
        document.getElementById('pageInfo').innerHTML = `
            Đang xem ${startItem} đến ${endItem} trong tổng số ${new Intl.NumberFormat('vi-VN').format(totalItems)} mục
        `;
        
        document.getElementById('totalCount').textContent = new Intl.NumberFormat('vi-VN').format(totalItems);
    }
    
    // Function to show/hide loading
    function showLoading(show) {
        const loadingIndicator = document.getElementById('loadingIndicator');
        const usersTable = document.getElementById('usersTable');
        
        if (show) {
            loadingIndicator.classList.remove('hidden');
            usersTable.classList.add('loading');
        } else {
            loadingIndicator.classList.add('hidden');
            usersTable.classList.remove('loading');
        }
    }
    
    // Function to go to specific page
    function goToPage(page) {
        if (page < 1 || page > totalPages) return;
        loadUsers(page, currentSearch);
    }
    
    // Function to change items per page
    function changeItemsPerPage() {
        itemsPerPage = parseInt(document.getElementById('itemsPerPage').value);
        currentPage = 1;
        loadUsers(1, currentSearch);
    }
    
    // XU Modal functions
    function showXuModal(userId, action) {
        document.getElementById('xuUserId').value = userId;
        document.getElementById('xuAction').value = action;
        document.getElementById('xuAmount').value = '';
        document.getElementById('xuReason').value = action === 'add' ? 'Admin thêm XU' : 'Admin trừ XU';
        
        const title = action === 'add' ? 'Thêm XU cho thành viên' : 'Trừ XU khỏi tài khoản';
        document.getElementById('xuModalTitle').textContent = title;
        
        const submitBtn = document.getElementById('xuSubmitBtn');
        submitBtn.textContent = action === 'add' ? 'Thêm XU' : 'Trừ XU';
        submitBtn.className = `px-4 py-2 rounded-lg text-white ${action === 'add' ? 'bg-green-600 hover:bg-green-700' : 'bg-orange-600 hover:bg-orange-700'}`;
        
        document.getElementById('xuModal').style.display = 'block';
    }
    
    function closeXuModal() {
        document.getElementById('xuModal').style.display = 'none';
    }
    
    // Edit User Modal functions
    function showEditUserModal(userId) {
        // Load user details first
        fetch('', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: `action=get_user_detail&user_id=${userId}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const user = data.user;
                document.getElementById('editUserId').value = user.id;
                document.getElementById('editFullName').value = user.full_name || '';
                document.getElementById('editPhone').value = user.phone || '';
                document.getElementById('editStatus').value = user.status;
                document.getElementById('editRole').value = user.role;
                
                document.getElementById('editUserModal').style.display = 'block';
            } else {
                alert('Lỗi: ' + data.message);
            }
        });
    }
    
    function closeEditUserModal() {
        document.getElementById('editUserModal').style.display = 'none';
    }
    
    // Form submissions
    document.getElementById('xuForm').addEventListener('submit', function(e) {
        e.preventDefault();
        
        const userId = document.getElementById('xuUserId').value;
        const action = document.getElementById('xuAction').value;
        const amount = document.getElementById('xuAmount').value;
        const reason = document.getElementById('xuReason').value;
        
        const actionType = action === 'add' ? 'add_xu' : 'subtract_xu';
        
        fetch('', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: `action=${actionType}&user_id=${userId}&amount=${amount}&reason=${encodeURIComponent(reason)}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Thành công!');
                closeXuModal();
                loadUsers(currentPage, currentSearch);
            } else {
                alert('Lỗi: ' + data.message);
            }
        });
    });
    
    document.getElementById('editUserForm').addEventListener('submit', function(e) {
        e.preventDefault();
        
        const formData = new FormData();
        formData.append('action', 'update_user');
        formData.append('user_id', document.getElementById('editUserId').value);
        formData.append('full_name', document.getElementById('editFullName').value);
        formData.append('phone', document.getElementById('editPhone').value);
        formData.append('status', document.getElementById('editStatus').value);
        formData.append('role', document.getElementById('editRole').value);
        
        fetch('', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Cập nhật thành công!');
                closeEditUserModal();
                loadUsers(currentPage, currentSearch);
            } else {
                alert('Lỗi: ' + data.message);
            }
        });
    });
    
    // Search functionality
    let searchTimeout;
    document.getElementById('searchInput').addEventListener('input', function(e) {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => {
            currentPage = 1;
            loadUsers(1, e.target.value);
        }, 500);
    });
    
    // Close modals when clicking outside
    window.addEventListener('click', function(e) {
        const xuModal = document.getElementById('xuModal');
        const editModal = document.getElementById('editUserModal');
        
        if (e.target === xuModal) {
            closeXuModal();
        }
        if (e.target === editModal) {
            closeEditUserModal();
        }
    });
    
    // Initialize on page load
    document.addEventListener('DOMContentLoaded', function() {
        updatePageTitle('Danh sách thành viên');
        loadUsers(1);
    });
    
    console.log('✅ Users management page with pure XU add/subtract functionality loaded');
    </script>
</body>
</html>