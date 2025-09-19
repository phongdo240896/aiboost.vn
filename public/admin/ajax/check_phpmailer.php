<?php
header('Content-Type: application/json');

$checks = [];

// Check composer autoload
$autoloadPath = __DIR__ . '/../../../vendor/autoload.php';
$checks['autoload_exists'] = file_exists($autoloadPath);

if ($checks['autoload_exists']) {
    require_once $autoloadPath;
    
    // Check PHPMailer classes
    $checks['phpmailer_class'] = class_exists('PHPMailer\PHPMailer\PHPMailer');
    $checks['smtp_class'] = class_exists('PHPMailer\PHPMailer\SMTP');
    $checks['exception_class'] = class_exists('PHPMailer\PHPMailer\Exception');
} else {
    $checks['phpmailer_class'] = false;
    $checks['smtp_class'] = false;
    $checks['exception_class'] = false;
}

// Check vendor directory
$vendorDir = __DIR__ . '/../../../vendor';
$checks['vendor_exists'] = is_dir($vendorDir);

if ($checks['vendor_exists']) {
    $phpmailerDir = $vendorDir . '/phpmailer/phpmailer';
    $checks['phpmailer_dir_exists'] = is_dir($phpmailerDir);
} else {
    $checks['phpmailer_dir_exists'] = false;
}

echo json_encode([
    'success' => $checks['phpmailer_class'],
    'checks' => $checks,
    'suggestion' => !$checks['phpmailer_class'] ? 'Run: composer require phpmailer/phpmailer' : 'PHPMailer is installed'
]);
?>