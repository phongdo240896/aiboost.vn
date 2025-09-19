<?php
/**
 * Notification Manager Class
 * Quản lý hệ thống thông báo
 */
class NotificationManager {
    private static $db;
    
    /**
     * Initialize với database connection
     */
    public static function init($database) {
        self::$db = $database;
    }
    
    /**
     * Tạo thông báo mới
     */
    public static function create($data) {
        try {
            // Validate required fields
            if (empty($data['title']) || empty($data['content'])) {
                return ['success' => false, 'message' => 'Tiêu đề và nội dung không được để trống'];
            }
            
            // Set default icon and color based on type
            $type = $data['type'] ?? 'info';
            $defaultIcon = self::getIconByType($type);
            $defaultColor = self::getColorByType($type);
            
            // Prepare notification data
            $notificationData = [
                'title' => $data['title'],
                'content' => $data['content'],
                'type' => $type,
                'icon' => $defaultIcon,  // Always use default icon
                'color' => $defaultColor,  // Always use default color
                'target_users' => $data['target_users'] ?? 'all',
                'target_role' => null,  // Removed role-based targeting
                'target_user_ids' => $data['selected_users'] ? implode(',', $data['selected_users']) : ($data['target_user_ids'] ?? null),
                'priority' => $data['priority'] ?? 0,
                'url' => null,  // Removed URL field
                'is_popup' => isset($data['is_popup']) ? (int)$data['is_popup'] : 1,
                'is_email' => isset($data['is_email']) ? (int)$data['is_email'] : 0,
                'created_by' => $data['created_by'] ?? $_SESSION['user_name'] ?? 'System',
                'status' => $data['status'] ?? 'active',
                'expires_at' => $data['expires_at'] ?? null
            ];
            
            // Insert notification
            $pdo = self::$db->getPdo();
            $sql = "INSERT INTO notifications (title, content, type, icon, color, target_users, target_role, 
                    target_user_ids, priority, url, is_popup, is_email, created_by, status, expires_at) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            
            $stmt = $pdo->prepare($sql);
            $result = $stmt->execute([
                $notificationData['title'],
                $notificationData['content'],
                $notificationData['type'],
                $notificationData['icon'],
                $notificationData['color'],
                $notificationData['target_users'],
                $notificationData['target_role'],
                $notificationData['target_user_ids'],
                $notificationData['priority'],
                $notificationData['url'],
                $notificationData['is_popup'],
                $notificationData['is_email'],
                $notificationData['created_by'],
                $notificationData['status'],
                $notificationData['expires_at']
            ]);
            
            if (!$result) {
                throw new Exception('Không thể tạo thông báo');
            }
            
            $notificationId = $pdo->lastInsertId();
            
            // Tạo user_notifications dựa trên target
            self::createUserNotifications($notificationId, $notificationData);
            
            // Gửi email nếu được chọn
            if ($notificationData['is_email']) {
                self::sendEmailNotifications($notificationId, $notificationData);
            }
            
            return [
                'success' => true,
                'message' => 'Tạo thông báo thành công',
                'notification_id' => $notificationId
            ];
            
        } catch (Exception $e) {
            error_log("Create notification error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Lỗi: ' . $e->getMessage()];
        }
    }
    
