<?php
require_once __DIR__ . '/../app/config.php';
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/auth.php';
require_once __DIR__ . '/../app/middleware.php';
require_once __DIR__ . '/../app/WalletManager.php';
require_once __DIR__ . '/../app/SubscriptionMiddleware.php';

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Require login to access topup
Middleware::requireLogin();

// Check if user has an active subscription (block Free users)
$subscription = SubscriptionMiddleware::requireSubscription();

// Log activity
Middleware::logActivity('topup_page_view');

// Get user data
$userData = Auth::getUser();
if (!$userData) {
    Auth::logout();
    header('Location: ' . url('login'));
    exit;
}

$userName = $userData['full_name'] ?? 'User';
$userEmail = $userData['email'] ?? '';
$userId = $userData['id'];

// Initialize WalletManager
$walletManager = new WalletManager($db);

// Get current balance in XU
$currentBalanceXU = $walletManager->getBalance($userId);
$exchangeRate = $walletManager->getExchangeRate();

// Generate unique pay code for user (only last 4 digits of user ID)
$userPayCode = 'PAY' . substr($userId, -4);

// Get bank settings from bank_settings table
$bankSettings = [];

try {
    // Get active bank settings from database
    $bankRecords = $db->select('bank_settings', '*', ['status' => 'active'], 'bank_code ASC');
    
    foreach ($bankRecords as $bank) {
        $bankSettings[$bank['bank_code']] = [
            'bank_name' => $bank['bank_name'],
            'account_number' => $bank['account_number'],
            'account_holder' => $bank['account_holder'],
            'status' => $bank['status']
        ];
    }
    
    // Fallback to demo data if no active banks
    if (empty($bankSettings)) {
        $bankSettings = [
            'ACB' => [
                'bank_name' => 'Ngân hàng ACB (Demo)',
                'account_number' => '123456789',
                'account_holder' => 'CONG TY AIBOOST',
                'status' => 'active'
            ]
        ];
    }
    
} catch (Exception $e) {
    error_log('Error loading bank settings for topup: ' . $e->getMessage());
    
    // Use fallback demo settings
    $bankSettings = [
        'ACB' => [
            'bank_name' => 'Ngân hàng ACB (Demo)',
            'account_number' => '123456789',
            'account_holder' => 'CONG TY AIBOOST',
            'status' => 'active'
        ]
    ];
}

// Handle AJAX requests
if (isset($_GET['action'])) {
    header('Content-Type: application/json');
    
    switch ($_GET['action']) {
        case 'get_bank_settings':
            echo json_encode([
                'success' => true,
                'settings' => $bankSettings,
                'exchange_rate' => $exchangeRate
            ]);
            exit;
            
        case 'check_pending_payment':
            // Check if there's any pending bank_logs for this user
            try {
                $sql = "SELECT * FROM bank_logs 
                        WHERE (user_id = ? OR description LIKE ?) 
                        AND status = 'pending' 
                        ORDER BY created_at DESC 
                        LIMIT 1";
                
                $payPattern = '%' . $userPayCode . '%';
                $stmt = $db->getPdo()->prepare($sql);
                $stmt->execute([$userId, $payPattern]);
                $pending = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($pending) {
                    echo json_encode([
                        'success' => true,
                        'has_pending' => true,
                        'amount' => $pending['amount'],
                        'created_at' => $pending['created_at']
                    ]);
                } else {
                    echo json_encode([
                        'success' => true,
                        'has_pending' => false
                    ]);
                }
            } catch (Exception $e) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Error checking payment status'
                ]);
            }
            exit;
            
        case 'get_exchange_info':
            // Get exchange rate and calculate XU amount
            $vndAmount = intval($_GET['amount'] ?? 0);
            $xuAmount = $vndAmount > 0 ? floor($vndAmount / $exchangeRate) : 0;
            
            echo json_encode([
                'success' => true,
                'vnd_amount' => $vndAmount,
                'xu_amount' => $xuAmount,
                'exchange_rate' => $exchangeRate,
                'rate_text' => "1 XU = " . number_format($exchangeRate) . " VND"
            ]);
            exit;
            
        case 'refresh_balance':
            // Get updated balance
            $newBalance = $walletManager->getBalance($userId);
            
            echo json_encode([
                'success' => true,
                'balance_xu' => $newBalance,
                'balance_vnd' => $newBalance * $exchangeRate,
                'exchange_rate' => $exchangeRate
            ]);
            exit;
    }
    
    // Default response
    echo json_encode(['success' => false, 'message' => 'Invalid action']);
    exit;
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nạp Tiền - AIboost.vn</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
body { 
    font-family: 'Inter', sans-serif; 
    background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
    min-height: 100vh;
}

/* ===== RESPONSIVE GRID SYSTEM ===== */
.topup-grid {
    display: grid;
    grid-template-columns: 1fr;
    gap: 1.5rem;
}

@media (min-width: 1280px) {
    .topup-grid {
        grid-template-columns: 2fr 1.5fr;
        gap: 2rem;
    }
}

