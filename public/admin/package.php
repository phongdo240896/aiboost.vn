<?php
session_start();
require_once __DIR__ . '/../../app/config.php';
require_once __DIR__ . '/../../app/db.php';
require_once __DIR__ . '/../../app/auth.php';
require_once __DIR__ . '/../../app/middleware.php';
require_once __DIR__ . '/../../app/models/Plan.php';

// Check admin access using Middleware
try {
    Middleware::requireAdmin();
} catch (Exception $e) {
    error_log('Admin access denied in package.php: ' . $e->getMessage());
    header('Location: ' . url('login'));
    exit;
}

// Log activity
Middleware::logActivity('view_admin_packages');

$message = '';
$messageType = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (isset($_POST['action'])) {
            switch ($_POST['action']) {
                case 'create':
                    $data = [
                        'name' => trim($_POST['name']),
                        'price' => (float)$_POST['price'],
                        'credits' => (int)$_POST['credits'],
                        'duration_days' => (int)$_POST['duration_days'],
                        'description' => trim($_POST['description']),
                        'is_recommended' => isset($_POST['is_recommended']) ? 1 : 0,
                        'status' => $_POST['status'] ?? 'active'
                    ];
                    
                    // Validate data
                    if (empty($data['name'])) {
                        throw new Exception('Tên gói không được để trống');
                    }
                    
                    Plan::create($data);
                    Plan::refreshCache(); // Refresh cache sau khi tạo
                    $message = "Tạo gói mới thành công! Pricing page đã được cập nhật.";
                    $messageType = "success";
                    break;
                    
                case 'update':
                    $id = (int)$_POST['id'];
                    if ($id <= 0) {
                        throw new Exception('ID gói không hợp lệ');
                    }
                    
                    $data = [
                        'name' => trim($_POST['name']),
                        'price' => (float)$_POST['price'],
                        'credits' => (int)$_POST['credits'],
                        'duration_days' => (int)$_POST['duration_days'],
                        'description' => trim($_POST['description']),
                        'is_recommended' => isset($_POST['is_recommended']) ? 1 : 0,
                        'status' => $_POST['status'] ?? 'active'
                    ];
                    
                    Plan::update($id, $data);
                    Plan::refreshCache(); // Refresh cache sau khi update
                    $message = "Cập nhật gói thành công! Pricing page đã được cập nhật.";
                    $messageType = "success";
                    break;
                    
                case 'delete':
                    $id = (int)$_POST['id'];
                    if ($id <= 0) {
                        throw new Exception('ID gói không hợp lệ');
                    }
                    
                    Plan::delete($id);
                    Plan::refreshCache(); // Refresh cache sau khi xóa
                    $message = "Xóa gói thành công! Pricing page đã được cập nhật.";
                    $messageType = "success";
                    break;
                    
                case 'toggle_status':
                    $id = (int)$_POST['id'];
                    $currentStatus = $_POST['current_status'];
                    $newStatus = $currentStatus === 'active' ? 'inactive' : 'active';
                    
                    Plan::update($id, ['status' => $newStatus]);
                    Plan::refreshCache(); // Refresh cache sau khi toggle
                    $message = "Đã thay đổi trạng thái gói! Pricing page đã được cập nhật.";
                    $messageType = "success";
                    break;
                    
                case 'reset_default':
                    // Reset to default plans
                    Plan::resetToDefault();
                    Plan::refreshCache(); // Refresh cache sau khi reset
                    $message = "Đã reset về 4 gói mặc định! Pricing page đã được cập nhật.";
                    $messageType = "success";
                    break;
            }
        }
    } catch (Exception $e) {
        $message = "Lỗi: " . $e->getMessage();
        $messageType = "error";
        error_log('Package admin error: ' . $e->getMessage());
    }
}

// Get all plans
try {
    $plans = Plan::all();
} catch (Exception $e) {
    $plans = [];
    $message = "Lỗi khi lấy danh sách gói: " . $e->getMessage();
    $messageType = "error";
    error_log('Error getting plans: ' . $e->getMessage());
}

