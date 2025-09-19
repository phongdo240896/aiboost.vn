<?php
require_once __DIR__ . '/../../app/config.php';
require_once __DIR__ . '/../../app/db.php';
require_once __DIR__ . '/../../app/auth.php';
require_once __DIR__ . '/../../app/middleware.php';

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Require admin access
Middleware::requireAdmin();

// Log activity
Middleware::logActivity('view_admin_bank_accounts');

// Get user data
$userData = Auth::getUser();
$userName = $userData['full_name'] ?? 'Admin';

// Initialize bank settings with defaults
$defaultBankSettings = [
    'ACB' => [
        'bank_name' => 'Ngân hàng ACB',
        'account_number' => '',
        'account_holder' => '',
        'api_token' => '',
        'status' => 'inactive'
    ],
    'VCB' => [
        'bank_name' => 'Vietcombank',
        'account_number' => '',
        'account_holder' => '',
        'api_token' => '',
        'status' => 'inactive'
    ],
    'MBBANK' => [
        'bank_name' => 'MB Bank',
        'account_number' => '',
        'account_holder' => '',
        'api_token' => '',
        'status' => 'inactive'
    ]
];

// Load current settings from database
$currentBankSettings = $defaultBankSettings;

try {
    // Try to get bank settings from database
    $bankRecords = $db->select('bank_settings', '*', [], 'bank_code ASC');
    
    foreach ($bankRecords as $bank) {
        if (isset($currentBankSettings[$bank['bank_code']])) {
            $currentBankSettings[$bank['bank_code']] = [
                'bank_name' => $bank['bank_name'],
                'account_number' => $bank['account_number'],
                'account_holder' => $bank['account_holder'],
                'api_token' => $bank['api_token'],
                'status' => $bank['status']
            ];
        }
    }
} catch (Exception $e) {
    // Bank settings table might not exist yet - use defaults
    error_log('Bank settings table not found, using defaults: ' . $e->getMessage());
}

