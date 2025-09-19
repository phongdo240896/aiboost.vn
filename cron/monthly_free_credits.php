<?php
/**
 * CRON JOB: TẶNG 500 XU MIỄN PHÍ HÀNG THÁNG
 * Chạy mỗi ngày để kiểm tra và reset XU cho tài khoản free
 * Chỉ reset vào đúng ngày đăng ký hàng tháng
 */

require_once __DIR__ . '/../app/config.php';
require_once __DIR__ . '/../app/db.php';

// Cấu hình
define('FREE_MONTHLY_CREDITS', 500);
define('DEBUG_MODE', isset($_GET['test']));

if (DEBUG_MODE) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
    echo "<pre style='font-family: monospace; background: #1e1e1e; color: #d4d4d4; padding: 20px;'>";
}

$stats = [
    'processed' => 0,
    'skipped' => 0,
    'errors' => 0,
    'credits_given' => 0
];

$startTime = microtime(true);
logMessage("╔══════════════════════════════════════════════════════════╗");
logMessage("║        MONTHLY FREE CREDITS - " . date('Y-m-d H:i:s') . "        ║");
logMessage("╚══════════════════════════════════════════════════════════╝\n");

function logMessage($message, $type = 'info') {
    $prefix = [
        'info' => '📌',
        'success' => '✅', 
        'warning' => '⚠️',
        'error' => '❌',
        'user' => '👤',
        'credits' => '💰',
        'gift' => '🎁'
    ];
    
    $icon = $prefix[$type] ?? '▸';
    $logMsg = "$icon $message";
    
    error_log($logMsg);
    if (DEBUG_MODE) {
        $color = [
            'success' => '#4caf50',
            'error' => '#f44336',
            'warning' => '#ff9800',
            'credits' => '#ffc107',
            'gift' => '#e91e63'
        ];
        $style = isset($color[$type]) ? "color: {$color[$type]};" : "";
        echo "<span style='$style'>$logMsg</span>\n";
    }
}

class FreeCreditsProcessor {
    private $db;
    private $stats;
    
    public function __construct($db, &$stats) {
        $this->db = $db;
        $this->stats = &$stats;
    }
    
    /**
     * Lấy danh sách user free cần reset XU
     */
    public function getFreeUsersForReset() {
        $today = date('Y-m-d');
        
        // Tìm user:
        // 1. Không có subscription active
        // 2. Đã đến ngày reset (theo ngày đăng ký)
        // 3. Chưa được cấp XU hôm nay
        $sql = "SELECT 
                    u.id,
                    u.email,
                    u.full_name,
                    u.created_at as register_date,
                    DAY(u.created_at) as register_day,
                    w.balance as current_balance,
                    MAX(wt.created_at) as last_free_credit_date
                FROM users u
                LEFT JOIN subscriptions s ON u.id = s.user_id 
                    AND s.status = 'active' 
                    AND s.end_date > NOW()
                LEFT JOIN wallets w ON u.id = w.user_id
                LEFT JOIN wallet_transactions wt ON u.id = wt.user_id 
                    AND wt.description LIKE '%Tặng XU miễn phí hàng tháng%'
                    AND DATE(wt.created_at) = ?
                WHERE s.id IS NULL -- Không có subscription active
                AND (
                    DAY(CURDATE()) = DAY(u.created_at) -- Đúng ngày đăng ký hàng tháng
                    OR u.created_at >= DATE_SUB(CURDATE(), INTERVAL 1 DAY) -- User mới đăng ký hôm qua/hôm nay
                )
                AND (wt.id IS NULL OR DATE(wt.created_at) != ?) -- Chưa nhận XU hôm nay
                GROUP BY u.id
                ORDER BY u.created_at ASC";
        
        $users = $this->db->query($sql, [$today, $today]);
        
        logMessage("Tìm thấy " . count($users) . " user free cần reset XU", 'info');
        
        return $users;
    }
    
    /**
     * Cấp XU miễn phí cho user
     */
    public function giveFreeCreditToUser($user) {
        $userId = $user['id'];
        $userEmail = $user['email'];
        $currentBalance = (int)($user['current_balance'] ?? 0);
        $registerDate = $user['register_date'];
        
        logMessage("\n┌─ User: $userEmail", 'user');
        logMessage("├─ ID: $userId", 'info');
        logMessage("├─ Ngày đăng ký: " . date('d/m/Y', strtotime($registerDate)), 'info');
        logMessage("├─ Số dư hiện tại: " . number_format($currentBalance) . " XU", 'credits');
        
        $this->db->getPdo()->beginTransaction();
        
        try {
            // 1. Tạo/cập nhật wallet với số XU mới (RESET, không cộng dồn)
            $newBalance = FREE_MONTHLY_CREDITS;
            
            $walletExists = $this->db->query("SELECT id FROM wallets WHERE user_id = ?", [$userId]);
            
            if (empty($walletExists)) {
                // Tạo wallet mới
                $sql = "INSERT INTO wallets (user_id, balance, created_at, updated_at) 
                        VALUES (?, ?, NOW(), NOW())";
                $this->db->getPdo()->prepare($sql)->execute([$userId, $newBalance]);
                logMessage("├─ Tạo ví mới với $newBalance XU", 'success');
            } else {
                // Reset balance (không cộng dồn)
                $sql = "UPDATE wallets SET balance = ?, updated_at = NOW() WHERE user_id = ?";
                $this->db->getPdo()->prepare($sql)->execute([$newBalance, $userId]);
                logMessage("├─ Reset ví thành $newBalance XU (không cộng dồn)", 'success');
            }
            
            // 2. Tạo wallet transaction
            $transactionId = 'FREE_' . date('Ymd') . '_' . $userId . '_' . uniqid();
            
            $sql = "INSERT INTO wallet_transactions 
                    (transaction_id, user_id, type, amount_vnd, amount_xu, exchange_rate,
                     balance_before, balance_after, description, status, created_at)
                    VALUES (?, ?, 'system_gift', 0, ?, 0, ?, ?, ?, 'completed', NOW())";
            
            $description = "🎁 Tặng XU miễn phí hàng tháng - Reset " . FREE_MONTHLY_CREDITS . " XU";
            
            $stmt = $this->db->getPdo()->prepare($sql);
            $stmt->execute([
                $transactionId,
                $userId,
                FREE_MONTHLY_CREDITS,
                $currentBalance,
                $newBalance,
                $description
            ]);
            
            // 3. Log vào bảng riêng (optional)
            $this->logFreeCreditsGiven($userId, $currentBalance, $newBalance);
            
            $this->db->getPdo()->commit();
            
            logMessage("├─ 💰 Đã reset thành " . number_format($newBalance) . " XU", 'gift');
            logMessage("└─ ✅ THÀNH CÔNG!", 'success');
            
            $this->stats['processed']++;
            $this->stats['credits_given'] += FREE_MONTHLY_CREDITS;
            
        } catch (Exception $e) {
            $this->db->getPdo()->rollBack();
            logMessage("└─ ❌ LỖI: " . $e->getMessage(), 'error');
            $this->stats['errors']++;
        }
    }
    
