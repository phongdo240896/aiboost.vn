<?php
// Prevent any output before JSON
error_reporting(E_ALL);
ini_set('display_errors', 0);
ob_start();

session_start();

// Set JSON header immediately
header('Content-Type: application/json; charset=utf-8');

try {
    // Load composer autoloader if exists
    $autoloadPath = __DIR__ . '/../vendor/autoload.php';
    if (file_exists($autoloadPath)) {
        require_once $autoloadPath;
    }

    // Load required files
    require_once __DIR__ . '/../app/config.php';
    require_once __DIR__ . '/../app/db.php';
    require_once __DIR__ . '/../app/auth.php';
    
    // Check if EmailService exists
    if (!file_exists(__DIR__ . '/../app/EmailService.php')) {
        throw new Exception('EmailService.php not found');
    }
    require_once __DIR__ . '/../app/EmailService.php';

    // Use the EmailService
    use App\EmailService;

    // Check admin permission for test email
    if (!Auth::isAdmin()) {
        ob_end_clean();
        echo json_encode([
            'success' => false, 
            'message' => 'Unauthorized - Admin access required'
        ]);
        exit;
    }

    $action = $_POST['action'] ?? $_GET['action'] ?? '';

    switch ($action) {
        case 'send_test_email':
            $toEmail = $_POST['email'] ?? $_GET['email'] ?? '';
            $content = $_POST['content'] ?? $_GET['content'] ?? '';
            
            if (empty($toEmail)) {
                throw new Exception('Email address is required');
            }
            
            if (!filter_var($toEmail, FILTER_VALIDATE_EMAIL)) {
                throw new Exception('Invalid email address format');
            }
            
            // Check if EmailService class exists
            if (!class_exists('App\EmailService')) {
                // Try without namespace
                if (!class_exists('EmailService')) {
                    throw new Exception('EmailService class not found');
                }
                $emailService = EmailService::getInstance();
            } else {
                $emailService = EmailService::getInstance();
            }
            
            $result = $emailService->sendTestEmail($toEmail, $content);
            
            ob_end_clean();
            echo json_encode($result);
            break;
            
        case 'check_config':
            // Check if email is configured
            global $db;
            
            if (!$db) {
                throw new Exception('Database connection not available');
            }
            
            $settings = $db->query(
                "SELECT setting_key, setting_value FROM email_settings 
                 WHERE setting_key IN ('smtp_username', 'smtp_password')"
            );
            
            $configured = false;
            $smtp_username = '';
            
            if (!empty($settings)) {
                foreach ($settings as $row) {
                    if ($row['setting_key'] == 'smtp_username' && !empty($row['setting_value'])) {
                        $configured = true;
                        $smtp_username = $row['setting_value'];
                    }
                    if ($row['setting_key'] == 'smtp_password' && !empty($row['setting_value'])) {
                        $configured = $configured && true;
                    }
                }
            }
            
            ob_end_clean();
            echo json_encode([
                'success' => true,
                'configured' => $configured,
                'smtp_username' => $smtp_username,
                'message' => $configured ? 'Email đã được cấu hình' : 'Chưa cấu hình email'
            ]);
            break;
            
        case 'test_connection':
            // Test SMTP connection
            global $db;
            
            $settings = $db->query("SELECT setting_key, setting_value FROM email_settings");
            $config = [];
            
            if (!empty($settings)) {
                foreach ($settings as $row) {
                    $config[$row['setting_key']] = $row['setting_value'];
                }
            }
            
            // Check required settings
            $missing = [];
            if (empty($config['smtp_host'])) $missing[] = 'SMTP Host';
            if (empty($config['smtp_username'])) $missing[] = 'SMTP Username';
            if (empty($config['smtp_password'])) $missing[] = 'SMTP Password';
            
            if (!empty($missing)) {
                ob_end_clean();
                echo json_encode([
                    'success' => false,
                    'message' => 'Missing configuration: ' . implode(', ', $missing)
                ]);
                break;
            }
            
            ob_end_clean();
            echo json_encode([
                'success' => true,
                'message' => 'Configuration looks good',
                'config' => [
                    'host' => $config['smtp_host'],
                    'port' => $config['smtp_port'],
                    'encryption' => $config['smtp_encryption'],
                    'username' => $config['smtp_username']
                ]
            ]);
            break;
            
        default:
            ob_end_clean();
            echo json_encode([
                'success' => false, 
                'message' => 'Invalid action: ' . $action
            ]);
    }
    
} catch (Exception $e) {
    // Clean any output buffer
    while (ob_get_level()) {
        ob_end_clean();
    }
    
    // Log error for debugging
    error_log('Email API Error: ' . $e->getMessage());
    error_log('Stack trace: ' . $e->getTraceAsString());
    
    // Return JSON error
    echo json_encode([
        'success' => false, 
        'message' => $e->getMessage(),
        'debug' => [
            'file' => $e->getFile(),
            'line' => $e->getLine()
        ]
    ]);
}