<?php
header('Content-Type: application/json');

try {
    // Test basic requirements
    $checks = [
        'session' => extension_loaded('session'),
        'json' => function_exists('json_encode'),
        'config_exists' => file_exists('../../../app/config.php'),
        'db_exists' => file_exists('../../../app/db.php'),
        'auth_exists' => file_exists('../../../app/auth.php'),
        'email_service_exists' => file_exists('../../../app/EmailService.php')
    ];
    
    // Try to include files
    if ($checks['config_exists']) {
        require_once '../../../app/config.php';
        $checks['config_loaded'] = defined('DB_HOST');
    }
    
    if ($checks['db_exists']) {
        require_once '../../../app/db.php';
        $checks['db_connected'] = isset($db);
    }
    
    echo json_encode([
        'success' => true,
        'checks' => $checks,
        'php_version' => PHP_VERSION
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>