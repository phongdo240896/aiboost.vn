<?php
/**
 * Email Configuration
 * Cấu hình SMTP để gửi email
 */

return [
    // SMTP Configuration
    'smtp' => [
        'host' => 'smtp.gmail.com', // Hoặc smtp server của bạn
        'port' => 587,
        'encryption' => 'tls', // tls hoặc ssl
        'username' => 'your-email@gmail.com', // Email gửi
        'password' => 'your-app-password', // App password (không phải password thường)
        'from_email' => 'noreply@aiboost.vn',
        'from_name' => 'AIboost.vn'
    ],
    
    // Email Templates
    'templates' => [
        'notification' => [
            'subject' => '[AIboost.vn] {title}',
            'logo_url' => 'https://aiboost.vn/assets/images/logo.png'
        ],
        'welcome' => [
            'subject' => 'Chào mừng bạn đến với AIboost.vn',
        ],
        'promotion' => [
            'subject' => '[Khuyến mãi] {title}',
        ]
    ],
    
    // Rate limiting
    'rate_limit' => [
        'max_per_minute' => 30,
        'max_per_hour' => 500,
        'max_per_day' => 5000
    ],
    
    // Debug mode (log emails instead of sending)
    'debug' => false,
    'log_path' => __DIR__ . '/../logs/emails/'
];