    /**
     * Log lịch sử tặng XU miễn phí
     */
    private function logFreeCreditsGiven($userId, $balanceBefore, $balanceAfter) {
        try {
            // Tạo bảng nếu chưa có
            $this->db->getPdo()->exec("
                CREATE TABLE IF NOT EXISTS free_credits_log (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    user_id VARCHAR(50) NOT NULL,
                    credits_given INT NOT NULL,
                    balance_before INT NOT NULL,
                    balance_after INT NOT NULL,
                    reset_date DATE NOT NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    INDEX idx_user_date (user_id, reset_date)
                )
            ");
            
            $sql = "INSERT INTO free_credits_log 
                    (user_id, credits_given, balance_before, balance_after, reset_date)
                    VALUES (?, ?, ?, ?, CURDATE())";
            
            $stmt = $this->db->getPdo()->prepare($sql);
            $stmt->execute([$userId, FREE_MONTHLY_CREDITS, $balanceBefore, $balanceAfter]);
            
        } catch (Exception $e) {
            // Không quan trọng lắm, chỉ log
            logMessage("Warning: Could not log free credits: " . $e->getMessage(), 'warning');
        }
    }
    
    /**
     * Kiểm tra user có đủ điều kiện nhận XU miễn phí không
     */
    public function isEligibleForFreeCredits($user) {
        $userId = $user['id'];
        $registerDate = $user['register_date'];
        $today = date('Y-m-d');
        
        // Kiểm tra đã nhận XU hôm nay chưa
        $sql = "SELECT id FROM wallet_transactions 
                WHERE user_id = ? 
                AND type = 'system_gift'
                AND description LIKE '%Tặng XU miễn phí hàng tháng%'
                AND DATE(created_at) = ?";
        
        $alreadyReceived = $this->db->query($sql, [$userId, $today]);
        
        if (!empty($alreadyReceived)) {
            logMessage("User $userId đã nhận XU hôm nay", 'warning');
            return false;
        }
        
        // Kiểm tra có subscription active không
        $sql = "SELECT id FROM subscriptions 
                WHERE user_id = ? 
                AND status = 'active' 
                AND end_date > NOW()";
        
        $hasActiveSub = $this->db->query($sql, [$userId]);
        
        if (!empty($hasActiveSub)) {
            logMessage("User $userId có subscription active", 'warning');
            return false;
        }
        
        return true;
    }
}

// MAIN EXECUTION
try {
    $processor = new FreeCreditsProcessor($db, $stats);
    $freeUsers = $processor->getFreeUsersForReset();
    
    if (empty($freeUsers)) {
        logMessage("Không có user nào cần reset XU hôm nay", 'info');
    } else {
        foreach ($freeUsers as $user) {
            if ($processor->isEligibleForFreeCredits($user)) {
                $processor->giveFreeCreditToUser($user);
            } else {
                logMessage("User {$user['email']} không đủ điều kiện", 'warning');
                $stats['skipped']++;
            }
        }
    }
    
} catch (Exception $e) {
    logMessage("\n❌ LỖI NGHIÊM TRỌNG: " . $e->getMessage(), 'error');
    $stats['errors']++;
}

// Tổng kết
$duration = round(microtime(true) - $startTime, 2);
logMessage("\n" . str_repeat("═", 60), 'info');
logMessage("TỔNG KẾT TẶNG XU MIỄN PHÍ", 'gift');
logMessage(str_repeat("═", 60), 'info');
logMessage("✓ Xử lý thành công: {$stats['processed']} user", 'success');
logMessage("🎁 Tổng XU đã tặng: " . number_format($stats['credits_given']) . " XU", 'gift');
logMessage("⚠ Bỏ qua: {$stats['skipped']} user", 'warning');
logMessage("✗ Lỗi: {$stats['errors']}", 'error');
logMessage("⏱ Thời gian: {$duration}s", 'info');

if (DEBUG_MODE) {
    echo "</pre>";
}
?>