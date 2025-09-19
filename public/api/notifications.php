<?php
session_start();
require_once '../../app/config.php';
require_once '../../app/db.php';
require_once '../../app/auth.php';
require_once '../../app/NotificationManager.php';

// Check if user is logged in
if (!Auth::isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$userId = Auth::getUser()['id'];
$action = $_GET['action'] ?? '';

header('Content-Type: application/json');

switch ($action) {
    case 'list':
        // Get notifications list
        $page = (int)($_GET['page'] ?? 1);
        $filter = $_GET['filter'] ?? 'all';
        
        $result = NotificationManager::getUserNotifications($userId, $page, 20, $filter);
        echo json_encode($result);
        break;
        
    case 'get':
        // Get single notification
        $notificationId = (int)($_GET['id'] ?? 0);
        
        if (!$notificationId) {
            echo json_encode(['success' => false, 'message' => 'Invalid notification ID']);
            break;
        }
        
        try {
            $pdo = $db->getPdo();
            $stmt = $pdo->prepare("
                SELECT n.*, un.is_read, un.read_at 
                FROM notifications n
                INNER JOIN user_notifications un ON n.id = un.notification_id
                WHERE n.id = ? AND un.user_id = ?
            ");
            $stmt->execute([$notificationId, $userId]);
            $notification = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($notification) {
                echo json_encode(['success' => true, 'notification' => $notification]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Notification not found']);
            }
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
        }
        break;
        
    case 'unread_count':
        // Get unread count
        $count = NotificationManager::getUnreadCount($userId);
        echo json_encode(['success' => true, 'count' => $count]);
        break;
        
    case 'get_popup':
        // Get popup notification
        $notification = NotificationManager::getPopupNotification($userId);
        echo json_encode(['success' => true, 'notification' => $notification]);
        break;
        
    case 'recent':
        // Get recent notifications for dropdown
        try {
            $pdo = $db->getPdo();
            $stmt = $pdo->prepare("
                SELECT n.*, un.is_read, un.read_at
                FROM notifications n
                INNER JOIN user_notifications un ON n.id = un.notification_id
                WHERE un.user_id = ? 
                AND n.status = 'active'
                AND (n.expires_at IS NULL OR n.expires_at > NOW())
                ORDER BY n.priority DESC, n.created_at DESC
                LIMIT 5
            ");
            $stmt->execute([$userId]);
            $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode(['success' => true, 'notifications' => $notifications]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
        }
        break;
        
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
}
?>