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

// Check CSRF token
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($data['notification_id'])) {
        echo json_encode(['success' => false, 'message' => 'Missing notification ID']);
        exit;
    }
    
    $userId = Auth::getUser()['id'];
    $notificationId = (int)$data['notification_id'];
    
    $result = NotificationManager::markAsRead($notificationId, $userId);
    
    header('Content-Type: application/json');
    echo json_encode($result);
} else {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
}
?>