    /**
     * Tạo user_notifications cho các user được target
     */
    private static function createUserNotifications($notificationId, $notificationData) {
        try {
            $pdo = self::$db->getPdo();
            $userIds = [];
            
            switch ($notificationData['target_users']) {
                case 'all':
                    // Lấy tất cả user active
                    $stmt = $pdo->query("SELECT id FROM users WHERE status = 'active'");
                    $userIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
                    break;
                    
                case 'role':
                    // Lấy user theo role
                    if (!empty($notificationData['target_role'])) {
                        $stmt = $pdo->prepare("SELECT id FROM users WHERE role = ? AND status = 'active'");
                        $stmt->execute([$notificationData['target_role']]);
                        $userIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
                    }
                    break;
                    
                case 'specific':
                    // User IDs cụ thể
                    if (!empty($notificationData['target_user_ids'])) {
                        $userIds = explode(',', $notificationData['target_user_ids']);
                        $userIds = array_map('trim', $userIds);
                    }
                    break;
            }
            
            // Batch insert user_notifications
            if (!empty($userIds)) {
                $sql = "INSERT INTO user_notifications (notification_id, user_id) VALUES ";
                $values = [];
                $params = [];
                
                foreach ($userIds as $userId) {
                    $values[] = "(?, ?)";
                    $params[] = $notificationId;
                    $params[] = $userId;
                }
                
                $sql .= implode(', ', $values);
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
            }
            
            return true;
            
        } catch (Exception $e) {
            error_log("Create user notifications error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Send email notifications
     */
    public static function sendEmailNotifications($notificationId, $notificationData = null) {
        try {
            // Load EmailService nếu chưa có
            if (!class_exists('App\EmailService')) {
                require_once __DIR__ . '/../vendor/autoload.php';
            }
            
            $emailService = \App\EmailService::getInstance();
            $pdo = self::$db->getPdo();
            
            // Nếu không có notificationData, load từ database
            if (!$notificationData) {
                $stmt = $pdo->prepare("SELECT * FROM notifications WHERE id = ?");
                $stmt->execute([$notificationId]);
                $notificationData = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$notificationData) {
                    return ['success' => false, 'message' => 'Notification not found'];
                }
            }
            
            // Check if email sending is enabled
            if (!$notificationData['is_email']) {
                return ['success' => false, 'message' => 'Email sending not enabled for this notification'];
            }
            
            // Get target users với email
            $userIds = [];
            $users = [];
            
            if ($notificationData['target_users'] === 'all') {
                // Get all active users với email
                $stmt = $pdo->query("
                    SELECT id, email, full_name, 
                           COALESCE(full_name, email) as display_name 
                    FROM users 
                    WHERE status = 'active' 
                    AND email IS NOT NULL 
                    AND email != ''
                ");
                $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
            } elseif ($notificationData['target_users'] === 'specific') {
                // Get specific users
                if (!empty($notificationData['target_user_ids'])) {
                    $userIds = explode(',', $notificationData['target_user_ids']);
                    $placeholders = str_repeat('?,', count($userIds) - 1) . '?';
                    
                    $stmt = $pdo->prepare("
                        SELECT id, email, full_name,
                               COALESCE(full_name, email) as display_name 
                        FROM users 
                        WHERE id IN ($placeholders) 
                        AND email IS NOT NULL 
                        AND email != ''
                    ");
                    $stmt->execute($userIds);
                    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
                }
            }
            
            // Prepare email content với template đẹp
            $emailSubject = '[AIboost.vn] ' . $notificationData['title'];
            $emailContent = self::getEmailTemplate($notificationData);
            
            // Track results
            $sent = 0;
            $failed = 0;
            $errors = [];
            
            // Send email to each user
            foreach ($users as $user) {
                try {
                    // Gửi email sử dụng sendNotificationEmail method
                    $result = $emailService->sendNotificationEmail(
                        $user['email'],
                        $user['display_name'],
                        $emailSubject,
                        $notificationData['content'],
                        $user['id'],
                        $notificationId
                    );
                    
                    if ($result['success']) {
                        $sent++;
                        
                        // Log success to database
                        $stmt = $pdo->prepare("
                            INSERT INTO email_logs 
                            (user_id, notification_id, email, subject, status, sent_at) 
                            VALUES (?, ?, ?, ?, 'sent', NOW())
                        ");
                        $stmt->execute([
                            $user['id'],
                            $notificationId,
                            $user['email'],
                            $emailSubject
                        ]);
                    } else {
                        $failed++;
                        $errors[] = $user['email'] . ': ' . $result['message'];
                        
                        // Log failure to database
                        $stmt = $pdo->prepare("
                            INSERT INTO email_logs 
                            (user_id, notification_id, email, subject, status, error_message, sent_at) 
                            VALUES (?, ?, ?, ?, 'failed', ?, NOW())
                        ");
                        $stmt->execute([
                            $user['id'],
                            $notificationId,
                            $user['email'],
                            $emailSubject,
                            $result['message']
                        ]);
                    }
                    
                    // Delay một chút để tránh spam
                    usleep(100000); // 100ms delay between emails
                    
                } catch (Exception $e) {
                    $failed++;
                    $errors[] = $user['email'] . ': ' . $e->getMessage();
                    error_log("Email send error for user {$user['id']}: " . $e->getMessage());
                }
            }
            
            // Update notification với kết quả
            $stmt = $pdo->prepare("
                UPDATE notifications 
                SET email_sent_count = ?, 
                    email_sent_at = NOW(),
                    updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$sent, $notificationId]);
            
            // Return results
            return [
                'success' => true,
                'sent' => $sent,
                'failed' => $failed,
                'total' => count($users),
                'errors' => array_slice($errors, 0, 10) // Limit errors shown
            ];
            
        } catch (Exception $e) {
            error_log("Send email notifications error: " . $e->getMessage());
            return [
                'success' => false, 
                'message' => 'Lỗi gửi email: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Get beautiful email template
     */
    private static function getEmailTemplate($notification) {
        $typeColors = [
            'info' => '#3b82f6',
            'success' => '#10b981',
            'warning' => '#f59e0b',
            'error' => '#ef4444',
            'promotion' => '#8b5cf6'
        ];
        
        $typeIcons = [
            'info' => '📢',
            'success' => '✅',
            'warning' => '⚠️',
            'error' => '❌',
            'promotion' => '🎁'
        ];
        
        $color = $typeColors[$notification['type']] ?? '#667eea';
        $icon = $typeIcons[$notification['type']] ?? '📢';
        
        return '
        <!DOCTYPE html>
        <html lang="vi">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <style>
                body {
                    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Arial, sans-serif;
                    line-height: 1.6;
                    color: #333;
                    margin: 0;
                    padding: 0;
                    background-color: #f5f5f5;
                }
                .wrapper {
                    padding: 40px 20px;
                }
                .container {
                    max-width: 600px;
                    margin: 0 auto;
                    background: white;
                    border-radius: 12px;
                    overflow: hidden;
                    box-shadow: 0 2px 20px rgba(0,0,0,0.1);
                }
                .header {
                    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                    color: white;
                    padding: 30px;
                    text-align: center;
                }
                .header h1 {
                    margin: 0;
                    font-size: 24px;
                }
                .notification-badge {
                    display: inline-block;
                    background: ' . $color . ';
                    color: white;
                    padding: 8px 16px;
                    border-radius: 20px;
                    font-size: 14px;
                    font-weight: 500;
                    margin-top: 15px;
                }
                .content {
                    padding: 30px;
                }
                .content h2 {
                    color: #1f2937;
                    margin-top: 0;
                    font-size: 20px;
                }
                .content p {
                    color: #4b5563;
                    line-height: 1.8;
                }
                .button {
                    display: inline-block;
                    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                    color: white;
                    text-decoration: none;
                    padding: 12px 30px;
                    border-radius: 8px;
                    font-weight: 500;
                    margin-top: 20px;
                }
                .footer {
                    background: #f9fafb;
                    padding: 20px 30px;
                    text-align: center;
                    color: #6b7280;
                    font-size: 13px;
                    border-top: 1px solid #e5e7eb;
                }
                .footer a {
                    color: #667eea;
                    text-decoration: none;
                }
            </style>
        </head>
        <body>
            <div class="wrapper">
                <div class="container">
                    <div class="header">
                        <h1>🚀 AIboost.vn</h1>
                        <p style="margin: 10px 0 0; opacity: 0.9;">Nền tảng AI hàng đầu Việt Nam</p>
                        <div class="notification-badge">
                            ' . $icon . ' ' . ucfirst($notification['type']) . ' Notification
                        </div>
                    </div>
                    <div class="content">
                        <h2>' . htmlspecialchars($notification['title']) . '</h2>
                        <p>' . nl2br(htmlspecialchars($notification['content'])) . '</p>
                        
                        <center>
                            <a href="https://aiboost.vn/notifications" class="button">
                                Xem chi tiết →
                            </a>
                        </center>
                    </div>
                    <div class="footer">
                        <p>
                            Bạn nhận được email này vì đã đăng ký nhận thông báo từ AIboost.vn<br>
                            <a href="https://aiboost.vn/unsubscribe">Hủy đăng ký</a> | 
                            <a href="https://aiboost.vn/settings">Cài đặt thông báo</a>
                        </p>
                        <p style="margin-top: 15px;">
                            © 2025 AIboost.vn - All rights reserved<br>
                            📧 support@aiboost.vn | 🌐 aiboost.vn
                        </p>
                    </div>
                </div>
            </div>
        </body>
        </html>';
    }
    
    /**
     * Lấy danh sách thông báo của user
     */
    public static function getUserNotifications($userId, $page = 1, $limit = 20, $filter = 'all') {
        try {
            $pdo = self::$db->getPdo();
            $offset = ($page - 1) * $limit;
            
            // Build query
            $sql = "SELECT n.*, un.is_read, un.read_at, un.created_at as received_at
                    FROM notifications n
                    INNER JOIN user_notifications un ON n.id = un.notification_id
                    WHERE un.user_id = ? 
                    AND n.status = 'active'
                    AND (n.expires_at IS NULL OR n.expires_at > NOW())";
            
            $params = [$userId];
            
            // Apply filter
            if ($filter === 'unread') {
                $sql .= " AND un.is_read = 0";
            } elseif ($filter === 'read') {
                $sql .= " AND un.is_read = 1";
            }
            
            // Get total count
            $countSql = str_replace('SELECT n.*, un.is_read, un.read_at, un.created_at as received_at', 'SELECT COUNT(*)', $sql);
            $stmt = $pdo->prepare($countSql);
            $stmt->execute($params);
            $total = $stmt->fetchColumn();
            
            // Get notifications
            $sql .= " ORDER BY n.priority DESC, n.created_at DESC LIMIT ? OFFSET ?";
            $params[] = $limit;
            $params[] = $offset;
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            return [
                'success' => true,
                'notifications' => $notifications,
                'total' => $total,
                'page' => $page,
                'total_pages' => ceil($total / $limit)
            ];
            
        } catch (Exception $e) {
            error_log("Get user notifications error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Lỗi: ' . $e->getMessage()];
        }
    }
    
    /**
     * Đếm số thông báo chưa đọc
     */
    public static function getUnreadCount($userId) {
        try {
            $pdo = self::$db->getPdo();
            
            $sql = "SELECT COUNT(*) FROM user_notifications un
                    INNER JOIN notifications n ON n.id = un.notification_id
                    WHERE un.user_id = ? 
                    AND un.is_read = 0
                    AND n.status = 'active'
                    AND (n.expires_at IS NULL OR n.expires_at > NOW())";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$userId]);
            
            return (int)$stmt->fetchColumn();
            
        } catch (Exception $e) {
            error_log("Get unread count error: " . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * Đánh dấu thông báo đã đọc
     */
    public static function markAsRead($notificationId, $userId) {
        try {
            $pdo = self::$db->getPdo();
            
            $sql = "UPDATE user_notifications 
                    SET is_read = 1, read_at = NOW() 
                    WHERE notification_id = ? AND user_id = ? AND is_read = 0";
            
            $stmt = $pdo->prepare($sql);
            $result = $stmt->execute([$notificationId, $userId]);
            
            return ['success' => true, 'message' => 'Đã đánh dấu là đã đọc'];
            
        } catch (Exception $e) {
            error_log("Mark as read error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Lỗi: ' . $e->getMessage()];
        }
    }
    
    /**
     * Đánh dấu tất cả thông báo đã đọc
     */
    public static function markAllAsRead($userId) {
        try {
            $pdo = self::$db->getPdo();
            
            $sql = "UPDATE user_notifications 
                    SET is_read = 1, read_at = NOW() 
                    WHERE user_id = ? AND is_read = 0";
            
            $stmt = $pdo->prepare($sql);
            $result = $stmt->execute([$userId]);
            
            return ['success' => true, 'message' => 'Đã đánh dấu tất cả là đã đọc'];
            
        } catch (Exception $e) {
            error_log("Mark all as read error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Lỗi: ' . $e->getMessage()];
        }
    }
    
    /**
     * Lấy thông báo cho popup (chưa hiển thị)
     */
    public static function getPopupNotification($userId) {
        try {
            $pdo = self::$db->getPdo();
            
            $sql = "SELECT n.*, un.id as user_notification_id
                    FROM notifications n
                    INNER JOIN user_notifications un ON n.id = un.notification_id
                    WHERE un.user_id = ? 
                    AND un.is_popup_shown = 0
                    AND n.is_popup = 1
                    AND n.status = 'active'
                    AND (n.expires_at IS NULL OR n.expires_at > NOW())
                    ORDER BY n.priority DESC, n.created_at DESC
                    LIMIT 1";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$userId]);
            $notification = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($notification) {
                // Đánh dấu đã hiển thị popup
                $updateSql = "UPDATE user_notifications SET is_popup_shown = 1 WHERE id = ?";
                $updateStmt = $pdo->prepare($updateSql);
                $updateStmt->execute([$notification['user_notification_id']]);
            }
            
            return $notification;
            
        } catch (Exception $e) {
            error_log("Get popup notification error: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Lấy danh sách thông báo cho admin
     */
    public static function getAdminNotifications($page = 1, $limit = 20, $filter = []) {
        try {
            $pdo = self::$db->getPdo();
            $offset = ($page - 1) * $limit;
            
            $sql = "SELECT n.*, 
                    (SELECT COUNT(*) FROM user_notifications WHERE notification_id = n.id) as total_recipients,
                    (SELECT COUNT(*) FROM user_notifications WHERE notification_id = n.id AND is_read = 1) as total_read
                    FROM notifications n WHERE 1=1";
            
            $params = [];
            
            // Apply filters
            if (!empty($filter['type'])) {
                $sql .= " AND n.type = ?";
                $params[] = $filter['type'];
            }
            
            if (!empty($filter['status'])) {
                $sql .= " AND n.status = ?";
                $params[] = $filter['status'];
            }
            
            // Get total count
            $countSql = str_replace('SELECT n.*, (SELECT COUNT(*) FROM user_notifications WHERE notification_id = n.id) as total_recipients, (SELECT COUNT(*) FROM user_notifications WHERE notification_id = n.id AND is_read = 1) as total_read', 'SELECT COUNT(*)', $sql);
            $stmt = $pdo->prepare($countSql);
            $stmt->execute($params);
            $total = $stmt->fetchColumn();
            
            // Get notifications
            $sql .= " ORDER BY n.created_at DESC LIMIT ? OFFSET ?";
            $params[] = $limit;
            $params[] = $offset;
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            return [
                'success' => true,
                'notifications' => $notifications,
                'total' => $total,
                'page' => $page,
                'total_pages' => ceil($total / $limit)
            ];
            
        } catch (Exception $e) {
            error_log("Get admin notifications error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Lỗi: ' . $e->getMessage()];
        }
    }
    
    /**
     * Xóa thông báo (admin)
     */
    public static function delete($notificationId) {
        try {
            $pdo = self::$db->getPdo();
            
            $sql = "DELETE FROM notifications WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $result = $stmt->execute([$notificationId]);
            
            return ['success' => true, 'message' => 'Đã xóa thông báo'];
            
        } catch (Exception $e) {
            error_log("Delete notification error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Lỗi: ' . $e->getMessage()];
        }
    }
    
    /**
     * Update trạng thái thông báo
     */
    public static function updateStatus($notificationId, $status) {
        try {
            $pdo = self::$db->getPdo();
            
            $sql = "UPDATE notifications SET status = ? WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $result = $stmt->execute([$status, $notificationId]);
            
            return ['success' => true, 'message' => 'Đã cập nhật trạng thái'];
            
        } catch (Exception $e) {
            error_log("Update status error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Lỗi: ' . $e->getMessage()];
        }
    }
    
    /**
     * Xóa thông báo hết hạn
     */
    public static function deleteExpired() {
        try {
            $pdo = self::$db->getPdo();
            
            // Xóa thông báo hết hạn hoặc cũ hơn 30 ngày
            $sql = "DELETE FROM notifications 
                    WHERE (expires_at IS NOT NULL AND expires_at < NOW()) 
                    OR created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)";
            
            $stmt = $pdo->prepare($sql);
            $result = $stmt->execute();
            
            return ['success' => true, 'deleted' => $stmt->rowCount()];
            
        } catch (Exception $e) {
            error_log("Delete expired error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Lỗi: ' . $e->getMessage()];
        }
    }
    
    /**
     * Helper: Get icon by type
     */
    private static function getIconByType($type) {
        $icons = [
            'info' => 'fa-info-circle',
            'success' => 'fa-check-circle',
            'warning' => 'fa-exclamation-triangle',
            'error' => 'fa-times-circle',
            'promotion' => 'fa-gift'
        ];
        
        return $icons[$type] ?? 'fa-bell';
    }
    
    /**
     * Helper: Get color by type
     */
    private static function getColorByType($type) {
        $colors = [
            'info' => '#3b82f6',      // Blue
            'success' => '#10b981',   // Green
            'warning' => '#f59e0b',   // Yellow
            'error' => '#ef4444',     // Red
            'promotion' => '#8b5cf6'  // Purple
        ];
        
        return $colors[$type] ?? '#6b7280';
    }
    
    /**
     * Get notification templates
     */
    public static function getTemplates() {
        try {
            $pdo = self::$db->getPdo();
            $stmt = $pdo->query("SELECT * FROM notification_templates ORDER BY name");
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Get templates error: " . $e->getMessage());
            return [];
        }
    }
}

// Initialize với database
if (isset($db)) {
    NotificationManager::init($db);
}
?>