/* ===== CARD COMPONENTS ===== */
.topup-card {
    background: white;
    border-radius: 16px;
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    border: 1px solid rgba(226, 232, 240, 0.8);
}

.topup-card:hover {
    box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
    transform: translateY(-2px);
}

/* ===== AMOUNT SELECTION CARDS ===== */
.amount-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 1rem;
}

@media (min-width: 640px) {
    .amount-grid {
        grid-template-columns: repeat(4, 1fr);
    }
}

@media (max-width: 480px) {
    .amount-grid {
        gap: 0.75rem;
    }
}

.amount-card {
    background: white;
    border: 2px solid #e2e8f0;
    border-radius: 12px;
    padding: 1rem;
    text-align: center;
    cursor: pointer;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    position: relative;
    overflow: hidden;
}

.amount-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: -100%;
    width: 100%;
    height: 100%;
    background: linear-gradient(90deg, transparent, rgba(59, 130, 246, 0.1), transparent);
    transition: left 0.5s;
}

.amount-card:hover {
    transform: translateY(-4px);
    border-color: #3b82f6;
    box-shadow: 0 12px 24px -4px rgba(59, 130, 246, 0.25);
}

.amount-card:hover::before {
    left: 100%;
}

.amount-card.selected {
    border-color: #3b82f6;
    background: linear-gradient(135deg, #eff6ff 0%, #dbeafe 100%);
    transform: translateY(-2px);
    box-shadow: 0 8px 16px -4px rgba(59, 130, 246, 0.3);
}

.amount-card .amount-value {
    font-size: 1.5rem;
    font-weight: 700;
    color: #3b82f6;
    margin-bottom: 0.25rem;
    display: block;
}

@media (max-width: 640px) {
    .amount-card .amount-value {
        font-size: 1.25rem;
    }
    
    .amount-card {
        padding: 0.75rem;
    }
}

/* ===== BALANCE DISPLAY ===== */
.balance-card {
    background: linear-gradient(135deg, #10b981 0%, #3b82f6 100%);
    color: white;
    border-radius: 16px;
    padding: 1.5rem;
    margin-bottom: 1.5rem;
    position: relative;
    overflow: hidden;
}

.balance-card::before {
    content: '';
    position: absolute;
    top: -50%;
    right: -50%;
    width: 200%;
    height: 200%;
    background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
    animation: float 6s ease-in-out infinite;
}

@keyframes float {
    0%, 100% { transform: translate(0, 0) rotate(0deg); }
    50% { transform: translate(-20px, -20px) rotate(180deg); }
}

.balance-info {
    position: relative;
    z-index: 1;
}

@media (max-width: 640px) {
    .balance-card {
        padding: 1rem;
        margin-bottom: 1rem;
    }
}

/* ===== BANK SELECTION ===== */
.bank-grid {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}

.bank-tab {
    background: white;
    border: 2px solid #e5e7eb;
    border-radius: 12px;
    padding: 1rem;
    cursor: pointer;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    position: relative;
    overflow: hidden;
}

.bank-tab::before {
    content: '';
    position: absolute;
    left: 0;
    top: 0;
    width: 0;
    height: 100%;
    background: linear-gradient(90deg, #3b82f6, #1d4ed8);
    transition: width 0.3s ease;
}

.bank-tab:hover {
    border-color: #3b82f6;
    transform: translateX(4px);
    box-shadow: 0 4px 12px -2px rgba(59, 130, 246, 0.25);
}

.bank-tab.active {
    background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%);
    color: white;
    border-color: #1d4ed8;
    transform: translateX(0);
}

.bank-tab.active::before {
    width: 4px;
}

/* ===== BANK INFO SECTION ===== */
.bank-info-section {
    animation: slideInUp 0.5s cubic-bezier(0.4, 0, 0.2, 1);
}

@keyframes slideInUp {
    from {
        opacity: 0;
        transform: translateY(20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.info-row {
    background: #f8fafc;
    border-radius: 12px;
    padding: 1rem;
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 0.75rem;
    transition: all 0.3s ease;
}

.info-row:hover {
    background: #f1f5f9;
    transform: translateX(2px);
}

.copy-btn {
    color: #3b82f6;
    padding: 0.25rem;
    border-radius: 6px;
    transition: all 0.2s ease;
}

.copy-btn:hover {
    background: rgba(59, 130, 246, 0.1);
    transform: scale(1.1);
}

/* ===== QR CODE SECTION - MOBILE OPTIMIZED ===== */
.qr-container {
    background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
    border: 2px dashed #cbd5e1;
    border-radius: 16px;
    padding: 2rem;
    text-align: center;
    transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
    min-height: 280px;
    display: flex;
    flex-direction: column;
    justify-content: center;
    align-items: center;
}

.qr-container.active {
    background: linear-gradient(135deg, #eff6ff 0%, #dbeafe 100%);
    border: 2px solid #3b82f6;
    border-style: solid;
}

#qrCodeImage {
    border-radius: 12px;
    box-shadow: 0 8px 16px -4px rgba(0, 0, 0, 0.1);
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    max-width: 100%;
    height: auto;
    margin: 0 auto;
    display: block;
}

#qrCodeImage:hover {
    transform: scale(1.05);
    box-shadow: 0 12px 24px -4px rgba(59, 130, 246, 0.3);
}

/* Mobile QR optimizations */
@media (max-width: 768px) {
    .qr-container {
        padding: 1.5rem;
        min-height: 300px; /* Tăng chiều cao */
    }
    
    #qrCodeImage {
        width: 180px !important; /* Tăng kích thước */
        height: 180px !important;
        margin: 0 auto;
    }
}

@media (max-width: 480px) {
    .qr-container {
        padding: 1rem;
        min-height: 280px;
        margin: 0 auto;
    }
    
    #qrCodeImage {
        width: 160px !important;
        height: 160px !important;
        margin: 0 auto;
        display: block;
    }
    
    /* Đảm bảo QR được căn giữa hoàn hảo */
    .qr-container img {
        margin-left: auto !important;
        margin-right: auto !important;
    }
}

/* QR Placeholder styling */
#qrCodePlaceholder {
    display: flex;
    flex-direction: column;
    justify-content: center;
    align-items: center;
    text-align: center;
    width: 100%;
}

#qrCodePlaceholder .text-6xl {
    margin-bottom: 1rem;
}

