<?php
header('Content-Type: application/json');

try {
    // Test loading EmailService
    $emailServicePath = __DIR__ . '/../../../app/EmailService.php';
    
    if (!file_exists($emailServicePath)) {
        throw new Exception('EmailService.php not found at: ' . $emailServicePath);
    }
    
    // Include config và db first
    require_once __DIR__ . '/../../../app/config.php';
    require_once __DIR__ . '/../../../app/db.php';
    
    // Include EmailService
    require_once $emailServicePath;
    
    // Check class exists
    if (!class_exists('\App\EmailService')) {
        throw new Exception('Class App\EmailService not found');
    }
    
    // Try to get instance
    $emailService = \App\EmailService::getInstance();
    
    // Check methods exist
    $methods = [
        'isConfigured' => method_exists($emailService, 'isConfigured'),
        'sendSubscriptionEmail' => method_exists($emailService, 'sendSubscriptionEmail'),
        'getConfig' => method_exists($emailService, 'getConfig')
    ];
    
    echo json_encode([
        'success' => true,
        'message' => 'EmailService loaded successfully',
        'methods' => $methods,
        'is_configured' => $emailService->isConfigured()
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>