<?php
require_once 'vendor/autoload.php';
require_once 'app/config.php';
require_once 'app/db.php';

use App\EmailService;

echo "Testing Email Service...\n\n";

try {
    // Check if vendor/autoload.php exists
    if (!file_exists('vendor/autoload.php')) {
        echo "❌ Composer autoload not found. Run: composer install\n";
        exit;
    }
    
    // Check if PHPMailer is installed
    if (!class_exists('PHPMailer\PHPMailer\PHPMailer')) {
        echo "❌ PHPMailer not installed. Run: composer install\n";
        exit;
    }
    
    echo "✅ PHPMailer is installed\n";
    
    // Initialize EmailService
    $emailService = EmailService::getInstance();
    echo "✅ EmailService initialized\n";
    
    // Check config
    global $db;
    $settings = $db->query("SELECT setting_key, setting_value FROM email_settings WHERE setting_key = 'smtp_username'");
    
    if (!empty($settings) && !empty($settings[0]['setting_value'])) {
        echo "✅ Email configuration found\n";
        echo "📧 SMTP Username: " . $settings[0]['setting_value'] . "\n";
    } else {
        echo "⚠️ Email not configured. Please configure in admin panel\n";
    }
    
    echo "\n✅ Email system is ready!\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}