/* Button container dưới QR */
.qr-buttons {
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 0.75rem;
    margin-top: 1rem;
    flex-wrap: wrap;
}

@media (max-width: 480px) {
    .qr-buttons {
        flex-direction: column;
        gap: 0.5rem;
        width: 100%;
    }
    
    .qr-buttons button {
        width: 100%;
        max-width: 200px;
    }
}

/* Bank info responsive */
@media (max-width: 640px) {
    .info-row {
        padding: 0.75rem;
        flex-direction: column;
        align-items: flex-start;
        gap: 0.5rem;
    }
    
    .info-row > div {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        width: 100%;
        justify-content: space-between;
    }
}

/* Amount display card - mobile fix */
@media (max-width: 640px) {
    .bg-gradient-to-r.from-yellow-50 {
        padding: 1rem;
        margin: 1rem 0;
    }
    
    .bg-gradient-to-r.from-yellow-50 .text-3xl {
        font-size: 1.875rem; /* Giảm kích thước text trên mobile */
    }
}

/* ===== BUTTONS ===== */
.btn-primary {
    background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%);
    color: white;
    padding: 0.75rem 1.5rem;
    border-radius: 12px;
    border: none;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    position: relative;
    overflow: hidden;
}

.btn-primary::before {
    content: '';
    position: absolute;
    top: 0;
    left: -100%;
    width: 100%;
    height: 100%;
    background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
    transition: left 0.5s;
}

.btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 16px -4px rgba(59, 130, 246, 0.4);
}

.btn-primary:hover::before {
    left: 100%;
}

.btn-secondary {
    background: #f8fafc;
    color: #475569;
    border: 1px solid #e2e8f0;
    padding: 0.5rem 1rem;
    border-radius: 8px;
    font-size: 0.875rem;
    cursor: pointer;
    transition: all 0.2s ease;
}

.btn-secondary:hover {
    background: #f1f5f9;
    border-color: #cbd5e1;
    transform: translateY(-1px);
}

/* ===== INPUT FIELDS ===== */
.form-input {
    width: 100%;
    padding: 1rem;
    border: 2px solid #e2e8f0;
    border-radius: 12px;
    font-size: 1rem;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    background: white;
}

.form-input:focus {
    outline: none;
    border-color: #3b82f6;
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
    transform: translateY(-1px);
}

.form-input::placeholder {
    color: #9ca3af;
}

/* ===== TRANSACTION HISTORY TABLE ===== */
.transaction-table {
    width: 100%;
    border-collapse: collapse;
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
}

.transaction-table th {
    background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
    padding: 0.75rem;
    text-align: left;
    font-weight: 600;
    color: #475569;
    border-bottom: 1px solid #e2e8f0;
}

.transaction-table td {
    padding: 0.75rem;
    border-bottom: 1px solid #f1f5f9;
    transition: all 0.2s ease;
}

.transaction-table tr:hover td {
    background: #f8fafc;
}

@media (max-width: 640px) {
    .transaction-table th,
    .transaction-table td {
        padding: 0.5rem 0.25rem;
        font-size: 0.875rem;
    }
    
    .hide-mobile {
        display: none;
    }
}

/* ===== STATUS BADGES ===== */
.status-badge {
    padding: 0.25rem 0.75rem;
    border-radius: 20px;
    font-size: 0.75rem;
    font-weight: 600;
    text-align: center;
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
}