$pageTitle = "Quản lý Gói Dịch Vụ - Admin";
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
        }
        
        .xu-currency {
            background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            font-weight: bold;
        }
        
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
        
        .modal.show {
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .modal-content {
            background: white;
            border-radius: 12px;
            padding: 24px;
            max-width: 500px;
            width: 90%;
            max-height: 90vh;
            overflow-y: auto;
        }
    </style>
</head>
<body class="min-h-screen bg-gray-50">
    <!-- Include Admin Sidebar -->
    <?php include __DIR__ . '/partials/sidebar.php'; ?>
    
    <!-- Include Admin Header -->
    <?php include __DIR__ . '/partials/header.php'; ?>

    <!-- Main Content -->
    <div class="lg:ml-64">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
            
            <!-- Page Header -->
            <div class="mb-6 flex justify-between items-center">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900">🎁 Quản lý Gói Dịch Vụ</h1>
                    <p class="text-gray-600 mt-1">Tạo và chỉnh sửa các gói subscription</p>
                </div>
                <button onclick="openCreateModal()" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition-colors">
                    <i class="fas fa-plus me-2"></i>Tạo Gói Mới
                </button>
            </div>

            <!-- Messages -->
            <?php if ($message): ?>
                <div class="mb-6 p-4 rounded-lg <?= $messageType === 'success' ? 'bg-green-50 border border-green-200 text-green-800' : 'bg-red-50 border border-red-200 text-red-800' ?>">
                    <i class="fas fa-<?= $messageType === 'success' ? 'check-circle' : 'exclamation-triangle' ?> me-2"></i>
                    <?= htmlspecialchars($message) ?>
                </div>
            <?php endif; ?>

            <!-- Plans Table -->
            <div class="bg-white rounded-lg shadow overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h3 class="text-lg font-medium text-gray-900">Danh sách gói dịch vụ</h3>
                </div>
                
                <?php if (empty($plans)): ?>
                    <div class="px-6 py-8 text-center text-gray-500">
                        <i class="fas fa-inbox text-4xl mb-4 text-gray-400"></i>
                        <p>Chưa có gói nào. Tạo gói đầu tiên!</p>
                    </div>
                <?php else: ?>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Gói</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Giá</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Xu</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Thời hạn</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Trạng thái</th>
                                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Thao tác</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php foreach ($plans as $plan): ?>
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="flex items-center">
                                                <div>
                                                    <div class="text-sm font-medium text-gray-900 flex items-center">
                                                        <?= htmlspecialchars($plan['name']) ?>
                                                        <?php if ((int)$plan['is_recommended'] === 1): ?>
                                                            <span class="ml-2 bg-blue-100 text-blue-800 text-xs px-2 py-1 rounded-full">Phổ biến</span>
                                                        <?php endif; ?>
                                                    </div>
                                                    <div class="text-sm text-gray-500"><?= htmlspecialchars($plan['description']) ?></div>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm font-medium text-gray-900">
                                                <?= $plan['price'] == 0 ? 'Miễn phí' : number_format($plan['price']) . 'đ' ?>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm text-gray-900"><?= number_format($plan['credits']) ?> Xu</div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm text-gray-900"><?= $plan['duration_days'] ?> ngày</div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <?php 
                                            $planStatus = $plan['status'] ?? 'active';
                                            $statusColor = $planStatus === 'active' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800';
                                            $statusText = $planStatus === 'active' ? 'Hoạt động' : 'Tạm dừng';
                                            ?>
                                            <form method="POST" class="inline">
                                                <input type="hidden" name="action" value="toggle_status">
                                                <input type="hidden" name="id" value="<?= $plan['id'] ?>">
                                                <input type="hidden" name="current_status" value="<?= htmlspecialchars($planStatus) ?>">
                                                <button type="submit" class="<?= $statusColor ?> px-2 py-1 text-xs rounded-full hover:opacity-80">
                                                    <?= $statusText ?>
                                                </button>
                                            </form>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                            <button onclick="openEditModal(<?= htmlspecialchars(json_encode($plan)) ?>)" class="text-blue-600 hover:text-blue-900 mr-3">
                                                <i class="fas fa-edit"></i> Sửa
                                            </button>
                                            <button onclick="deletePlan(<?= $plan['id'] ?>, '<?= htmlspecialchars($plan['name']) ?>')" class="text-red-600 hover:text-red-900">
                                                <i class="fas fa-trash"></i> Xóa
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Additional Info với link tới pricing -->
            <div class="mt-6 grid grid-cols-1 md:grid-cols-3 gap-4">
                <div class="bg-white p-4 rounded-lg shadow">
                    <h4 class="font-medium text-gray-900 mb-2">🔄 Reset về mặc định</h4>
                    <p class="text-sm text-gray-600 mb-3">Khôi phục 4 gói cơ bản: Free, Standard, Pro, Ultra</p>
                    <button onclick="resetToDefault()" class="w-full bg-gray-600 text-white py-2 px-4 rounded hover:bg-gray-700">
                        Reset Plans
                    </button>
                </div>
                
                <div class="bg-white p-4 rounded-lg shadow">
                    <h4 class="font-medium text-gray-900 mb-2">📊 Thống kê</h4>
                    <p class="text-sm text-gray-600">Tổng: <strong><?= count($plans) ?></strong> gói</p>
                    <p class="text-sm text-gray-600">Đang hoạt động: <strong><?= count(array_filter($plans, fn($p) => ($p['status'] ?? 'active') === 'active')) ?></strong> gói</p>
                </div>
                
                <div class="bg-white p-4 rounded-lg shadow">
                    <h4 class="font-medium text-gray-900 mb-2">🔗 Liên kết</h4>
                    <a href="/pricing" target="_blank" class="block text-blue-600 hover:text-blue-800 text-sm mb-2">
                        <i class="fas fa-external-link-alt me-1"></i>Xem Pricing Page
                    </a>
                    <button onclick="testPricingSync()" class="block text-green-600 hover:text-green-800 text-sm mb-2">
                        <i class="fas fa-sync me-1"></i>Test Đồng Bộ
                    </button>
                    <a href="/admin" class="block text-blue-600 hover:text-blue-800 text-sm">
                        <i class="fas fa-arrow-left me-1"></i>Quay lại Admin
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Create/Edit Modal -->
    <div id="planModal" class="modal fixed inset-0 bg-black bg-opacity-50 items-center justify-center z-50">
        <div class="bg-white rounded-lg shadow-xl max-w-md w-full mx-4">
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 id="modalTitle" class="text-lg font-medium text-gray-900">Tạo Gói Mới</h3>
            </div>
            
            <form id="planForm" method="POST">
                <div class="px-6 py-4 space-y-4">
                    <input type="hidden" name="action" id="formAction" value="create">
                    <input type="hidden" name="id" id="planId">
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Tên gói</label>
                        <input type="text" name="name" id="planName" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Giá (VNĐ)</label>
                            <input type="number" name="price" id="planPrice" min="0" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Số Xu</label>
                            <input type="number" name="credits" id="planCredits" min="0" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Thời hạn (ngày)</label>
                            <input type="number" name="duration_days" id="planDuration" value="30" min="1" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Trạng thái</label>
                            <select name="status" id="planStatus" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                                <option value="active">Hoạt động</option>
                                <option value="inactive">Tạm dừng</option>
                            </select>
                        </div>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Mô tả</label>
                        <textarea name="description" id="planDescription" rows="3" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"></textarea>
                    </div>
                    
                    <div class="flex items-center">
                        <input type="checkbox" name="is_recommended" id="planRecommended" class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                        <label for="planRecommended" class="ml-2 block text-sm text-gray-700">Gói được đề xuất</label>
                    </div>
                </div>
                
                <div class="px-6 py-4 border-t border-gray-200 flex justify-end space-x-3">
                    <button type="button" onclick="closeModal()" class="px-4 py-2 text-gray-700 bg-gray-100 rounded-md hover:bg-gray-200">
                        Hủy
                    </button>
                    <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">
                        <span id="submitText">Tạo Gói</span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Delete Form (hidden) -->
    <form id="deleteForm" method="POST" style="display: none;">
        <input type="hidden" name="action" value="delete">
        <input type="hidden" name="id" id="deleteId">
    </form>

    <!-- Reset Form (hidden) -->
    <form id="resetForm" method="POST" style="display: none;">
        <input type="hidden" name="action" value="reset_default">
    </form>

    <script>
        // Modal functions
        function openCreateModal() {
            document.getElementById('modalTitle').textContent = 'Tạo Gói Mới';
            document.getElementById('formAction').value = 'create';
            document.getElementById('submitText').textContent = 'Tạo Gói';
            document.getElementById('planForm').reset();
            document.getElementById('planId').value = '';
            document.getElementById('planModal').classList.add('show');
        }

        function openEditModal(plan) {
            document.getElementById('modalTitle').textContent = 'Chỉnh Sửa Gói';
            document.getElementById('formAction').value = 'update';
            document.getElementById('submitText').textContent = 'Cập Nhật';
            document.getElementById('planId').value = plan.id;
            document.getElementById('planName').value = plan.name;
            document.getElementById('planPrice').value = plan.price;
            document.getElementById('planCredits').value = plan.credits;
            document.getElementById('planDuration').value = plan.duration_days;
            document.getElementById('planDescription').value = plan.description;
            document.getElementById('planStatus').value = plan.status;
            document.getElementById('planRecommended').checked = parseInt(plan.is_recommended) === 1;
            document.getElementById('planModal').classList.add('show');
        }

        function closeModal() {
            document.getElementById('planModal').classList.remove('show');
        }

        function deletePlan(id, name) {
            if (confirm(`Bạn có chắc muốn xóa gói "${name}"?\n\nThao tác này không thể hoàn tác.`)) {
                document.getElementById('deleteId').value = id;
                document.getElementById('deleteForm').submit();
            }
        }

        function resetToDefault() {
            if (confirm('Bạn có chắc muốn reset về 4 gói mặc định?\n\nTất cả gói hiện tại sẽ bị xóa.')) {
                // Create reset action via JavaScript
                const form = document.createElement('form');
                form.method = 'POST';
                form.style.display = 'none';
                
                const actionInput = document.createElement('input');
                actionInput.type = 'hidden';
                actionInput.name = 'action';
                actionInput.value = 'reset_default';
                
                form.appendChild(actionInput);
                document.body.appendChild(form);
                form.submit();
            }
        }

        // Test đồng bộ dữ liệu
        function testPricingSync() {
            // Mở pricing page trong tab mới
            const pricingWindow = window.open('/pricing', '_blank');
            
            // Show notification
            const notification = document.createElement('div');
            notification.className = 'fixed top-4 right-4 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded z-50';
            notification.innerHTML = '<strong>✅ Đã mở Pricing Page!</strong><br>Kiểm tra dữ liệu có đồng bộ không.';
            document.body.appendChild(notification);
            
            // Auto remove notification sau 3s
            setTimeout(() => {
                notification.remove();
            }, 3000);
        }

        // Close modal when clicking outside
        document.getElementById('planModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeModal();
            }
        });

        // Auto refresh page every 5 minutes
        setTimeout(() => {
            if (!document.querySelector('.modal.show')) {
                location.reload();
            }
        }, 300000);

        // Auto refresh notification sau khi submit form
        document.querySelectorAll('form').forEach(form => {
            if (form.method.toLowerCase() === 'post') {
                form.addEventListener('submit', function() {
                    // Show loading
                    const loading = document.createElement('div');
                    loading.className = 'fixed top-4 right-4 bg-blue-100 border border-blue-400 text-blue-700 px-4 py-3 rounded z-50';
                    loading.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Đang cập nhật...';
                    document.body.appendChild(loading);
                });
            }
        });
        document.addEventListener('DOMContentLoaded', function() {
            updatePageTitle('Quản lý gói dịch vụ');
        });

        console.log('✅ Admin Package Management with sync loaded!');
        
    </script>

    <!-- Include Admin Footer -->
    <?php include __DIR__ . '/partials/footer.php'; ?>

</body>
</html>