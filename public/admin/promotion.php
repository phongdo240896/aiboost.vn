<?php
require_once __DIR__ . '/../../app/config.php';
require_once __DIR__ . '/../../app/db.php';
require_once __DIR__ . '/../../app/auth.php';
require_once __DIR__ . '/../../app/middleware.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

Middleware::requireAdmin();
Middleware::logActivity('admin_promotion_management');

$userData = Auth::getUser();

// Handle AJAX requests
if (isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    switch ($_POST['action']) {
        case 'get_promotions':
            $page = intval($_POST['page'] ?? 1);
            $perPage = intval($_POST['per_page'] ?? 10);
            $search = $_POST['search'] ?? '';
            $offset = ($page - 1) * $perPage;
            
            $whereClause = "WHERE 1=1";
            $params = [];
            
            if (!empty($search)) {
                $whereClause .= " AND (name LIKE ? OR description LIKE ?)";
                $searchTerm = '%' . $search . '%';
                $params = [$searchTerm, $searchTerm];
            }
            
            // Get total count
            $totalQuery = "SELECT COUNT(*) as total FROM promotions $whereClause";
            $totalResult = $db->query($totalQuery, $params);
            $total = $totalResult[0]['total'] ?? 0;
            $totalPages = ceil($total / $perPage);
            
            // Get promotions
            $promotions = $db->query("
                SELECT 
                    p.*,
                    COALESCE(
                        (SELECT COUNT(*) FROM promotion_usage pu WHERE pu.promotion_id = p.id), 0
                    ) as usage_count,
                    COALESCE(
                        (SELECT SUM(pu.bonus_xu) FROM promotion_usage pu WHERE pu.promotion_id = p.id), 0
                    ) as total_bonus_given
                FROM promotions p
                $whereClause
                ORDER BY p.created_at DESC
                LIMIT ? OFFSET ?
            ", array_merge($params, [$perPage, $offset]));
            
            echo json_encode([
                'success' => true,
                'promotions' => $promotions,
                'pagination' => [
                    'current_page' => $page,
                    'total_pages' => $totalPages,
                    'total_items' => $total,
                    'per_page' => $perPage
                ]
            ]);
            exit;
            
        case 'create_promotion':
            $name = $_POST['name'] ?? '';
            $type = $_POST['type'] ?? 'percentage';
            $value = floatval($_POST['value'] ?? 0);
            $minDeposit = floatval($_POST['min_deposit'] ?? 0);
            $maxBonus = !empty($_POST['max_bonus']) ? floatval($_POST['max_bonus']) : null;
            $startDate = $_POST['start_date'] ?? '';
            $endDate = $_POST['end_date'] ?? '';
            $description = $_POST['description'] ?? '';
            $usageLimitPerUser = !empty($_POST['usage_limit_per_user']) ? intval($_POST['usage_limit_per_user']) : null;
            $totalUsageLimit = !empty($_POST['total_usage_limit']) ? intval($_POST['total_usage_limit']) : null;
            
            if (empty($name) || empty($startDate) || empty($endDate) || $value <= 0) {
                echo json_encode(['success' => false, 'message' => 'Vui lòng điền đầy đủ thông tin']);
                exit;
            }
            
            try {
                $db->query("
                    INSERT INTO promotions (name, type, value, min_deposit, max_bonus, start_date, end_date, description, status, usage_limit_per_user, total_usage_limit)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'active', ?, ?)
                ", [$name, $type, $value, $minDeposit, $maxBonus, $startDate, $endDate, $description, $usageLimitPerUser, $totalUsageLimit]);
                
                echo json_encode(['success' => true, 'message' => 'Tạo khuyến mãi thành công']);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => 'Lỗi: ' . $e->getMessage()]);
            }
            exit;
            
        case 'update_promotion':
            $id = intval($_POST['id'] ?? 0);
            $name = $_POST['name'] ?? '';
            $type = $_POST['type'] ?? 'percentage';
            $value = floatval($_POST['value'] ?? 0);
            $minDeposit = floatval($_POST['min_deposit'] ?? 0);
            $maxBonus = !empty($_POST['max_bonus']) ? floatval($_POST['max_bonus']) : null;
            $startDate = $_POST['start_date'] ?? '';
            $endDate = $_POST['end_date'] ?? '';
            $status = $_POST['status'] ?? 'active';
            $description = $_POST['description'] ?? '';
            $usageLimitPerUser = !empty($_POST['usage_limit_per_user']) ? intval($_POST['usage_limit_per_user']) : null;
            $totalUsageLimit = !empty($_POST['total_usage_limit']) ? intval($_POST['total_usage_limit']) : null;
            
            if (empty($name) || empty($startDate) || empty($endDate) || $value <= 0) {
                echo json_encode(['success' => false, 'message' => 'Vui lòng điền đầy đủ thông tin']);
                exit;
            }
            
            try {
                $db->query("
                    UPDATE promotions 
                    SET name = ?, type = ?, value = ?, min_deposit = ?, max_bonus = ?, 
                        start_date = ?, end_date = ?, status = ?, description = ?, 
                        usage_limit_per_user = ?, total_usage_limit = ?, updated_at = NOW()
                    WHERE id = ?
                ", [$name, $type, $value, $minDeposit, $maxBonus, $startDate, $endDate, $status, $description, $usageLimitPerUser, $totalUsageLimit, $id]);
                
                echo json_encode(['success' => true, 'message' => 'Cập nhật khuyến mãi thành công']);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => 'Lỗi: ' . $e->getMessage()]);
            }
            exit;
            
        case 'delete_promotion':
            $id = intval($_POST['id'] ?? 0);
            
            try {
                // Check if promotion has been used
                $usage = $db->query("SELECT COUNT(*) as count FROM promotion_usage WHERE promotion_id = ?", [$id]);
                if ($usage[0]['count'] > 0) {
                    echo json_encode(['success' => false, 'message' => 'Không thể xóa khuyến mãi đã được sử dụng']);
                    exit;
                }
                
                $db->query("DELETE FROM promotions WHERE id = ?", [$id]);
                echo json_encode(['success' => true, 'message' => 'Xóa khuyến mãi thành công']);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => 'Lỗi: ' . $e->getMessage()]);
            }
            exit;
            
        case 'get_promotion_detail':
            $id = intval($_POST['id'] ?? 0);
            
            $promotion = $db->query("SELECT * FROM promotions WHERE id = ?", [$id]);
            if (!empty($promotion)) {
                echo json_encode(['success' => true, 'promotion' => $promotion[0]]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Không tìm thấy khuyến mãi']);
            }
            exit;
            
        case 'get_promotion_usage':
            $promotionId = intval($_POST['promotion_id'] ?? 0);
            $page = intval($_POST['page'] ?? 1);
            $perPage = 10;
            $offset = ($page - 1) * $perPage;
            
            $usageList = $db->query("
                SELECT 
                    pu.*,
                    u.email,
                    u.full_name,
                    p.name as promotion_name
                FROM promotion_usage pu
                JOIN users u ON pu.user_id = u.id
                JOIN promotions p ON pu.promotion_id = p.id
                WHERE pu.promotion_id = ?
                ORDER BY pu.created_at DESC
                LIMIT ? OFFSET ?
            ", [$promotionId, $perPage, $offset]);
            
            $totalUsage = $db->query("SELECT COUNT(*) as total FROM promotion_usage WHERE promotion_id = ?", [$promotionId]);
            $total = $totalUsage[0]['total'] ?? 0;
            
            echo json_encode([
                'success' => true,
                'usage' => $usageList,
                'pagination' => [
                    'current_page' => $page,
                    'total_pages' => ceil($total / $perPage),
                    'total_items' => $total
                ]
            ]);
            exit;
    }
}