.status-completed {
    background: linear-gradient(135deg, #dcfce7 0%, #bbf7d0 100%);
    color: #166534;
}

.status-pending {
    background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
    color: #92400e;
}

.status-failed {
    background: linear-gradient(135deg, #fee2e2 0%, #fecaca 100%);
    color: #991b1b;
}

/* ===== INFO CARDS ===== */
.info-card {
    background: linear-gradient(135deg, #0f766e 0%, #3b82f6 100%);
    color: white;
    border-radius: 16px;
    padding: 1.5rem;
    position: relative;
    overflow: hidden;
}

.info-card::before {
    content: '';
    position: absolute;
    top: -50%;
    right: -50%;
    width: 200%;
    height: 200%;
    background: radial-gradient(circle, rgba(255,255,255,0.05) 0%, transparent 50%);
    animation: rotate 20s linear infinite;
}

@keyframes rotate {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}

/* ===== XU BADGE ===== */
.xu-badge {
    background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
    color: white;
    padding: 0.125rem 0.5rem;
    border-radius: 12px;
    font-weight: 700;
    font-size: 0.75rem;
    display: inline-block;
    box-shadow: 0 2px 4px rgba(245, 158, 11, 0.3);
}

/* ===== LOADING STATES ===== */
.loading {
    opacity: 0.7;
    pointer-events: none;
    position: relative;
}

.loading::after {
    content: '';
    position: absolute;
    top: 50%;
    left: 50%;
    width: 20px;
    height: 20px;
    margin: -10px 0 0 -10px;
    border: 2px solid #3b82f6;
    border-top-color: transparent;
    border-radius: 50%;
    animation: spin 1s linear infinite;
}

@keyframes spin {
    to { transform: rotate(360deg); }
}

/* ===== RESPONSIVE UTILITIES ===== */
@media (max-width: 1023px) {
    .lg-hide {
        display: none;
    }
}

@media (max-width: 767px) {
    .md-hide {
        display: none;
    }
    
    .mobile-full {
        width: 100% !important;
    }
    
    .mobile-text-sm {
        font-size: 0.875rem !important;
    }
    
    .mobile-p-2 {
        padding: 0.5rem !important;
    }
}

@media (max-width: 479px) {
    .sm-hide {
        display: none;
    }
    
    .mobile-text-xs {
        font-size: 0.75rem !important;
    }
}

/* ===== SMOOTH SCROLLING ===== */
html {
    scroll-behavior: smooth;
}

/* ===== ACCESSIBILITY ===== */
@media (prefers-reduced-motion: reduce) {
    *, *::before, *::after {
        animation-duration: 0.01ms !important;
        animation-iteration-count: 1 !important;
        transition-duration: 0.01ms !important;
    }
}

/* ===== FOCUS STYLES ===== */
*:focus {
    outline: 2px solid #3b82f6;
    outline-offset: 2px;
}

button:focus,
.bank-tab:focus,
.amount-card:focus {
    outline: 2px solid #3b82f6;
    outline-offset: 4px;
}

/* ===== PRINT STYLES ===== */
@media print {
    .no-print {
        display: none !important;
    }
    
    .topup-card {
        box-shadow: none !important;
        border: 1px solid #000 !important;
    }
}
    </style>
</head>
<body class="min-h-screen bg-gray-50">
    <!-- Include Sidebar -->
    <?php include __DIR__ . '/partials/sidebar.php'; ?>
    
    <!-- Include Header -->
    <?php include __DIR__ . '/partials/header.php'; ?>

    <!-- Main Content -->
    <div class="lg:ml-64 content-container">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8" style="padding-top: 0.5rem !important; padding-bottom: 1rem;">
            
            <!-- Page Header -->
            <div class="mb-6">
                <h1 class="text-2xl font-bold text-gray-900">💰 Nạp Tiền Vào Ví</h1>
                <p class="text-gray-600 mt-1">Nạp tiền để sử dụng các dịch vụ AI</p>
                
                <!-- Current Balance -->
                <div class="balance-card mb-6">
                    <div class="balance-info">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm font-medium opacity-90 mb-1">💎 Số dư hiện tại</p>
                                <div class="flex items-baseline gap-2 mb-2">
                                    <span class="text-3xl font-bold" id="currentBalanceXU"><?php echo number_format($currentBalanceXU); ?></span>
                                    <span class="text-lg font-semibold opacity-90">XU</span>
                                </div>
                                <p class="text-sm opacity-75">
                                    ≈ <span id="currentBalanceVND" class="font-semibold"><?php echo number_format($currentBalanceXU * $exchangeRate); ?></span> VND
                                </p>
                            </div>
                            <div class="text-center">
                                <div class="text-5xl mb-3">💎</div>
                                <button onclick="refreshBalance()" class="btn-secondary text-xs">
                                    🔄 Cập nhật
                                </button>
                            </div>
                        </div>
                        <div class="mt-4 pt-4 border-t border-white/20">
                            <p class="text-xs opacity-75">
                                Tỷ giá hiện tại: <strong>1 XU = <?php echo number_format($exchangeRate); ?> VND</strong>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Update the main grid container -->
            <div class="topup-grid">
                
                <!-- LEFT COLUMN - Amount Selection -->
                <div class="order-1">
                    <div class="topup-card p-6">
                        <h3 class="text-xl font-bold text-gray-900 mb-4 flex items-center">
                            💰 <span class="ml-2">Chọn Mức Tiền Nạp</span>
                        </h3>
                        
                        <div class="amount-grid mb-6">
                            <div class="amount-card" onclick="selectAmount(50000)">
                                <div class="amount-value">50K</div>
                                <div class="text-sm text-gray-500 mb-1">VND</div>
                                <div class="xu-badge">
                                    <span class="xu-amount-50k">50</span> XU
                                </div>
                            </div>
                            
                            <div class="amount-card" onclick="selectAmount(100000)">
                                <div class="amount-value">100K</div>
                                <div class="text-sm text-gray-500 mb-1">VND</div>
                                <div class="xu-badge">
                                    <span class="xu-amount-100k">100</span> XU
                                </div>
                            </div>
                            
                            <div class="amount-card" onclick="selectAmount(200000)">
                                <div class="amount-value">200K</div>
                                <div class="text-sm text-gray-500 mb-1">VND</div>
                                <div class="xu-badge">
                                    <span class="xu-amount-200k">200</span> XU
                                </div>
                            </div>
                            
                            <div class="amount-card" onclick="selectAmount(500000)">
                                <div class="amount-value">500K</div>
                                <div class="text-sm text-gray-500 mb-1">VND</div>
                                <div class="xu-badge">
                                    <span class="xu-amount-500k">500</span> XU
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-6">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Hoặc nhập số tiền khác:</label>
                            <input 
                                type="number" 
                                id="customAmount" 
                                placeholder="Nhập số tiền (tối thiểu 10.000 VND)"
                                min="10000"
                                step="1000"
                                class="form-input"
                                oninput="updateAmountDisplay()"
                            >
                            <div id="xuConversion" class="mt-2 text-sm text-gray-600" style="display: none;">
                                = <span id="convertedXU" class="font-bold text-green-600">0</span> XU
                            </div>
                        </div>
                        
                        <!-- Info card with better styling -->
                        <div class="info-card">
                            <div class="flex items-start relative z-10">
                                <svg class="flex-shrink-0 h-6 w-6 mr-3 mt-1" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"></path>
                                </svg>
                                <div>
                                    <p class="text-sm font-semibold mb-1">💡 Lưu ý quan trọng</p>
                                    <p class="text-sm opacity-90 mb-2">
                                        Tiền sẽ được quy đổi sang XU và cộng vào ví trong vòng <strong>1-5 phút</strong> sau khi chuyển khoản thành công.
                                    </p>
                                    <p class="text-xs opacity-75">
                                        Tỷ giá: <strong>1 XU = <?php echo number_format($exchangeRate); ?> VND</strong>
                                    </p>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Quick Link to Wallet History -->
                        <div class="mt-6 p-4 bg-gradient-to-r from-blue-50 to-indigo-50 border border-blue-200 rounded-xl">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-sm font-medium text-blue-800 mb-1">📊 Xem lịch sử giao dịch</p>
                                    <p class="text-xs text-blue-600">Theo dõi chi tiết tất cả giao dịch</p>
                                </div>
                                <a href="<?php echo url('wallet'); ?>" class="btn-secondary text-xs bg-white/80 hover:bg-white">
                                    👀 Xem ngay
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- RIGHT COLUMN -->
                <div class="order-2 space-y-6">
                    
                    <!-- Bank Selection with better styling -->
                    <div class="topup-card p-6">
                        <h3 class="text-xl font-bold text-gray-900 mb-4 flex items-center">
                            🏦 <span class="ml-2">Chọn Ngân Hàng</span>
                        </h3>
                        <div class="bank-grid">
                            <?php foreach ($bankSettings as $bankCode => $bank): ?>
                            <?php if ($bank['status'] === 'active'): ?>
                            <button class="bank-tab" onclick="selectBank('<?php echo $bankCode; ?>')">
                                <div class="flex items-center justify-between relative z-10">
                                    <div class="flex items-center">
                                        <span class="text-2xl mr-3">
                                            <?php 
                                            $bankIcons = ['ACB' => '🏛️', 'VCB' => '🏦', 'MBBANK' => '💳'];
                                            echo $bankIcons[$bankCode] ?? '🏦';
                                            ?>
                                        </span>
                                        <span class="font-semibold"><?php echo htmlspecialchars($bank['bank_name']); ?></span>
                                    </div>
                                    <span class="text-sm opacity-75 font-mono"><?php echo $bankCode; ?></span>
                                </div>
                            </button>
                            <?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Bank Info + QR with improved styling -->
                    <div class="topup-card p-6 bank-info-section" id="bankInfo" style="display: none;">
                        <!-- Bank header -->
                        <div class="text-center mb-6">
                            <div class="inline-flex items-center justify-center w-20 h-20 bg-gradient-to-br from-blue-100 to-blue-200 rounded-2xl mb-4 shadow-lg">
                                <span class="text-3xl" id="bankLogo">💳</span>
                            </div>
                            <h3 class="text-xl font-bold text-gray-900" id="bankName">Chọn ngân hàng</h3>
                        </div>

                        <!-- Bank details -->
                        <div class="space-y-3 mb-6">
                            <div class="info-row">
                                <span class="text-sm font-medium text-gray-600">Số tài khoản</span>
                                <div class="flex items-center">
                                    <span class="font-mono font-bold text-lg" id="accountNumber">-</span>
                                    <button onclick="copyToClipboard('accountNumber')" class="copy-btn ml-2">
                                        📋
                                    </button>
                                </div>
                            </div>

                            <div class="info-row">
                                <span class="text-sm font-medium text-gray-600">Nội dung CK</span>
                                <div class="flex items-center">
                                    <span class="font-mono font-bold text-lg text-red-600" id="transferContent"><?php echo $userPayCode; ?></span>
                                    <button onclick="copyToClipboard('transferContent')" class="copy-btn ml-2">
                                        📋
                                    </button>
                                </div>
                            </div>

                            <div class="info-row">
                                <span class="text-sm font-medium text-gray-600">Chủ TK</span>
                                <span class="font-semibold" id="accountHolder">-</span>
                            </div>

                            <div class="bg-gradient-to-r from-yellow-50 to-orange-50 border-2 border-yellow-200 rounded-xl p-4 text-center">
                                <p class="text-sm font-medium text-yellow-700 mb-2">💰 Số tiền cần chuyển</p>
                                <p class="text-3xl font-bold text-yellow-800 mb-1" id="displayAmount">0 VND</p>
                                <p class="text-sm text-yellow-600">
                                    = <span id="displayXU" class="font-bold">0</span> XU
                                </p>
                            </div>
                        </div>

                        <!-- QR Code with enhanced styling -->
                        <div class="text-center" id="qrCodeSection">
                            <div class="qr-container" id="qrCodeContainer">
                                <img id="qrCodeImage" src="" alt="VietQR QR Code" class="mx-auto" style="width: 200px; height: 200px; display: none;">
                                
                                <div id="qrCodePlaceholder" class="text-center">
                                    <div class="text-6xl text-gray-400 mb-3">📱</div>
                                    <p class="text-lg font-semibold text-gray-600 mb-1">QR Code thanh toán</p>
                                    <p class="text-sm text-gray-500">Chọn số tiền để tạo QR</p>
                                </div>
                            </div>
                            
                            <div class="flex justify-center gap-3 mt-4 qr-buttons">
                                <button id="downloadQRBtn" onclick="downloadQR()" class="btn-primary text-sm" style="display: none;">
                                    ⬇️ Tải QR
                                </button>
                                
                                <button id="refreshQRBtn" onclick="refreshQR()" class="btn-secondary text-sm" style="display: none;">
                                    🔄 Làm mới
                                </button>
                            </div>
                        </div>

                        <div class="mt-6 text-center">
                            <p class="text-xs text-gray-500">
                                🚀 Hệ thống tự động xử lý trong <strong>1-5 phút</strong>
                            </p>
                        </div>
                    </div>

                    <!-- Instructions Card with better design -->
                    <div class="info-card">
                        <div class="flex items-center mb-4 relative z-10">
                            <svg class="w-6 h-6 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                            <h3 class="text-lg font-bold">💡 HƯỚNG DẪN NẠP TIỀN</h3>
                        </div>
                        
                        <div class="space-y-3 text-sm relative z-10">
                            <div class="flex items-start">
                                <span class="flex-shrink-0 w-6 h-6 bg-white/20 rounded-full flex items-center justify-center text-xs font-bold mr-3 mt-0.5">1</span>
                                <p>Chọn số tiền muốn nạp</p>
                            </div>
                            <div class="flex items-start">
                                <span class="flex-shrink-0 w-6 h-6 bg-white/20 rounded-full flex items-center justify-center text-xs font-bold mr-3 mt-0.5">2</span>
                                <p>Chọn ngân hàng của bạn</p>
                            </div>
                            <div class="flex items-start">
                                <span class="flex-shrink-0 w-6 h-6 bg-white/20 rounded-full flex items-center justify-center text-xs font-bold mr-3 mt-0.5">3</span>
                                <p>Chuyển khoản với nội dung: <strong class="text-yellow-200"><?php echo $userPayCode; ?></strong></p>
                            </div>
                            <div class="flex items-start">
                                <span class="flex-shrink-0 w-6 h-6 bg-white/20 rounded-full flex items-center justify-center text-xs font-bold mr-3 mt-0.5">4</span>
                                <p>Hệ thống tự động xử lý và cộng XU</p>
                            </div>
                        </div>
                        
                        <div class="mt-6 pt-4 border-t border-white/20 text-center relative z-10">
                            <p class="text-xs opacity-90">
                                💬 Hỗ trợ: <strong>support@aiboost.vn</strong> | Hotline: <strong>0325.59.59.95</strong>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Include Footer -->
    <?php include __DIR__ . '/partials/footer.php'; ?>

    <script>
        // Global variables
        let selectedAmount = 0;
        let selectedAmountXU = 0;
        let selectedBank = '';
        let userPayCode = '<?php echo $userPayCode; ?>';
        let bankSettings = <?php echo json_encode($bankSettings); ?>;
        let exchangeRate = <?php echo $exchangeRate; ?>;
        let qrCodeImageUrl = '';

        // Page initialization
        document.addEventListener('DOMContentLoaded', function() {
            console.log('✅ Topup page loaded');
            console.log('User pay code:', userPayCode);
            console.log('Exchange rate: 1 XU =', exchangeRate, 'VND');
            console.log('Available banks:', Object.keys(bankSettings));
            
            // Update XU amounts in quick select cards
            updateQuickAmountCards();
            
            // Auto select first bank
            const firstBank = Object.keys(bankSettings)[0];
            if (firstBank) {
                setTimeout(() => {
                    const firstBankTab = document.querySelector('.bank-tab');
                    if (firstBankTab) {
                        firstBankTab.click();
                    }
                }, 500);
            }
            
            // Auto refresh balance and check payment status
            setInterval(() => {
                checkPendingPayment();
            }, 30000); // Every 30 seconds
        });

        function updateQuickAmountCards() {
            // Update XU amounts for quick select cards based on current exchange rate
            document.querySelector('.xu-amount-50k').textContent = Math.floor(50000 / exchangeRate);
            document.querySelector('.xu-amount-100k').textContent = Math.floor(100000 / exchangeRate);
            document.querySelector('.xu-amount-200k').textContent = Math.floor(200000 / exchangeRate);
            document.querySelector('.xu-amount-500k').textContent = Math.floor(500000 / exchangeRate);
        }

        function selectAmount(amount) {
            selectedAmount = amount;
            selectedAmountXU = Math.floor(amount / exchangeRate);
            
            // Update UI
            document.querySelectorAll('.amount-card').forEach(card => {
                card.classList.remove('selected');
            });
            event.target.closest('.amount-card').classList.add('selected');
            
            document.getElementById('customAmount').value = amount;
            updateAmountDisplay();
        }

        function updateAmountDisplay() {
            const amount = document.getElementById('customAmount').value;
            selectedAmount = parseInt(amount) || 0;
            selectedAmountXU = selectedAmount > 0 ? Math.floor(selectedAmount / exchangeRate) : 0;
            
            // Update display amount
            document.getElementById('displayAmount').textContent = 
                selectedAmount ? selectedAmount.toLocaleString() + ' VND' : '0 VND';
            
            document.getElementById('displayXU').textContent = 
                selectedAmountXU ? selectedAmountXU.toLocaleString() : '0';
            
            // Show XU conversion
            if (selectedAmount > 0) {
                document.getElementById('xuConversion').style.display = 'block';
                document.getElementById('convertedXU').textContent = selectedAmountXU.toLocaleString();
            } else {
                document.getElementById('xuConversion').style.display = 'none';
            }
            
            // Update transfer content (keep simple - just pay code)
            if (selectedBank && userPayCode) {
                document.getElementById('transferContent').textContent = userPayCode;
                
                // Generate QR code if amount > 0
                if (selectedAmount > 0) {
                    generateQRCode();
                } else {
                    hideQRCode();
                }
            }
        }

        function selectBank(bank) {
            if (!bankSettings[bank] || bankSettings[bank].status !== 'active') {
                alert('Ngân hàng này hiện không khả dụng');
                return;
            }
            
            selectedBank = bank;
            
            // Update bank tab UI
            document.querySelectorAll('.bank-tab').forEach(tab => {
                tab.classList.remove('active');
            });
            event.target.classList.add('active');
            
            // Show bank info
            document.getElementById('bankInfo').style.display = 'block';
            
            // Update bank info
            const settings = bankSettings[bank];
            document.getElementById('bankName').textContent = settings.bank_name;
            document.getElementById('accountNumber').textContent = settings.account_number;
            document.getElementById('accountHolder').textContent = settings.account_holder;
            document.getElementById('transferContent').textContent = userPayCode;
            
            // Set bank logo
            const logos = {
                'ACB': '🏛️',
                'VCB': '🏦', 
                'MBBANK': '💳'
            };
            document.getElementById('bankLogo').textContent = logos[bank] || '🏦';
            
            // Generate QR code if amount is selected
            if (selectedAmount > 0) {
                generateQRCode();
            }
        }

        function generateQRCode() {
            if (!selectedBank || !selectedAmount || selectedAmount <= 0) {
                hideQRCode();
                return;
            }
            
            const settings = bankSettings[selectedBank];
            if (!settings) {
                hideQRCode();
                return;
            }
            
            // Build VietQR URL with simple pay code
            const bankCode = selectedBank;
            const accountNo = settings.account_number;
            const accountName = encodeURIComponent(settings.account_holder);
            const amount = selectedAmount;
            const info = encodeURIComponent(userPayCode); // Just the pay code
            const template = 'compact';
            
            qrCodeImageUrl = `https://img.vietqr.io/image/${bankCode}-${accountNo}-${template}.jpg?amount=${amount}&addInfo=${info}&accountName=${accountName}`;
            
            showQRCode();
        }

        function showQRCode() {
            const qrImage = document.getElementById('qrCodeImage');
            const placeholder = document.getElementById('qrCodePlaceholder');
            const downloadBtn = document.getElementById('downloadQRBtn');
            const refreshBtn = document.getElementById('refreshQRBtn');
            const container = document.getElementById('qrCodeContainer');
            
            qrImage.src = qrCodeImageUrl;
            qrImage.style.display = 'block';
            placeholder.style.display = 'none';
            downloadBtn.style.display = 'inline-block';
            refreshBtn.style.display = 'inline-block';
            
            container.classList.add('active');
            
            qrImage.onerror = function() {
                console.error('Failed to load QR code');
                hideQRCode();
                alert('Không thể tạo mã QR. Vui lòng thử lại.');
            };
        }

        function hideQRCode() {
            const qrImage = document.getElementById('qrCodeImage');
            const placeholder = document.getElementById('qrCodePlaceholder');
            const downloadBtn = document.getElementById('downloadQRBtn');
            const refreshBtn = document.getElementById('refreshQRBtn');
            const container = document.getElementById('qrCodeContainer');
            
            qrImage.style.display = 'none';
            placeholder.style.display = 'block';
            downloadBtn.style.display = 'none';
            refreshBtn.style.display = 'none';
            qrCodeImageUrl = '';
            
            container.classList.remove('active');
        }

        function refreshQR() {
            if (selectedBank && selectedAmount > 0) {
                generateQRCode();
                
                const btn = document.getElementById('refreshQRBtn');
                const originalText = btn.textContent;
                btn.textContent = '⏳ Đang tạo...';
                btn.disabled = true;
                
                setTimeout(() => {
                    btn.textContent = originalText;
                    btn.disabled = false;
                }, 1000);
            }
        }

        function downloadQR() {
            if (!qrCodeImageUrl) {
                alert('Chưa có mã QR để tải xuống');
                return;
            }
            
            const btn = document.getElementById('downloadQRBtn');
            const originalText = btn.textContent;
            btn.textContent = '⏳ Đang tải...';
            btn.disabled = true;
            
            fetch(qrCodeImageUrl)
                .then(response => response.blob())
                .then(blob => {
                    const url = window.URL.createObjectURL(blob);
                    const link = document.createElement('a');
                    link.href = url;
                    link.setAttribute('download', `QR_Payment_${userPayCode}_${selectedAmount}.png`);
                    document.body.appendChild(link);
                    link.click();
                    document.body.removeChild(link);
                    window.URL.revokeObjectURL(url);
                    
                    btn.textContent = '✅ Đã tải!';
                    setTimeout(() => {
                        btn.textContent = originalText;
                        btn.disabled = false;
                    }, 2000);
                })
                .catch(error => {
                    console.error('Error downloading QR:', error);
                    alert('Không thể tải xuống');
                    
                    btn.textContent = originalText;
                    btn.disabled = false;
                });
        }

        function copyToClipboard(elementId) {
            const element = document.getElementById(elementId);
            const text = element.textContent;
            
            navigator.clipboard.writeText(text).then(() => {
                const originalText = element.textContent;
                element.textContent = '✅ Đã copy!';
                element.style.color = 'green';
                
                setTimeout(() => {
                    element.textContent = originalText;
                    element.style.color = '';
                }, 1500);
            }).catch(err => {
                console.error('Copy failed:', err);
                alert('Không thể copy. Vui lòng copy thủ công.');
            });
        }

        function refreshBalance() {
            const btn = event.target;
            btn.disabled = true;
            btn.textContent = '⏳';
            
            fetch('?action=refresh_balance')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        updateBalanceDisplay(data.balance_xu);
                        
                        // Show success animation
                        btn.textContent = '✅';
                        setTimeout(() => {
                            btn.textContent = '🔄 Cập nhật';
                            btn.disabled = false;
                        }, 1500);
                    }
                })
                .catch(error => {
                    console.error('Error refreshing balance:', error);
                    btn.textContent = '❌';
                    setTimeout(() => {
                        btn.textContent = '🔄 Cập nhật';
                        btn.disabled = false;
                    }, 1500);
                });
        }

        function updateBalanceDisplay(balanceXU) {
            document.getElementById('currentBalanceXU').textContent = Number(balanceXU).toLocaleString();
            document.getElementById('currentBalanceVND').textContent = Number(balanceXU * exchangeRate).toLocaleString();
        }

        function checkPendingPayment() {
            fetch('?action=check_pending_payment')
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.has_pending) {
                        console.log('Found pending payment:', data);
                        // Refresh balance
                        refreshBalance();
                    }
                })
                .catch(error => {
                    console.error('Error checking payment:', error);
                });
        }

        console.log('✅ Topup.php loaded successfully!');
        console.log('Current balance: <?php echo number_format($currentBalanceXU); ?> XU');
        console.log('Exchange rate: 1 XU =', exchangeRate, 'VND');
        console.log('Pay code:', userPayCode);
    </script>
</body>
</html>