// Handle AJAX requests
if (isset($_GET['action'])) {
    // Start output buffering to prevent any unwanted output
    ob_start();
    
    // Log incoming request for debugging
    error_log("Bank accounts AJAX request: " . $_GET['action']);
    error_log("POST data: " . print_r($_POST, true));
    error_log("GET data: " . print_r($_GET, true));
    
    // Clear any previous output
    ob_clean();
    
    // Set proper headers
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-cache, must-revalidate');
    header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
    
    try {
        switch ($_GET['action']) {
            case 'save_bank_settings':
                if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                    throw new Exception('Invalid request method');
                }
                
                $bankCode = $_POST['bank_code'] ?? '';
                $bankName = $_POST['bank_name'] ?? '';
                $accountNumber = $_POST['account_number'] ?? '';
                $accountHolder = $_POST['account_holder'] ?? '';
                $apiToken = $_POST['api_token'] ?? '';
                $status = $_POST['status'] ?? 'inactive';
                
                if (!in_array($bankCode, ['ACB', 'VCB', 'MBBANK'])) {
                    throw new Exception('Invalid bank code: ' . $bankCode);
                }
                
                // Create bank_settings table if not exists
                $createTableSQL = "
                    CREATE TABLE IF NOT EXISTS bank_settings (
                        id INT AUTO_INCREMENT PRIMARY KEY,
                        bank_code VARCHAR(20) UNIQUE NOT NULL,
                        bank_name VARCHAR(100) NOT NULL,
                        account_number VARCHAR(50) NOT NULL,
                        account_holder VARCHAR(100) NOT NULL,
                        api_token TEXT,
                        status ENUM('active', 'inactive') DEFAULT 'inactive',
                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                ";
                
                $db->getPdo()->exec($createTableSQL);
                
                // Check if record exists
                $existing = $db->findOne('bank_settings', ['bank_code' => $bankCode]);
                
                if ($existing) {
                    // Update existing record
                    $result = $db->update('bank_settings', [
                        'bank_name' => $bankName,
                        'account_number' => $accountNumber,
                        'account_holder' => $accountHolder,
                        'api_token' => $apiToken,
                        'status' => $status,
                        'updated_at' => date('Y-m-d H:i:s')
                    ], ['bank_code' => $bankCode]);
                } else {
                    // Insert new record
                    $result = $db->insert('bank_settings', [
                        'bank_code' => $bankCode,
                        'bank_name' => $bankName,
                        'account_number' => $accountNumber,
                        'account_holder' => $accountHolder,
                        'api_token' => $apiToken,
                        'status' => $status,
                        'created_at' => date('Y-m-d H:i:s'),
                        'updated_at' => date('Y-m-d H:i:s')
                    ]);
                }
                
                if (!$result) {
                    throw new Exception('Database operation failed');
                }
                
                // Log activity
                try {
                    Middleware::logActivity('update_bank_settings', "Updated {$bankCode} settings");
                } catch (Exception $e) {
                    error_log('Log activity failed: ' . $e->getMessage());
                }
                
                $response = [
                    'success' => true,
                    'message' => "Cập nhật {$bankCode} thành công",
                    'data' => [
                        'bank_code' => $bankCode,
                        'status' => $status,
                        'timestamp' => date('Y-m-d H:i:s')
                    ]
                ];
                
                echo json_encode($response, JSON_UNESCAPED_UNICODE);
                break;
                
            case 'get_bank_settings':
                $response = [
                    'success' => true,
                    'settings' => $currentBankSettings
                ];
                echo json_encode($response, JSON_UNESCAPED_UNICODE);
                break;
                
            case 'test_api':
                $bankCode = $_GET['bank_code'] ?? '';
                $apiToken = trim($_GET['api_token'] ?? '');
                $testMode = $_GET['test_mode'] ?? 'real';
                
                if (!$apiToken || empty($apiToken)) {
                    echo json_encode([
                        'success' => false,
                        'message' => 'API Token không được để trống',
                        'details' => 'Vui lòng nhập API token hợp lệ từ ZenPN'
                    ], JSON_UNESCAPED_UNICODE);
                    break;
                }
                
                // Demo mode for testing UI
                if ($testMode === 'demo') {
                    echo json_encode([
                        'success' => true,
                        'message' => "✅ Demo API test cho {$bankCode}",
                        'data' => [
                            'token' => substr($apiToken, 0, 8) . '...',
                            'status' => 'success',
                            'bank' => $bankCode,
                            'balance' => 1000000,
                            'transactions' => [
                                [
                                    'amount' => 100000,
                                    'description' => 'Demo transaction PAY123456',
                                    'time' => date('Y-m-d H:i:s'),
                                    'status' => 'completed'
                                ]
                            ]
                        ],
                        'api_url' => "Demo mode - không gọi API thật",
                        'http_code' => 200,
                        'response_time' => 50,
                        'token_preview' => 'DEMO_' . substr($apiToken, 0, 8) . '...'
                    ], JSON_UNESCAPED_UNICODE);
                    break;
                }
                
                // Real API test
                try {
                    $startTime = microtime(true);
                    
                    // Build API URL based on ZenPN documentation
                    $apiUrl = '';
                    switch ($bankCode) {
                        case 'ACB':
                            $apiUrl = "https://api.zenpn.com/api/historyacb/{$apiToken}";
                            break;
                        case 'VCB':
                            $apiUrl = "https://api.zenpn.com/api/historyvcb/{$apiToken}";
                            break;
                        case 'MBBANK':
                            $apiUrl = "https://api.zenpn.com/api/historymb/{$apiToken}";
                            break;
                        default:
                            throw new Exception('Bank code không hợp lệ: ' . $bankCode);
                    }
                    
                    // Setup cURL with proper headers for ZenPN API
                    $ch = curl_init();
                    curl_setopt_array($ch, [
                        CURLOPT_URL => $apiUrl,
                        CURLOPT_RETURNTRANSFER => true,
                        CURLOPT_TIMEOUT => 30,
                        CURLOPT_CONNECTTIMEOUT => 10,
                        CURLOPT_USERAGENT => 'AIboost Bank API Test v1.0',
                        CURLOPT_FOLLOWLOCATION => true,
                        CURLOPT_SSL_VERIFYPEER => false,
                        CURLOPT_SSL_VERIFYHOST => false,
                        CURLOPT_HTTPHEADER => [
                            'Accept: application/json',
                            'Content-Type: application/json',
                            'User-Agent: AIboost/1.0'
                        ]
                    ]);
                    
                    $response = curl_exec($ch);
                    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                    $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
                    $error = curl_error($ch);
                    $responseTime = round((microtime(true) - $startTime) * 1000, 2);
                    curl_close($ch);
                    
                    // Log for debugging
                    error_log("ZenPN API Test - Bank: {$bankCode}");
                    error_log("API URL: {$apiUrl}");
                    error_log("HTTP Code: {$httpCode}");
                    error_log("Content-Type: {$contentType}");
                    error_log("Response: " . substr($response, 0, 500));
                    
                    if ($error) {
                        throw new Exception("cURL Error: {$error}");
                    }
                    
                    // Handle different HTTP status codes
                    if ($httpCode === 404) {
                        echo json_encode([
                            'success' => false,
                            'message' => 'API endpoint không tồn tại',
                            'details' => 'Token có thể không hợp lệ hoặc API đã thay đổi',
                            'api_url' => $apiUrl,
                            'http_code' => $httpCode,
                            'response_time' => $responseTime
                        ], JSON_UNESCAPED_UNICODE);
                        break;
                    }
                    
                    if ($httpCode === 401 || $httpCode === 403) {
                        echo json_encode([
                            'success' => false,
                            'message' => 'API Token không hợp lệ hoặc hết hạn',
                            'details' => 'Vui lòng kiểm tra lại token từ ZenPN.com',
                            'api_url' => $apiUrl,
                            'http_code' => $httpCode,
                            'response_time' => $responseTime
                        ], JSON_UNESCAPED_UNICODE);
                        break;
                    }
                    
                    if ($httpCode !== 200) {
                        $responsePreview = strip_tags(substr($response, 0, 300));
                        echo json_encode([
                            'success' => false,
                            'message' => "HTTP Error: {$httpCode}",
                            'details' => "Server response: {$responsePreview}",
                            'api_url' => $apiUrl,
                            'http_code' => $httpCode,
                            'response_time' => $responseTime
                        ], JSON_UNESCAPED_UNICODE);
                        break;
                    }
                    
                    // Check if response is JSON
                    if (strpos($contentType, 'application/json') === false && strpos($response, '{') !== 0) {
                        $responsePreview = strip_tags(substr($response, 0, 500));
                        echo json_encode([
                            'success' => false,
                            'message' => "API trả về HTML thay vì JSON",
                            'details' => "Có thể token sai hoặc API maintenance. Response: {$responsePreview}",
                            'api_url' => $apiUrl,
                            'http_code' => $httpCode,
                            'response_time' => $responseTime,
                            'content_type' => $contentType
                        ], JSON_UNESCAPED_UNICODE);
                        break;
                    }
                    
                    // Try to parse JSON
                    $data = json_decode($response, true);
                    
                    if (json_last_error() !== JSON_ERROR_NONE) {
                        $errorMsg = json_last_error_msg();
                        $responsePreview = strip_tags(substr($response, 0, 500));
                        
                        echo json_encode([
                            'success' => false,
                            'message' => "JSON Parse Error: {$errorMsg}",
                            'details' => "Response preview: {$responsePreview}",
                            'api_url' => $apiUrl,
                            'http_code' => $httpCode,
                            'response_time' => $responseTime
                        ], JSON_UNESCAPED_UNICODE);
                        break;
                    }
                    
                    // Success response
                    echo json_encode([
                        'success' => true,
                        'message' => "✅ API {$bankCode} hoạt động tốt",
                        'data' => $data,
                        'api_url' => $apiUrl,
                        'http_code' => $httpCode,
                        'response_time' => $responseTime,
                        'token_preview' => substr($apiToken, 0, 8) . '...' . substr($apiToken, -4),
                        'content_type' => $contentType
                    ], JSON_UNESCAPED_UNICODE);
                    
                } catch (Exception $e) {
                    error_log('ZenPN API Test Error: ' . $e->getMessage());
                    echo json_encode([
                        'success' => false,
                        'message' => "API Test Failed: " . $e->getMessage(),
                        'details' => 'Kiểm tra token và kết nối mạng',
                        'api_url' => $apiUrl ?? 'N/A',
                        'response_time' => $responseTime ?? 0
                    ], JSON_UNESCAPED_UNICODE);
                }
                break;
                
            case 'get_recent_transactions':
                try {
                    // Get recent bank logs from database
                    $recentLogs = [];
                    $todayCount = 0;
                    $today = date('Y-m-d');
                    
                    // Query bank_logs table
                    $sql = "SELECT 
                            bl.*,
                            u.email as user_email,
                            u.full_name as user_name
                        FROM bank_logs bl
                        LEFT JOIN users u ON bl.user_id = u.id
                        ORDER BY bl.created_at DESC
                        LIMIT 50";
                    
                    $stmt = $db->getPdo()->query($sql);
                    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    // Count today's transactions
                    $todaySQL = "SELECT COUNT(*) as count 
                                FROM bank_logs 
                                WHERE DATE(created_at) = ?";
                    $todayStmt = $db->getPdo()->prepare($todaySQL);
                    $todayStmt->execute([$today]);
                    $todayResult = $todayStmt->fetch(PDO::FETCH_ASSOC);
                    $todayCount = $todayResult['count'] ?? 0;
                    
                    // Format transactions for display
                    foreach ($logs as $log) {
                        $recentLogs[] = [
                            'id' => $log['id'],
                            'transaction_id' => $log['transaction_id'],
                            'user_id' => $log['user_id'],
                            'user_email' => $log['user_email'] ?? 'N/A',
                            'user_name' => $log['user_name'] ?? 'Unknown',
                            'amount' => $log['amount'],
                            'bank' => $log['bank_code'],
                            'status' => $log['status'],
                            'description' => $log['description'],
                            'reference_number' => $log['reference_number'],
                            'transaction_date' => $log['transaction_date'],
                            'created_at' => $log['created_at'],
                            'processed_at' => $log['processed_at']
                        ];
                    }
                    
                    echo json_encode([
                        'success' => true,
                        'transactions' => $recentLogs,
                        'today_count' => $todayCount,
                        'total_count' => count($recentLogs)
                    ], JSON_UNESCAPED_UNICODE);
                    
                } catch (Exception $e) {
                    error_log('Error fetching bank logs: ' . $e->getMessage());
                    echo json_encode([
                        'success' => false,
                        'message' => 'Lỗi tải dữ liệu giao dịch',
                        'error' => $e->getMessage()
                    ], JSON_UNESCAPED_UNICODE);
                }
                break;
                
            case 'process_bank_log':
                if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                    throw new Exception('Invalid request method');
                }
                
                $input = file_get_contents('php://input');
                $data = json_decode($input, true);
                $transactionId = $data['transaction_id'] ?? '';
                
                if (!$transactionId) {
                    throw new Exception('Transaction ID is required');
                }
                
                try {
                    // Get the bank log
                    $log = $db->findOne('bank_logs', ['transaction_id' => $transactionId]);
                    
                    if (!$log) {
                        throw new Exception('Transaction not found');
                    }
                    
                    if ($log['status'] !== 'pending') {
                        throw new Exception('Transaction already processed');
                    }
                    
                    // Extract user from description
                    $userId = $log['user_id'];
                    
                    if (!$userId) {
                        // Try to extract from description
                        if (preg_match('/PAY(\d{4})/', $log['description'], $matches)) {
                            $payCode = 'PAY' . $matches[1];
                            
                            // Find user by pay code pattern
                            $users = $db->select('users', ['id', 'email'], [], 'id DESC');
                            foreach ($users as $user) {
                                $userPayCode = 'PAY' . substr($user['id'], -4);
                                if ($userPayCode === $payCode) {
                                    $userId = $user['id'];
                                    break;
                                }
                            }
                        }
                    }
                    
                    if (!$userId) {
                        throw new Exception('Cannot identify user from transaction');
                    }
                    
                    // Start transaction
                    $db->getPdo()->beginTransaction();
                    
                    try {
                        // Update user balance
                        $sql = "UPDATE users SET balance = balance + ? WHERE id = ?";
                        $stmt = $db->getPdo()->prepare($sql);
                        $stmt->execute([$log['amount'], $userId]);
                        
                        // Create transaction record
                        $db->insert('transactions', [
                            'id' => 'TXN_' . time() . '_' . rand(1000, 9999),
                            'user_id' => $userId,
                            'type' => 'credit',
                            'amount' => $log['amount'],
                            'description' => 'Nạp tiền qua ' . $log['bank_code'] . ' - Ref: ' . $log['reference_number'],
                            'status' => 'completed',
                            'reference_id' => $log['transaction_id'],
                            'bank_code' => $log['bank_code'],
                            'created_at' => date('Y-m-d H:i:s')
                        ]);
                        
                        // Update bank log status
                        $db->update('bank_logs', [
                            'status' => 'processed',
                            'user_id' => $userId,
                            'processed_at' => date('Y-m-d H:i:s')
                        ], ['transaction_id' => $transactionId]);
                        
                        $db->getPdo()->commit();
                        
                        echo json_encode([
                            'success' => true,
                            'message' => 'Giao dịch đã được xử lý thành công'
                        ], JSON_UNESCAPED_UNICODE);
                        
                    } catch (Exception $e) {
                        $db->getPdo()->rollBack();
                        throw $e;
                    }
                    
                } catch (Exception $e) {
                    echo json_encode([
                        'success' => false,
                        'message' => 'Lỗi xử lý giao dịch: ' . $e->getMessage()
                    ], JSON_UNESCAPED_UNICODE);
                }
                break;
                
            case 'get_bank_stats':
                try {
                    $stats = [];
                    
                    // Get stats for each bank
                    $banks = ['ACB', 'VCB', 'MBBANK'];
                    foreach ($banks as $bank) {
                        $sql = "SELECT 
                                COUNT(*) as total_transactions,
                                SUM(CASE WHEN status = 'processed' THEN amount ELSE 0 END) as total_amount,
                                COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending_count
                            FROM bank_logs 
                            WHERE bank_code = ?
                            AND DATE(created_at) >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)";
                        
                        $stmt = $db->getPdo()->prepare($sql);
                        $stmt->execute([$bank]);
                        $result = $stmt->fetch(PDO::FETCH_ASSOC);
                        
                        $stats[$bank] = [
                            'total_transactions' => $result['total_transactions'] ?? 0,
                            'total_amount' => $result['total_amount'] ?? 0,
                            'pending_count' => $result['pending_count'] ?? 0
                        ];
                    }
                    
                    echo json_encode([
                        'success' => true,
                        'stats' => $stats
                    ], JSON_UNESCAPED_UNICODE);
                    
                } catch (Exception $e) {
                    echo json_encode([
                        'success' => false,
                        'message' => 'Lỗi tải thống kê: ' . $e->getMessage()
                    ], JSON_UNESCAPED_UNICODE);
                }
                break;
                
            default:
                throw new Exception('Unknown action: ' . $_GET['action']);
        }
        
    } catch (Exception $e) {
        error_log('AJAX Error in bank_accounts.php: ' . $e->getMessage());
        error_log('Stack trace: ' . $e->getTraceAsString());
        
        $errorResponse = [
            'success' => false,
            'message' => $e->getMessage(),
            'error_code' => 'SYSTEM_ERROR',
            'debug' => [
                'file' => basename($e->getFile()),
                'line' => $e->getLine(),
                'action' => $_GET['action'] ?? 'unknown'
            ]
        ];
        
        echo json_encode($errorResponse, JSON_UNESCAPED_UNICODE);
    }
    
    // Ensure clean exit
    exit();
}
$pageTitle = "Quản Lý Ngân Hàng - Admin";
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
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
            
            <!-- Page Header -->
            <div class="mb-8">
                <h1 class="text-3xl font-bold text-gray-900 flex items-center">
                    🏦 Quản Lý Ngân Hàng
                </h1>
                <p class="text-gray-600 mt-2">Cấu hình tài khoản ngân hàng và API tự động</p>
            </div>
            <!-- Quick Stats -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
                <div class="bg-white rounded-xl shadow-sm p-6 border">
                    <div class="flex items-center">
                        <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center mr-4">
                            <span class="text-2xl">🏦</span>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600">Tổng Ngân Hàng</p>
                            <p class="text-2xl font-bold text-gray-900" id="totalBanks">3</p>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white rounded-xl shadow-sm p-6 border">
                    <div class="flex items-center">
                        <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center mr-4">
                            <span class="text-2xl">✅</span>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600">Đang Hoạt Động</p>
                            <p class="text-2xl font-bold text-green-600" id="activeBanks">0</p>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white rounded-xl shadow-sm p-6 border">
                    <div class="flex items-center">
                        <div class="w-12 h-12 bg-orange-100 rounded-lg flex items-center justify-center mr-4">
                            <span class="text-2xl">🔧</span>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600">API Configured</p>
                            <p class="text-2xl font-bold text-orange-600" id="configuredAPIs">0</p>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white rounded-xl shadow-sm p-6 border">
                    <div class="flex items-center">
                        <div class="w-12 h-12 bg-purple-100 rounded-lg flex items-center justify-center mr-4">
                            <span class="text-2xl">💰</span>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600">Giao Dịch Hôm Nay</p>
                            <p class="text-2xl font-bold text-purple-600" id="todayTransactions">-</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Bank Configuration Cards -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
                
                <!-- ACB Bank -->
                <div class="bg-white rounded-xl shadow-sm p-6 border" id="acb-card">
                    <div class="flex items-center justify-between mb-6">
                        <div class="flex items-center">
                            <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center mr-3">
                                <span class="text-2xl">🏛️</span>
                            </div>
                            <h3 class="text-xl font-bold text-gray-900">ACB Bank</h3>
                        </div>
                        <div class="flex items-center space-x-2">
                            <span class="text-sm text-gray-600">Hiển thị:</span>
                            <div class="toggle-switch" id="acb_status_toggle" onclick="toggleBankStatus('ACB')">
                                <div class="toggle-slider"></div>
                            </div>
                        </div>
                    </div>

                    <form onsubmit="updateBankSettings(event, 'ACB')">
                        <div class="space-y-4">
                            <input type="hidden" id="acb_status" value="inactive">
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Tên ngân hàng</label>
                                <input type="text" id="acb_bank_name" value="Ngân hàng ACB"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Số tài khoản</label>
                                <input type="text" id="acb_account_number" placeholder="0123456789"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Tên tài khoản</label>
                                <input type="text" id="acb_account_holder" placeholder="NGUYEN VAN A"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">API Token</label>
                                <textarea id="acb_api_token" rows="3" placeholder="Nhập API token từ ZenPN..."
                                          class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 font-mono text-sm"></textarea>
                                <p class="text-xs text-gray-500 mt-1">API từ <a href="https://api.zenpn.com" target="_blank" class="text-blue-600">ZenPN.com</a></p>
                            </div>
                            
                            <div class="flex space-x-2">
                                <button type="submit" class="flex-1 bg-blue-600 text-white py-2 rounded-lg hover:bg-blue-700 font-semibold">
                                    💾 Lưu ACB
                                </button>
                                <button type="button" onclick="testAPI('ACB')" class="px-4 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700">
                                    🧪 Test
                                </button>
                            </div>
                        </div>
                    </form>
                </div>

                <!-- VCB Bank -->
                <div class="bg-white rounded-xl shadow-sm p-6 border" id="vcb-card">
                    <div class="flex items-center justify-between mb-6">
                        <div class="flex items-center">
                            <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center mr-3">
                                <span class="text-2xl">🏦</span>
                            </div>
                            <h3 class="text-xl font-bold text-gray-900">Vietcombank</h3>
                        </div>
                        <div class="flex items-center space-x-2">
                            <span class="text-sm text-gray-600">Hiển thị:</span>
                            <div class="toggle-switch" id="vcb_status_toggle" onclick="toggleBankStatus('VCB')">
                                <div class="toggle-slider"></div>
                            </div>
                        </div>
                    </div>

                    <form onsubmit="updateBankSettings(event, 'VCB')">
                        <div class="space-y-4">
                            <input type="hidden" id="vcb_status" value="inactive">
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Tên ngân hàng</label>
                                <input type="text" id="vcb_bank_name" value="Vietcombank"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500">
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Số tài khoản</label>
                                <input type="text" id="vcb_account_number" placeholder="0123456789"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500">
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Tên tài khoản</label>
                                <input type="text" id="vcb_account_holder" placeholder="NGUYEN VAN A"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500">
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">API Token</label>
                                <textarea id="vcb_api_token" rows="3" placeholder="Nhập API token từ ZenPN..."
                                          class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 font-mono text-sm"></textarea>
                                <p class="text-xs text-gray-500 mt-1">API từ <a href="https://api.zenpn.com" target="_blank" class="text-blue-600">ZenPN.com</a></p>
                            </div>
                            
                            <div class="flex space-x-2">
                                <button type="submit" class="flex-1 bg-green-600 text-white py-2 rounded-lg hover:bg-green-700 font-semibold">
                                    💾 Lưu VCB
                                </button>
                                <button type="button" onclick="testAPI('VCB')" class="px-4 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700">
                                    🧪 Test
                                </button>
                            </div>
                        </div>
                    </form>
                </div>

                <!-- MBBANK -->
                <div class="bg-white rounded-xl shadow-sm p-6 border" id="mbbank-card">
                    <div class="flex items-center justify-between mb-6">
                        <div class="flex items-center">
                            <div class="w-12 h-12 bg-purple-100 rounded-lg flex items-center justify-center mr-3">
                                <span class="text-2xl">💳</span>
                            </div>
                            <h3 class="text-xl font-bold text-gray-900">MB Bank</h3>
                        </div>
                        <div class="flex items-center space-x-2">
                            <span class="text-sm text-gray-600">Hiển thị:</span>
                            <div class="toggle-switch" id="mbbank_status_toggle" onclick="toggleBankStatus('MBBANK')">
                                <div class="toggle-slider"></div>
                            </div>
                        </div>
                    </div>

                    <form onsubmit="updateBankSettings(event, 'MBBANK')">
                        <div class="space-y-4">
                            <input type="hidden" id="mbbank_status" value="inactive">
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Tên ngân hàng</label>
                                <input type="text" id="mbbank_bank_name" value="MB Bank"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500">
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Số tài khoản</label>
                                <input type="text" id="mbbank_account_number" placeholder="0123456789"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500">
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Tên tài khoản</label>
                                <input type="text" id="mbbank_account_holder" placeholder="NGUYEN VAN A"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500">
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">API Token</label>
                                <textarea id="mbbank_api_token" rows="3" placeholder="Nhập API token từ ZenPN..."
                                          class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 font-mono text-sm"></textarea>
                                <p class="text-xs text-gray-500 mt-1">API từ <a href="https://api.zenpn.com" target="_blank" class="text-blue-600">ZenPN.com</a></p>
                            </div>
                            
                            <div class="flex space-x-2">
                                <button type="submit" class="flex-1 bg-purple-600 text-white py-2 rounded-lg hover:bg-purple-700 font-semibold">
                                    💾 Lưu MBBANK
                                </button>
                                <button type="button" onclick="testAPI('MBBANK')" class="px-4 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700">
                                    🧪 Test
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Status Summary -->
            <div class="bg-white rounded-xl shadow-sm p-6 border mb-8">
                <h3 class="text-lg font-bold text-gray-900 mb-4">📊 Trạng Thái Ngân Hàng</h3>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div class="flex items-center justify-between p-4 bg-blue-50 rounded-lg border border-blue-200">
                        <div class="flex items-center">
                            <span class="text-2xl mr-3">🏛️</span>
                            <span class="font-medium text-blue-900">ACB Bank</span>
                        </div>
                        <span id="acb_status_text" class="px-3 py-1 rounded-full text-xs font-medium bg-gray-100 text-gray-800">Đang tải...</span>
                    </div>
                    <div class="flex items-center justify-between p-4 bg-green-50 rounded-lg border border-green-200">
                        <div class="flex items-center">
                            <span class="text-2xl mr-3">🏦</span>
                            <span class="font-medium text-green-900">Vietcombank</span>
                        </div>
                        <span id="vcb_status_text" class="px-3 py-1 rounded-full text-xs font-medium bg-gray-100 text-gray-800">Đang tải...</span>
                    </div>
                    <div class="flex items-center justify-between p-4 bg-purple-50 rounded-lg border border-purple-200">
                        <div class="flex items-center">
                            <span class="text-2xl mr-3">💳</span>
                            <span class="font-medium text-purple-900">MB Bank</span>
                        </div>
                        <span id="mbbank_status_text" class="px-3 py-1 rounded-full text-xs font-medium bg-gray-100 text-gray-800">Đang tải...</span>
                    </div>
                </div>
            </div>

            <!-- API Test Results -->
            <div class="bg-white rounded-xl shadow-sm p-6 border mb-8" id="apiTestSection" style="display: none;">
                <h3 class="text-xl font-bold text-gray-900 mb-4">🧪 Kết Quả Test API</h3>
                
                <div id="apiTestResult" class="p-4 bg-gray-50 rounded-lg border">
                    <h4 class="font-semibold mb-3 text-gray-900">API Test Result:</h4>
                    <pre id="apiTestOutput" class="text-sm text-gray-700 overflow-x-auto whitespace-pre-wrap"></pre>
                </div>
            </div>

            <!-- Recent Transactions -->
            <div class="bg-white rounded-xl shadow-sm p-6 border">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-xl font-bold text-gray-900">📊 Giao Dịch Ngân Hàng (Bank Logs)</h3>
                    <div class="flex items-center space-x-2">
                        <select id="statusFilter" onchange="filterTransactions()" class="px-3 py-1 border rounded-lg text-sm">
                            <option value="all">Tất cả</option>
                            <option value="pending">Chờ xử lý</option>
                            <option value="processed">Đã xử lý</option>
                            <option value="failed">Thất bại</option>
                            <option value="duplicate">Trùng lặp</option>
                        </select>
                        <button onclick="loadRecentTransactions()" class="px-3 py-1 bg-blue-600 text-white rounded-lg hover:bg-blue-700 text-sm font-medium">
                            🔄 Làm mới
                        </button>
                    </div>
                </div>
                
                <!-- Transaction Stats -->
                <div class="grid grid-cols-4 gap-4 mb-4">
                    <div class="bg-gray-50 rounded-lg p-3">
                        <div class="text-xs text-gray-600">Tổng giao dịch</div>
                        <div class="text-lg font-bold text-gray-900" id="totalTransactions">0</div>
                    </div>
                    <div class="bg-yellow-50 rounded-lg p-3">
                        <div class="text-xs text-yellow-600">Chờ xử lý</div>
                        <div class="text-lg font-bold text-yellow-700" id="pendingTransactions">0</div>
                    </div>
                    <div class="bg-green-50 rounded-lg p-3">
                        <div class="text-xs text-green-600">Đã xử lý</div>
                        <div class="text-lg font-bold text-green-700" id="processedTransactions">0</div>
                    </div>
                    <div class="bg-blue-50 rounded-lg p-3">
                        <div class="text-xs text-blue-600">Hôm nay</div>
                        <div class="text-lg font-bold text-blue-700" id="todayTransactions">0</div>
                    </div>
                </div>
                
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">ID</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Thời gian</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">User</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Ngân hàng</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Số tiền</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Nội dung</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Trạng thái</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Hành động</th>
                            </tr>
                        </thead>
                        <tbody id="transactionsTableBody" class="bg-white divide-y divide-gray-200">
                            <tr>
                                <td colspan="8" class="px-6 py-8 text-center text-gray-500">
                                    <div class="text-4xl mb-2">⏳</div>
                                    <p class="text-sm">Đang tải bank logs...</p>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Loading Overlay -->
    <div id="loadingOverlay" class="loading-overlay" style="display: none;">
        <div class="bg-white rounded-lg p-6 shadow-xl">
            <div class="flex items-center space-x-3">
                <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600"></div>
                <span class="text-gray-700 font-medium">Đang xử lý...</span>
            </div>
        </div>
    </div>

    <!-- Include Admin Footer -->
    <?php include __DIR__ . '/partials/footer.php'; ?>

    <script>
        // Global variables
        let bankSettings = <?php echo json_encode($currentBankSettings); ?>;
        let isLoading = false;

        // Initialize on page load
        document.addEventListener('DOMContentLoaded', function() {
            console.log('✅ Bank accounts admin page loaded');
            console.log('Current bank settings:', bankSettings);
            
            // Load initial data
            populateAllForms();
            updateAllStatusUI();
            updateStats();
            loadRecentTransactions();
        });

        function showLoading(show = true) {
            document.getElementById('loadingOverlay').style.display = show ? 'flex' : 'none';
        }

        function populateAllForms() {
            Object.keys(bankSettings).forEach(bankCode => {
                populateForm(bankCode, bankSettings[bankCode]);
            });
        }

        function populateForm(bankCode, settings) {
            if (!settings) return;
            
            const prefix = bankCode.toLowerCase();
            
            document.getElementById(`${prefix}_bank_name`).value = settings.bank_name || '';
            document.getElementById(`${prefix}_account_number`).value = settings.account_number || '';
            document.getElementById(`${prefix}_account_holder`).value = settings.account_holder || '';
            document.getElementById(`${prefix}_api_token`).value = settings.api_token || '';
            document.getElementById(`${prefix}_status`).value = settings.status || 'inactive';
        }

        function updateStatusUI(bankCode, status) {
            const prefix = bankCode.toLowerCase();
            const toggle = document.getElementById(`${prefix}_status_toggle`);
            const statusText = document.getElementById(`${prefix}_status_text`);
            
            if (status === 'active') {
                toggle.classList.add('active');
                statusText.textContent = 'Hoạt động';
                statusText.className = 'px-3 py-1 rounded-full text-xs font-medium bg-green-100 text-green-800';
            } else {
                toggle.classList.remove('active');
                statusText.textContent = 'Tạm dừng';
                statusText.className = 'px-3 py-1 rounded-full text-xs font-medium bg-gray-100 text-gray-800';
            }
        }

        function updateAllStatusUI() {
            Object.keys(bankSettings).forEach(bankCode => {
                const status = bankSettings[bankCode].status || 'inactive';
                updateStatusUI(bankCode, status);
            });
        }

        function updateStats() {
            const totalBanks = Object.keys(bankSettings).length;
            const activeBanks = Object.values(bankSettings).filter(bank => bank.status === 'active').length;
            const configuredAPIs = Object.values(bankSettings).filter(bank => bank.api_token && bank.api_token.trim() !== '').length;
            
            document.getElementById('totalBanks').textContent = totalBanks;
            document.getElementById('activeBanks').textContent = activeBanks;
            document.getElementById('configuredAPIs').textContent = configuredAPIs;
        }

        async function toggleBankStatus(bankCode) {
            console.log(`🔄 Toggling ${bankCode} status...`);
            
            const prefix = bankCode.toLowerCase();
            const statusField = document.getElementById(`${prefix}_status`);
            const currentStatus = statusField.value;
            
            console.log(`Current status: ${currentStatus}`);
            
            // Toggle status
            const newStatus = currentStatus === 'active' ? 'inactive' : 'active';
            statusField.value = newStatus;
            
            console.log(`New status: ${newStatus}`);
            
            // Update local data
            bankSettings[bankCode].status = newStatus;
            
            // Update UI immediately
            updateStatusUI(bankCode, newStatus);
            updateStats();
            
            // Show loading state
            const toggle = document.getElementById(`${prefix}_status_toggle`);
            toggle.style.opacity = '0.6';
            toggle.style.pointerEvents = 'none';
            
            try {
                // Auto save with detailed error info
                const result = await saveBankSettings(bankCode);
                
                console.log(`✅ ${bankCode} status toggled successfully:`, result);
                
                // Flash success
                const card = document.getElementById(`${prefix}-card`);
                card.classList.add('success-flash');
                setTimeout(() => card.classList.remove('success-flash'), 500);
                
                // Show success message
                const statusText = document.getElementById(`${prefix}_status_text`);
                statusText.textContent = '✅ Đã lưu';
                setTimeout(() => {
                    updateStatusUI(bankCode, newStatus);
                }, 1000);
                
            } catch (error) {
                console.error(`❌ Toggle ${bankCode} failed:`, error);
                
                // Revert on error
                statusField.value = currentStatus;
                bankSettings[bankCode].status = currentStatus;
                updateStatusUI(bankCode, currentStatus);
                updateStats();
                
                // Show detailed error with console info
                const errorMsg = `❌ Lỗi cập nhật trạng thái ${bankCode}:

${error.message}

🔧 Debug Info:
- Mở DevTools (F12) và xem Console tab
- Kiểm tra Network tab để xem request/response
- Báo lỗi chi tiết đã được log

Vui lòng thử lại hoặc liên hệ support.`;
                
                alert(errorMsg);
                
            } finally {
                // Restore toggle state
                toggle.style.opacity = '1';
                toggle.style.pointerEvents = 'auto';
            }
        }

        async function updateBankSettings(event, bankCode) {
            event.preventDefault();
            
            const button = event.target.querySelector('button[type="submit"]');
            const originalText = button.textContent;
            
            try {
                button.textContent = '⏳ Đang lưu...';
                button.disabled = true;
                
                await saveBankSettings(bankCode);
                
                // Update local data
                const prefix = bankCode.toLowerCase();
                bankSettings[bankCode] = {
                    bank_name: document.getElementById(`${prefix}_bank_name`).value,
                    account_number: document.getElementById(`${prefix}_account_number`).value,
                    account_holder: document.getElementById(`${prefix}_account_holder`).value,
                    api_token: document.getElementById(`${prefix}_api_token`).value,
                    status: document.getElementById(`${prefix}_status`).value
                };
                
                updateStats();
                
                // Show success
                button.textContent = '✅ Đã lưu!';
                button.style.backgroundColor = '#10b981';
                
                setTimeout(() => {
                    button.textContent = originalText;
                    button.disabled = false;
                    button.style.backgroundColor = '';
                }, 2000);
                
            } catch (error) {
                button.textContent = '❌ Lỗi!';
                button.style.backgroundColor = '#ef4444';
                
                setTimeout(() => {
                    button.textContent = originalText;
                    button.disabled = false;
                    button.style.backgroundColor = '';
                }, 2000);
                
                alert(`❌ Lỗi cập nhật ${bankCode}: ${error.message}`);
            }
        }

        async function saveBankSettings(bankCode) {
            const prefix = bankCode.toLowerCase();
            
            const formData = new FormData();
            formData.append('bank_code', bankCode);
            formData.append('bank_name', document.getElementById(`${prefix}_bank_name`).value);
            formData.append('account_number', document.getElementById(`${prefix}_account_number`).value);
            formData.append('account_holder', document.getElementById(`${prefix}_account_holder`).value);
            formData.append('api_token', document.getElementById(`${prefix}_api_token`).value);
            formData.append('status', document.getElementById(`${prefix}_status`).value);
            
            console.log(`🔄 Saving ${bankCode} settings...`);
            
            try {
                const response = await fetch('?action=save_bank_settings', {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });
                
                console.log('Response status:', response.status);
                console.log('Response headers:', [...response.headers.entries()]);
                
                // Get response text first
                const responseText = await response.text();
                console.log('Raw response:', responseText.substring(0, 500));
                
                // Check if response is ok
                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                }
                
                // Check if response is JSON
                const contentType = response.headers.get('content-type');
                if (!contentType || !contentType.includes('application/json')) {
                    console.error('❌ Non-JSON response received');
                    console.error('Content-Type:', contentType);
                    console.error('Response body:', responseText);
                    throw new Error(`Server trả về ${contentType || 'unknown'} thay vì JSON. Response: ${responseText.substring(0, 200)}`);
                }
                
                // Try to parse JSON
                let result;
                try {
                    result = JSON.parse(responseText);
                } catch (parseError) {
                    console.error('❌ JSON parse error:', parseError);
                    console.error('Response text:', responseText);
                    throw new Error(`Invalid JSON response: ${parseError.message}. Response: ${responseText.substring(0, 200)}`);
                }
                
                if (!result.success) {
                    throw new Error(result.message || 'Unknown error occurred');
                }
                
                console.log('✅ Bank settings saved successfully:', result);
                return result;
                
            } catch (error) {
                console.error('❌ Save bank settings error:', error);
                throw error;
            }
        }

        async function testAPI(bankCode, mode = 'real') {
            const prefix = bankCode.toLowerCase();
            const apiToken = document.getElementById(`${prefix}_api_token`).value;
            
            if (mode === 'real' && (!apiToken || apiToken.trim() === '')) {
                alert('⚠️ Vui lòng nhập API token trước khi test!');
                return;
            }
            
            showLoading(true);
            
            try {
                const url = `?action=test_api&bank_code=${bankCode}&test_mode=${mode}&api_token=${encodeURIComponent(apiToken)}`;
                const response = await fetch(url);
                
                // Check if response is OK
                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                }
                
                // Check if response is JSON
                const contentType = response.headers.get('content-type');
                if (!contentType || !contentType.includes('application/json')) {
                    const text = await response.text();
                    throw new Error(`Server trả về ${contentType} thay vì JSON: ${text.substring(0, 200)}`);
                }
                
                const result = await response.json();
                
                // Show test section
                document.getElementById('apiTestSection').style.display = 'block';
                const outputElement = document.getElementById('apiTestOutput');
                
                if (result.success) {
                    const modeLabel = mode === 'demo' ? '📝 DEMO MODE' : '🔥 LIVE API';
                    outputElement.innerHTML = `<div style="color: #059669;">
                        <strong>✅ API Test Success for ${bankCode} (${modeLabel})</strong>
                        <br><br>
                        <strong>API Endpoint:</strong> ${result.api_url || 'N/A'}
                        <br>
                        <strong>HTTP Status:</strong> ${result.http_code || 200}
                        <br>
                        <strong>Response Time:</strong> ${result.response_time || 'N/A'}ms
                        <br>
                        <strong>Token:</strong> ${result.token_preview || 'N/A'}
                        <br><br>
                        <strong>Sample Data:</strong>
                        <pre style="background: #f0f9ff; padding: 10px; border-radius: 5px; max-height: 300px; overflow-y: auto;">${JSON.stringify(result.data, null, 2)}</pre>
                    </div>`;
                } else {
                    outputElement.innerHTML = `<div style="color: #dc2626;">
                        <strong>❌ API Test Failed for ${bankCode}</strong>
                        <br><br>
                        <strong>Error:</strong> ${result.message}
                        <br>
                        <strong>Details:</strong> ${result.details || 'Không có thông tin chi tiết'}
                        <br>
                        <strong>API URL:</strong> ${result.api_url || 'N/A'}
                        <br>
                        <strong>HTTP Code:</strong> ${result.http_code || 'N/A'}
                        <br><br>
                        <strong>Possible Solutions:</strong>
                        <ul style="margin-left: 20px; margin-top: 10px;">
                            <li>• Thử nút 📝 Demo để test giao diện</li>
                            <li>• Kiểm tra API Token từ ZenPN</li>
                            <li>• Đảm bảo tài khoản được kích hoạt API</li>
                            <li>• Thử lại sau vài phút</li>
                        </ul>
                    </div>`;
                }
                
                // Scroll to result
                document.getElementById('apiTestSection').scrollIntoView({ behavior: 'smooth' });
                
            } catch (error) {
                console.error('API Test Error:', error);
                
                // Show error in test section
                document.getElementById('apiTestSection').style.display = 'block';
                document.getElementById('apiTestOutput').innerHTML = `<div style="color: #dc2626;">
                    <strong>❌ Network/Server Error</strong>
                    <br><br>
                    <strong>Error:</strong> ${error.message}
                    <br><br>
                    <strong>Quick Fix:</strong>
                    <ul style="margin-left: 20px; margin-top: 10px;">
                        <li>• Thử nút 📝 Demo trước để test giao diện</li>
                        <li>• Reload trang và thử lại</li>
                        <li>• Kiểm tra console để xem lỗi chi tiết</li>
                    </ul>
                </div>`;
                
                document.getElementById('apiTestSection').scrollIntoView({ behavior: 'smooth' });
                
            } finally {
                showLoading(false);
            }
        }

        async function loadBankSettings() {
            if (isLoading) return;
            
            isLoading = true;
            
            try {
                const response = await fetch('?action=get_bank_settings');
                const result = await response.json();
                
                if (result.success) {
                    bankSettings = result.settings;
                    populateAllForms();
                    updateAllStatusUI();
                    updateStats();
                    console.log('✅ Bank settings reloaded');
                }
                
            } catch (error) {
                console.error('❌ Error loading bank settings:', error);
            } finally {
                isLoading = false;
            }
        }

        async function loadRecentTransactions() {
            try {
                const response = await fetch('?action=get_recent_transactions');
                const result = await response.json();
                
                const tbody = document.getElementById('transactionsTableBody');
                
                if (result.success && result.transactions.length > 0) {
                    let todayCount = 0;
                    const today = new Date().toDateString();
                    
                    tbody.innerHTML = result.transactions.map(tx => {
                        const txDate = new Date(tx.created_at);
                        if (txDate.toDateString() === today) {
                            todayCount++;
                        }
                        
                        const statusColors = {
                            'completed': 'bg-green-100 text-green-800',
                            'pending': 'bg-yellow-100 text-yellow-800',
                            'failed': 'bg-red-100 text-red-800'
                        };
                        
                        const statusText = {
                            'completed': 'Thành công',
                            'pending': 'Chờ xử lý',
                            'failed': 'Thất bại'
                        };
                        
                        return `
                            <tr>
                                <td class="px-6 py-4 text-sm text-gray-900">${txDate.toLocaleString('vi-VN')}</td>
                                <td class="px-6 py-4 text-sm text-gray-500">${tx.user_id.substring(0, 8)}...</td>
                                <td class="px-6 py-4 text-sm text-gray-500">${tx.bank || 'N/A'}</td>
                                <td class="px-6 py-4 text-sm font-medium text-green-600">+${Number(tx.amount).toLocaleString()}₫</td>
                                <td class="px-6 py-4">
                                    <span class="px-2 py-1 text-xs rounded-full ${statusColors[tx.status] || 'bg-gray-100 text-gray-800'}">
                                        ${statusText[tx.status] || tx.status}
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-sm font-mono text-blue-600">${tx.pay_code || '-'}</td>
                            </tr>
                        `;
                    }).join('');
                    
                    document.getElementById('todayTransactions').textContent = todayCount;
                    
                } else {
                    tbody.innerHTML = `
                        <tr>
                            <td colspan="6" class="px-6 py-8 text-center text-gray-500">
                                <div class="text-4xl mb-2">📝</div>
                                <p class="text-sm">Chưa có giao dịch nào</p>
                            </td>
                        </tr>
                    `;
                    document.getElementById('todayTransactions').textContent = '0';
                }
                
            } catch (error) {
                console.error('❌ Error loading transactions:', error);
                document.getElementById('transactionsTableBody').innerHTML = `
                    <tr>
                        <td colspan="6" class="px-6 py-8 text-center text-red-500">
                            <div class="text-4xl mb-2">❌</div>
                            <p class="text-sm">Lỗi tải giao dịch</p>
                        </td>
                    </tr>
                `;
            }
        }

        console.log('✅ Bank accounts admin page scripts loaded');
        console.log('Admin user: <?php echo htmlspecialchars($userName); ?>');
    </script>
</body>
</html>