// Get statistics
$stats = [
    'total_promotions' => 0,
    'active_promotions' => 0,
    'total_usage' => 0,
    'total_bonus_given' => 0
];

try {
    $promotionStats = $db->query("
        SELECT 
            COUNT(*) as total_promotions,
            SUM(CASE WHEN status = 'active' AND start_date <= NOW() AND end_date >= NOW() THEN 1 ELSE 0 END) as active_promotions
        FROM promotions
    ");
    
    if (!empty($promotionStats)) {
        $stats['total_promotions'] = $promotionStats[0]['total_promotions'] ?? 0;
        $stats['active_promotions'] = $promotionStats[0]['active_promotions'] ?? 0;
    }
    
    $usageStats = $db->query("
        SELECT 
            COUNT(*) as total_usage,
            SUM(bonus_xu) as total_bonus_given
        FROM promotion_usage
    ");
    
    if (!empty($usageStats)) {
        $stats['total_usage'] = $usageStats[0]['total_usage'] ?? 0;
        $stats['total_bonus_given'] = $usageStats[0]['total_bonus_given'] ?? 0;
    }
    
} catch (Exception $e) {
    error_log('Promotion stats error: ' . $e->getMessage());
}

$pageTitle = "Quản Lý Khuyến Mãi - Admin - AIboost.vn";
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
            margin: 5% auto;
            padding: 0;
            border-radius: 12px;
            width: 90%;
            max-width: 800px;
            max-height: 90vh;
            overflow-y: auto;
            animation: slideIn 0.3s ease;
        }
        
        @keyframes slideIn {
            from { opacity: 0; transform: translateY(-30px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .status-badge {
            padding: 4px 8px;
            border-radius: 9999px;
            font-size: 11px;
            font-weight: 600;
        }
        
        .status-active { background: #dcfce7; color: #166534; }
        .status-inactive { background: #f3f4f6; color: #6b7280; }
        .status-expired { background: #fee2e2; color: #dc2626; }
        
        .type-badge {
            padding: 3px 8px;
            border-radius: 6px;
            font-size: 10px;
            font-weight: 600;
        }
        
        .type-percentage { background: #dbeafe; color: #1d4ed8; }
        .type-fixed_amount { background: #dcfce7; color: #166534; }
        .type-bonus_xu { background: #fef3c7; color: #d97706; }
    </style>
</head>
<body class="min-h-screen bg-gray-50">
    <?php include __DIR__ . '/partials/sidebar.php'; ?>
    <?php include __DIR__ . '/partials/header.php'; ?>
    
    <div class="lg:ml-64">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
            
            <!-- Page Header -->
            <div class="mb-8">
                <h1 class="text-3xl font-bold text-gray-900">🎁 Quản Lý Khuyến Mãi</h1>
                <p class="text-gray-600 mt-2">Tạo và quản lý các chương trình khuyến mãi</p>
            </div>
            
            <!-- Stats Cards -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
                <div class="bg-white rounded-xl shadow-sm border p-6">
                    <div class="flex items-center">
                        <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center mr-4">
                            <i class="fas fa-gift text-blue-600 text-xl"></i>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600">Tổng khuyến mãi</p>
                            <p class="text-2xl font-bold text-gray-900"><?= number_format($stats['total_promotions']) ?></p>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white rounded-xl shadow-sm border p-6">
                    <div class="flex items-center">
                        <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center mr-4">
                            <i class="fas fa-play text-green-600 text-xl"></i>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600">Đang hoạt động</p>
                            <p class="text-2xl font-bold text-green-600"><?= number_format($stats['active_promotions']) ?></p>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white rounded-xl shadow-sm border p-6">
                    <div class="flex items-center">
                        <div class="w-12 h-12 bg-purple-100 rounded-lg flex items-center justify-center mr-4">
                            <i class="fas fa-users text-purple-600 text-xl"></i>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600">Lượt sử dụng</p>
                            <p class="text-2xl font-bold text-purple-600"><?= number_format($stats['total_usage']) ?></p>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white rounded-xl shadow-sm border p-6">
                    <div class="flex items-center">
                        <div class="w-12 h-12 bg-yellow-100 rounded-lg flex items-center justify-center mr-4">
                            <i class="fas fa-coins text-yellow-600 text-xl"></i>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600">XU đã tặng</p>
                            <p class="text-2xl font-bold text-yellow-600"><?= number_format($stats['total_bonus_given']) ?></p>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Promotions Management -->
            <div class="bg-white rounded-xl shadow-sm border">
                <div class="p-6">
                    <div class="flex items-center justify-between mb-6">
                        <h2 class="text-xl font-bold">Danh Sách Khuyến Mãi</h2>
                        
                        <!-- Controls -->
                        <div class="flex items-center gap-4">
                            <!-- Search -->
                            <div class="relative">
                                <input type="text" id="searchInput" placeholder="Tìm kiếm khuyến mãi..." 
                                       class="pl-10 pr-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 w-64">
                                <i class="fas fa-search absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                            </div>
                            
                            <!-- Add Promotion Button -->
                            <button onclick="showCreateModal()" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition-colors">
                                <i class="fas fa-plus mr-2"></i>Tạo khuyến mãi
                            </button>
                            
                            <!-- Loading -->
                            <div id="loadingIndicator" class="hidden flex items-center">
                                <div class="loading-spinner mr-2"></div>
                                <span class="text-sm text-gray-600">Đang tải...</span>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Promotions Table -->
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200" id="promotionsTable">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Tên khuyến mãi</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Loại</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Giá trị</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Nạp tối thiểu</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Thời gian</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Trạng thái</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Sử dụng</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Giới hạn</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Hành động</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200" id="promotionsBody">
                                <!-- Will be populated by JavaScript -->
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- Pagination -->
                    <div class="flex items-center justify-between mt-6 pt-4 border-t border-gray-200">
                        <div class="flex items-center text-sm text-gray-600">
                            <span>Xem</span>
                            <select id="itemsPerPage" class="mx-2 border rounded px-2 py-1" onchange="changeItemsPerPage()">
                                <option value="10" selected>10</option>
                                <option value="20">20</option>
                                <option value="50">50</option>
                            </select>
                            <span>mục</span>
                            <span id="pageInfo" class="ml-4"></span>
                        </div>
                        
                        <div class="flex items-center" id="paginationContainer">
                            <!-- Pagination buttons will be here -->
                        </div>
                        
                        <div class="text-sm text-gray-600">
                            <span>Tổng: <strong id="totalCount">0</strong></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Create/Edit Promotion Modal -->
    <div id="promotionModal" class="modal">
        <div class="modal-content">
            <div class="bg-white rounded-lg">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h3 class="text-lg font-semibold" id="promotionModalTitle">Tạo Khuyến Mãi Mới</h3>
                </div>
                <div class="p-6">
                    <form id="promotionForm">
                        <input type="hidden" id="promotionId">
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <!-- Left Column -->
                            <div class="space-y-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Tên khuyến mãi *</label>
                                    <input type="text" id="promotionName" class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Loại khuyến mãi *</label>
                                    <select id="promotionType" class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" onchange="updateValueLabel()">
                                        <option value="percentage">Phần trăm (%)</option>
                                        <option value="fixed_amount">Số tiền cố định (VND)</option>
                                        <option value="bonus_xu">Tặng XU cố định</option>
                                    </select>
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2" id="valueLabel">Giá trị khuyến mãi (%) *</label>
                                    <input type="number" id="promotionValue" class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" min="0" step="0.01" required>
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Nạp tối thiểu (VND)</label>
                                    <input type="number" id="minDeposit" class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" min="0" value="0">
                                </div>
                            </div>
                            
                            <!-- Right Column -->
                            <div class="space-y-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Bonus tối đa (VND)</label>
                                    <input type="number" id="maxBonus" class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" min="0" placeholder="Không giới hạn">
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Ngày bắt đầu *</label>
                                    <input type="datetime-local" id="startDate" class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Ngày kết thúc *</label>
                                    <input type="datetime-local" id="endDate" class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                                </div>
                                
                                <div id="statusGroup" style="display: none;">
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Trạng thái</label>
                                    <select id="promotionStatus" class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                                        <option value="active">Hoạt động</option>
                                        <option value="inactive">Tạm dừng</option>
                                        <option value="expired">Hết hạn</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mt-6">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Mô tả</label>
                            <textarea id="promotionDescription" rows="3" class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="Mô tả chi tiết về khuyến mãi..."></textarea>
                        </div>
                        
                        <!-- Giới hạn sử dụng -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-6">
                            <!-- Left Column -->
                            <div class="space-y-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Giới hạn mỗi user</label>
                                    <input type="number" id="usageLimitPerUser" class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" min="0" placeholder="Không giới hạn">
                                    <p class="text-xs text-gray-500 mt-1">Để trống = không giới hạn</p>
                                </div>
                            </div>
                            
                            <!-- Right Column -->
                            <div class="space-y-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Giới hạn tổng số lần</label>
                                    <input type="number" id="totalUsageLimit" class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" min="0" placeholder="Không giới hạn">
                                    <p class="text-xs text-gray-500 mt-1">Tổng số lần được sử dụng</p>
                                </div>
                            </div>
                        </div>
                        
                        <div class="flex justify-end gap-3 mt-6 pt-4 border-t">
                            <button type="button" onclick="closePromotionModal()" class="px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-50">
                                Hủy
                            </button>
                            <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                                <span id="submitButtonText">Tạo khuyến mãi</span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Usage Details Modal -->
    <div id="usageModal" class="modal">
        <div class="modal-content">
            <div class="bg-white rounded-lg">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h3 class="text-lg font-semibold">Chi Tiết Sử Dụng Khuyến Mãi</h3>
                </div>
                <div class="p-6">
                    <div id="usageContent">
                        <!-- Usage details will be loaded here -->
                    </div>
                    <div class="flex justify-end mt-4">
                        <button onclick="closeUsageModal()" class="px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-50">
                            Đóng
                        </button>
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
    let currentSearch = '';
    let isEditMode = false;
    
    // Update value label based on promotion type
    function updateValueLabel() {
        const type = document.getElementById('promotionType').value;
        const label = document.getElementById('valueLabel');
        
        switch(type) {
            case 'percentage':
                label.textContent = 'Phần trăm khuyến mãi (%) *';
                break;
            case 'fixed_amount':
                label.textContent = 'Số tiền cố định (VND) *';
                break;
            case 'bonus_xu':
                label.textContent = 'Số XU tặng *';
                break;
        }
    }
    
    // Format status
    function formatStatus(status, startDate, endDate) {
        const now = new Date();
        const start = new Date(startDate);
        const end = new Date(endDate);
        
        let actualStatus = status;
        if (now > end) {
            actualStatus = 'expired';
        }
        
        const statusMap = {
            'active': '<span class="status-badge status-active">Hoạt động</span>',
            'inactive': '<span class="status-badge status-inactive">Tạm dừng</span>',
            'expired': '<span class="status-badge status-expired">Hết hạn</span>'
        };
        return statusMap[actualStatus] || actualStatus;
    }
    
    // Format type
    function formatType(type) {
        const typeMap = {
            'percentage': '<span class="type-badge type-percentage">Phần trăm</span>',
            'fixed_amount': '<span class="type-badge type-fixed_amount">Số tiền cố định</span>',
            'bonus_xu': '<span class="type-badge type-bonus_xu">Tặng XU</span>'
        };
        return typeMap[type] || type;
    }
    
    // Format value
    function formatValue(type, value) {
        switch(type) {
            case 'percentage':
                return value + '%';
            case 'fixed_amount':
                return new Intl.NumberFormat('vi-VN').format(value) + 'đ';
            case 'bonus_xu':
                return new Intl.NumberFormat('vi-VN').format(value) + ' XU';
            default:
                return value;
        }
    }
    
    // Load promotions
    function loadPromotions(page = 1, search = '') {
        showLoading(true);
        currentSearch = search;
        
        fetch('', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: `action=get_promotions&page=${page}&per_page=${itemsPerPage}&search=${encodeURIComponent(search)}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                currentPage = data.pagination.current_page;
                totalPages = data.pagination.total_pages;
                totalItems = data.pagination.total_items;
                
                renderPromotions(data.promotions);
                renderPagination();
                updatePageInfo();
            }
        })
        .finally(() => showLoading(false));
    }
    
    // Render promotions table
    function renderPromotions(promotions) {
        const tbody = document.getElementById('promotionsBody');
        
        if (promotions.length === 0) {
            tbody.innerHTML = `
                <tr>
                    <td colspan="9" class="px-6 py-8 text-center text-gray-500">
                        <i class="fas fa-gift text-4xl mb-2 text-gray-300"></i>
                        <p>Chưa có khuyến mãi nào</p>
                    </td>
                </tr>
            `;
            return;
        }
        
        tbody.innerHTML = promotions.map(promo => `
            <tr class="hover:bg-gray-50">
                <td class="px-6 py-4">
                    <div class="flex flex-col">
                        <span class="font-medium text-gray-900">${promo.name}</span>
                        ${promo.description ? `<span class="text-sm text-gray-500">${promo.description.substring(0, 50)}${promo.description.length > 50 ? '...' : ''}</span>` : ''}
                    </div>
                </td>
                <td class="px-6 py-4">${formatType(promo.type)}</td>
                <td class="px-6 py-4">
                    <span class="font-semibold text-blue-600">${formatValue(promo.type, promo.value)}</span>
                    ${promo.max_bonus ? `<div class="text-xs text-gray-500">Tối đa: ${new Intl.NumberFormat('vi-VN').format(promo.max_bonus)}đ</div>` : ''}
                </td>
                <td class="px-6 py-4">${new Intl.NumberFormat('vi-VN').format(promo.min_deposit)}đ</td>
                <td class="px-6 py-4">
                    <div class="text-sm">
                        <div class="text-green-600">${new Date(promo.start_date).toLocaleDateString('vi-VN')}</div>
                        <div class="text-red-600">${new Date(promo.end_date).toLocaleDateString('vi-VN')}</div>
                    </div>
                </td>
                <td class="px-6 py-4">${formatStatus(promo.status, promo.start_date, promo.end_date)}</td>
                <td class="px-6 py-4">
                    <div class="text-sm">
                        <div class="font-medium">${promo.usage_count} lượt</div>
                        <div class="text-gray-500">${new Intl.NumberFormat('vi-VN').format(promo.total_bonus_given)} XU</div>
                    </div>
                </td>
                <td class="px-6 py-4">
                    <div class="text-xs text-gray-600">
                        ${promo.usage_limit_per_user ? `<div>Per user: ${promo.usage_limit_per_user}</div>` : '<div class="text-green-600">Không giới hạn user</div>'}
                        ${promo.total_usage_limit ? `<div>Tối đa: ${promo.total_usage_limit}</div>` : '<div class="text-green-600">Không giới hạn tổng</div>'}
                    </div>
                </td>
                <td class="px-6 py-4">
                    <div class="flex items-center gap-2">
                        <button onclick="showEditModal(${promo.id})" class="text-blue-600 hover:text-blue-900" title="Sửa">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button onclick="showUsageDetails(${promo.id})" class="text-green-600 hover:text-green-900" title="Xem sử dụng">
                            <i class="fas fa-eye"></i>
                        </button>
                        <button onclick="deletePromotion(${promo.id})" class="text-red-600 hover:text-red-900" title="Xóa">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                </td>
            </tr>
        `).join('');
    }
    
    // Show create modal
    function showCreateModal() {
        isEditMode = false;
        document.getElementById('promotionModalTitle').textContent = 'Tạo Khuyến Mãi Mới';
        document.getElementById('submitButtonText').textContent = 'Tạo khuyến mãi';
        document.getElementById('statusGroup').style.display = 'none';
        document.getElementById('promotionForm').reset();
        document.getElementById('promotionId').value = '';
        
        // Set default start date to now
        const now = new Date();
        now.setMinutes(now.getMinutes() - now.getTimezoneOffset());
        document.getElementById('startDate').value = now.toISOString().slice(0,16);
        
        document.getElementById('promotionModal').style.display = 'block';
    }
    
    // Show edit modal
    function showEditModal(id) {
        isEditMode = true;
        document.getElementById('promotionModalTitle').textContent = 'Sửa Khuyến Mãi';
        document.getElementById('submitButtonText').textContent = 'Cập nhật';
        document.getElementById('statusGroup').style.display = 'block';
        
        fetch('', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: `action=get_promotion_detail&id=${id}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const promo = data.promotion;
                document.getElementById('promotionId').value = promo.id;
                document.getElementById('promotionName').value = promo.name;
                document.getElementById('promotionType').value = promo.type;
                document.getElementById('promotionValue').value = promo.value;
                document.getElementById('minDeposit').value = promo.min_deposit;
                document.getElementById('maxBonus').value = promo.max_bonus || '';
                document.getElementById('usageLimitPerUser').value = promo.usage_limit_per_user || '';
                document.getElementById('totalUsageLimit').value = promo.total_usage_limit || '';
                
                // Convert dates to local datetime format
                const startDate = new Date(promo.start_date);
                startDate.setMinutes(startDate.getMinutes() - startDate.getTimezoneOffset());
                document.getElementById('startDate').value = startDate.toISOString().slice(0,16);
                
                const endDate = new Date(promo.end_date);
                endDate.setMinutes(endDate.getMinutes() - endDate.getTimezoneOffset());
                document.getElementById('endDate').value = endDate.toISOString().slice(0,16);
                
                document.getElementById('promotionStatus').value = promo.status;
                document.getElementById('promotionDescription').value = promo.description || '';
                
                updateValueLabel();
                document.getElementById('promotionModal').style.display = 'block';
            }
        });
    }
    
    // Close promotion modal
    function closePromotionModal() {
        document.getElementById('promotionModal').style.display = 'none';
    }
    
    // Show usage details
    function showUsageDetails(promotionId) {
        document.getElementById('usageContent').innerHTML = '<div class="text-center py-4"><div class="loading-spinner mx-auto"></div><p class="mt-2">Đang tải...</p></div>';
        document.getElementById('usageModal').style.display = 'block';
        
        fetch('', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: `action=get_promotion_usage&promotion_id=${promotionId}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                if (data.usage.length === 0) {
                    document.getElementById('usageContent').innerHTML = `
                        <div class="text-center py-8 text-gray-500">
                            <i class="fas fa-inbox text-4xl mb-2"></i>
                            <p>Chưa có ai sử dụng khuyến mãi này</p>
                        </div>
                    `;
                } else {
                    const usageHtml = `
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Người dùng</th>
                                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Nạp</th>
                                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Bonus</th>
                                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Thời gian</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200">
                                    ${data.usage.map(usage => `
                                        <tr>
                                            <td class="px-4 py-2">
                                                <div class="text-sm font-medium">${usage.full_name}</div>
                                                <div class="text-xs text-gray-500">${usage.email}</div>
                                            </td>
                                            <td class="px-4 py-2 text-sm">${new Intl.NumberFormat('vi-VN').format(usage.deposit_amount)}đ</td>
                                            <td class="px-4 py-2 text-sm font-semibold text-green-600">+${new Intl.NumberFormat('vi-VN').format(usage.bonus_xu)} XU</td>
                                            <td class="px-4 py-2 text-sm text-gray-500">${new Date(usage.created_at).toLocaleDateString('vi-VN')} ${new Date(usage.created_at).toLocaleTimeString('vi-VN')}</td>
                                        </tr>
                                    `).join('')}
                                </tbody>
                            </table>
                        </div>
                    `;
                    document.getElementById('usageContent').innerHTML = usageHtml;
                }
            }
        });
    }
    
    // Close usage modal
    function closeUsageModal() {
        document.getElementById('usageModal').style.display = 'none';
    }
    
    // Delete promotion
    function deletePromotion(id) {
        if (!confirm('Bạn có chắc chắn muốn xóa khuyến mãi này?')) return;
        
        fetch('', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: `action=delete_promotion&id=${id}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Xóa thành công!');
                loadPromotions(currentPage, currentSearch);
            } else {
                alert(data.message);
            }
        });
    }
    
    // Show/hide loading
    function showLoading(show) {
        const indicator = document.getElementById('loadingIndicator');
        if (show) {
            indicator.classList.remove('hidden');
        } else {
            indicator.classList.add('hidden');
        }
    }
    
    // Render pagination
    function renderPagination() {
        const container = document.getElementById('paginationContainer');
        let html = '';
        
        // Previous button
        html += `
            <button onclick="goToPage(${currentPage - 1})" 
                    class="px-3 py-2 border border-gray-300 bg-white text-gray-500 hover:bg-gray-50 ${currentPage <= 1 ? 'opacity-50 cursor-not-allowed' : ''}"
                    ${currentPage <= 1 ? 'disabled' : ''}>
                <i class="fas fa-chevron-left"></i>
            </button>
        `;
        
        // Page numbers
        const startPage = Math.max(1, currentPage - 2);
        const endPage = Math.min(totalPages, currentPage + 2);
        
        for (let i = startPage; i <= endPage; i++) {
            html += `
                <button onclick="goToPage(${i})" 
                        class="px-3 py-2 border border-gray-300 ${i === currentPage ? 'bg-blue-500 text-white' : 'bg-white text-gray-700 hover:bg-gray-50'}">
                    ${i}
                </button>
            `;
        }
        
        // Next button
        html += `
            <button onclick="goToPage(${currentPage + 1})" 
                    class="px-3 py-2 border border-gray-300 bg-white text-gray-500 hover:bg-gray-50 ${currentPage >= totalPages ? 'opacity-50 cursor-not-allowed' : ''}"
                    ${currentPage >= totalPages ? 'disabled' : ''}>
                <i class="fas fa-chevron-right"></i>
            </button>
        `;
        
        container.innerHTML = html;
    }
    
    // Update page info
    function updatePageInfo() {
        const startItem = ((currentPage - 1) * itemsPerPage) + 1;
        const endItem = Math.min(currentPage * itemsPerPage, totalItems);
        
        document.getElementById('pageInfo').innerHTML = `
            ${startItem} đến ${endItem} trong tổng số ${totalItems} mục
        `;
        document.getElementById('totalCount').textContent = totalItems;
    }
    
    // Go to page
    function goToPage(page) {
        if (page < 1 || page > totalPages) return;
        loadPromotions(page, currentSearch);
    }
    
    // Change items per page
    function changeItemsPerPage() {
        itemsPerPage = parseInt(document.getElementById('itemsPerPage').value);
        currentPage = 1;
        loadPromotions(1, currentSearch);
    }
    
    // Form submission
    document.getElementById('promotionForm').addEventListener('submit', function(e) {
        e.preventDefault();
        
        const formData = new FormData();
        const action = isEditMode ? 'update_promotion' : 'create_promotion';
        formData.append('action', action);
        
        if (isEditMode) {
            formData.append('id', document.getElementById('promotionId').value);
            formData.append('status', document.getElementById('promotionStatus').value);
        }
        
        formData.append('name', document.getElementById('promotionName').value);
        formData.append('type', document.getElementById('promotionType').value);
        formData.append('value', document.getElementById('promotionValue').value);
        formData.append('min_deposit', document.getElementById('minDeposit').value);
        formData.append('max_bonus', document.getElementById('maxBonus').value);
        formData.append('start_date', document.getElementById('startDate').value);
        formData.append('end_date', document.getElementById('endDate').value);
        formData.append('description', document.getElementById('promotionDescription').value);
        formData.append('usage_limit_per_user', document.getElementById('usageLimitPerUser').value);
        formData.append('total_usage_limit', document.getElementById('totalUsageLimit').value);
        
        fetch('', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert(data.message);
                closePromotionModal();
                loadPromotions(currentPage, currentSearch);
            } else {
                alert(data.message);
            }
        });
    });
    
    // Search functionality
    let searchTimeout;
    document.getElementById('searchInput').addEventListener('input', function(e) {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => {
            currentPage = 1;
            loadPromotions(1, e.target.value);
        }, 500);
    });
    
    // Close modals when clicking outside
    window.addEventListener('click', function(e) {
        const promotionModal = document.getElementById('promotionModal');
        const usageModal = document.getElementById('usageModal');
        
        if (e.target === promotionModal) {
            closePromotionModal();
        }
        if (e.target === usageModal) {
            closeUsageModal();
        }
    });
    
    // Initialize
    document.addEventListener('DOMContentLoaded', function() {
        updatePageTitle('Quản lý khuyến mãi');
        loadPromotions(1);
    });
    
    console.log('✅ Promotion management page loaded');
    </script>
</body>
</html>