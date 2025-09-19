<?php
namespace App;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

class EmailService {
    private static $instance = null;
    private $config;
    private $mailer;
    private $db;
    
    private function __construct() {
        global $db;
        $this->db = $db;
        $this->loadConfig();
        $this->initMailer();
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function loadConfig() {
        try {
            $settings = $this->db->query("SELECT setting_key, setting_value FROM email_settings");
            
            $this->config = [];
            if (!empty($settings)) {
                foreach ($settings as $row) {
                    $this->config[$row['setting_key']] = $row['setting_value'];
                }
            }
        } catch (\Exception $e) {
            // Use default config if database not available
            $this->config = [
                'smtp_host' => 'smtp.gmail.com',
                'smtp_port' => '587',
                'smtp_encryption' => 'tls',
                'smtp_username' => '',
                'smtp_password' => '',
                'from_email' => 'noreply@aiboost.vn',
                'from_name' => 'AIboost.vn',
                'debug_mode' => '0'
            ];
        }
    }
    
    private function initMailer() {
        $this->mailer = new PHPMailer(true);
        
        try {
            // Server settings
            if (($this->config['debug_mode'] ?? '0') == '1') {
                $this->mailer->SMTPDebug = SMTP::DEBUG_SERVER;
            } else {
                $this->mailer->SMTPDebug = SMTP::DEBUG_OFF;
            }
            
            $this->mailer->isSMTP();
            $this->mailer->Host = $this->config['smtp_host'] ?? 'smtp.gmail.com';
            $this->mailer->SMTPAuth = true;
            $this->mailer->Username = $this->config['smtp_username'] ?? '';
            $this->mailer->Password = $this->config['smtp_password'] ?? '';
            
            // Encryption
            if (($this->config['smtp_encryption'] ?? 'tls') == 'ssl') {
                $this->mailer->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
                $this->mailer->Port = 465;
            } else {
                $this->mailer->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                $this->mailer->Port = 587;
            }
            
            // Override port if specified
            if (!empty($this->config['smtp_port'])) {
                $this->mailer->Port = (int)$this->config['smtp_port'];
            }
            
            $this->mailer->CharSet = 'UTF-8';
            
            // Default sender
            $this->mailer->setFrom(
                $this->config['from_email'] ?? 'noreply@aiboost.vn',
                $this->config['from_name'] ?? 'AIboost.vn'
            );
            
        } catch (Exception $e) {
            error_log("Mailer Init Error: " . $e->getMessage());
        }
    }
    
    /**
     * Send test email
     */
    public function sendTestEmail($toEmail, $content = null) {
        if (!filter_var($toEmail, FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'message' => 'Email không hợp lệ'];
        }
        
        if (empty($this->config['smtp_username']) || empty($this->config['smtp_password'])) {
            return ['success' => false, 'message' => 'Vui lòng cấu hình SMTP trước khi gửi email'];
        }
        
        if (!$content) {
            $content = 'Đây là email test từ AIboost.vn. Nếu bạn nhận được email này, hệ thống email đang hoạt động bình thường.';
        }
        
        try {
            // Clear previous recipients
            $this->mailer->clearAllRecipients();
            $this->mailer->clearAttachments();
            
            // Add recipient
            $this->mailer->addAddress($toEmail);
            
            // Content
            $this->mailer->isHTML(true);
            $this->mailer->Subject = 'Test Email từ AIboost.vn - ' . date('H:i:s d/m/Y');
            $this->mailer->Body = $this->getTestEmailTemplate($content);
            $this->mailer->AltBody = strip_tags($content);
            
            // Check debug mode
            if (($this->config['debug_mode'] ?? '0') == '1') {
                $this->logEmailToFile($toEmail, $this->mailer->Subject, $this->mailer->Body);
                return ['success' => true, 'message' => 'Email đã được lưu log (chế độ debug)'];
            }
            
            // Send email
            $this->mailer->send();
            
            // Log to database
            try {
                $this->db->query(
                    "INSERT INTO email_logs (user_id, email, subject, status, sent_at) 
                     VALUES ('system', ?, ?, 'sent', NOW())",
                    [$toEmail, $this->mailer->Subject]
                );
            } catch (\Exception $e) {
                // Ignore database logging errors
            }
            
            return ['success' => true, 'message' => 'Email đã được gửi thành công!'];
            
        } catch (Exception $e) {
            $errorMsg = $this->mailer->ErrorInfo ?: $e->getMessage();
            
            // Log error to database
            try {
                $this->db->query(
                    "INSERT INTO email_logs (user_id, email, subject, status, error_message, sent_at) 
                     VALUES ('system', ?, ?, 'failed', ?, NOW())",
                    [$toEmail, 'Test Email', $errorMsg]
                );
            } catch (\Exception $e) {
                // Ignore logging errors
            }
            
            return ['success' => false, 'message' => 'Lỗi: ' . $errorMsg];
        }
    }
    
    /**
     * Send notification email
     */
    public function sendNotificationEmail($toEmail, $toName, $subject, $content, $userId = null, $notificationId = null) {
        if (!filter_var($toEmail, FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'message' => 'Email không hợp lệ'];
        }
        
        try {
            // Clear previous
            $this->mailer->clearAllRecipients();
            $this->mailer->clearAttachments();
            
            // Add recipient
            $this->mailer->addAddress($toEmail, $toName);
            
            // Content
            $this->mailer->isHTML(true);
            $this->mailer->Subject = $subject;
            $this->mailer->Body = $this->getNotificationTemplate($subject, $content);
            $this->mailer->AltBody = strip_tags($content);
            
            // Check debug mode
            if (($this->config['debug_mode'] ?? '0') == '1') {
                $this->logEmailToFile($toEmail, $subject, $this->mailer->Body);
                return ['success' => true, 'message' => 'Email logged (debug mode)'];
            }
            
            // Send
            $this->mailer->send();
            
            // Log success
            $this->db->query(
                "INSERT INTO email_logs (user_id, notification_id, email, subject, status, sent_at) 
                 VALUES (?, ?, ?, ?, 'sent', NOW())",
                [$userId ?? 'system', $notificationId, $toEmail, $subject]
            );
            
            return ['success' => true, 'message' => 'Email sent successfully'];
            
        } catch (Exception $e) {
            // Log error
            $this->db->query(
                "INSERT INTO email_logs (user_id, notification_id, email, subject, status, error_message, sent_at) 
                 VALUES (?, ?, ?, ?, 'failed', ?, NOW())",
                [$userId ?? 'system', $notificationId, $toEmail, $subject, $e->getMessage()]
            );
            
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    /**
     * Send subscription reminder email với template đẹp
     */
    public function sendSubscriptionEmail($toEmail, $toName, $subject, $content, $type = 'info', $subscriptionData = []) {
        try {
            // Clone mailer
            $mail = clone $this->mailer;
            
            // Clear recipients
            $mail->clearAddresses();
            
            // Add recipient
            $mail->addAddress($toEmail, $toName);
            
            // Set subject
            $mail->Subject = $subject;
            
            // Set body với template
            $mail->isHTML(true);
            $mail->Body = $this->getSubscriptionTemplate($subject, $content, $type, $subscriptionData);
            $mail->AltBody = strip_tags($content);
            
            // Check debug mode
            if ($this->config['debug_mode'] == '1') {
                $this->logEmail($toEmail, $subject, 'debug');
                return ['success' => true, 'message' => 'Email logged (Debug mode)'];
            }
            
            // Send
            if ($mail->send()) {
                // Log to database
                $this->db->query(
                    "INSERT INTO email_logs (user_id, email, subject, status, sent_at) 
                     VALUES (?, ?, ?, 'sent', NOW())",
                    [$subscriptionData['user_id'] ?? 'system', $toEmail, $subject]
                );
                
                return ['success' => true, 'message' => 'Email sent successfully'];
            } else {
                throw new Exception($mail->ErrorInfo);
            }
            
        } catch (Exception $e) {
            // Log error
            $this->db->query(
                "INSERT INTO email_logs (user_id, email, subject, status, error_message, sent_at) 
                 VALUES (?, ?, ?, 'failed', ?, NOW())",
                [$subscriptionData['user_id'] ?? 'system', $toEmail, $subject, $e->getMessage()]
            );
            
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    /**
     * Check if email service is configured
     */
    public function isConfigured() {
        return !empty($this->config['smtp_username']) && !empty($this->config['smtp_password']);
    }
    
    /**
     * Get configuration
     */
    public function getConfig() {
        return $this->config;
    }
    
    private function getTestEmailTemplate($content) {
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
                }
                .wrapper {
                    background-color: #f5f5f5;
                    padding: 40px 20px;
                }
                .container {
                    max-width: 600px;
                    margin: 0 auto;
                    background: white;
                    border-radius: 10px;
                    overflow: hidden;
                    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
                }
                .header {
                    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                    color: white;
                    padding: 40px 30px;
                    text-align: center;
                }
                .header h1 {
                    margin: 0;
                    font-size: 28px;
                }
                .content {
                    padding: 40px 30px;
                }
                .info-box {
                    background: #f8f9fa;
                    border-left: 4px solid #667eea;
                    padding: 15px;
                    margin: 20px 0;
                }
                .footer {
                    background: #f8f9fa;
                    padding: 30px;
                    text-align: center;
                    color: #666;
                    font-size: 14px;
                }
            </style>
        </head>
        <body>
            <div class="wrapper">
                <div class="container">
                    <div class="header">
                        <h1>🚀 AIboost.vn</h1>
                        <p>Nền tảng AI hàng đầu Việt Nam</p>
                    </div>
                    <div class="content">
                        <h2>📧 Test Email</h2>
                        <p>' . nl2br(htmlspecialchars($content)) . '</p>
                        
                        <div class="info-box">
                            <strong>📊 Thông tin hệ thống:</strong><br>
                            ⏰ Thời gian: ' . date('H:i:s d/m/Y') . '<br>
                            📮 SMTP Server: ' . ($this->config['smtp_host'] ?? 'N/A') . '<br>
                            📤 From: ' . ($this->config['from_email'] ?? 'N/A') . '
                        </div>
                    </div>
                    <div class="footer">
                        <p>© 2025 AIboost.vn - All rights reserved</p>
                        <p>📧 support@aiboost.vn | 🌐 aiboost.vn</p>
                    </div>
                </div>
            </div>
        </body>
        </html>';
    }
    
    private function getNotificationTemplate($subject, $content) {
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
                }
                .wrapper {
                    background-color: #f5f5f5;
                    padding: 40px 20px;
                }
                .container {
                    max-width: 600px;
                    margin: 0 auto;
                    background: white;
                    border-radius: 10px;
                    overflow: hidden;
                    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
                }
                .header {
                    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                    color: white;
                    padding: 40px 30px;
                    text-align: center;
                }
                .header h1 {
                    margin: 0;
                    font-size: 28px;
                }
                .content {
                    padding: 40px 30px;
                }
                .info-box {
                    background: #f8f9fa;
                    border-left: 4px solid #667eea;
                    padding: 15px;
                    margin: 20px 0;
                }
                .footer {
                    background: #f8f9fa;
                    padding: 30px;
                    text-align: center;
                    color: #666;
                    font-size: 14px;
                }
            </style>
        </head>
        <body>
            <div class="wrapper">
                <div class="container">
                    <div class="header">
                        <h1>🚀 AIboost.vn</h1>
                        <p>Bạn làm chủ chiến lược, để AIBoost lo việc sáng tạo</p>
                    </div>
                    <div class="content">
                        <p>' . nl2br(htmlspecialchars($content)) . '</p>
                        
                        <div class="info-box">
                            <strong>📊 Thông tin hệ thống:</strong><br>
                            ⏰ Thời gian: ' . date('H:i:s d/m/Y') . '<br>
                            🌐 Website: ' . ($this->config['smtp_host'] ?? 'N/A') . '<br>
                            📤 From: ' . ($this->config['from_email'] ?? 'N/A') . '
                        </div>
                    </div>
                    <div class="footer">
                        <p>© 2025 AIboost.vn - All rights reserved</p>
                        <p>📧 aiboostvn@gmail.com | 🌐 aiboost.vn</p> ☎
                    </div>
                </div>
            </div>
        </body>
        </html>';
    }
    
    private function getSubscriptionTemplate($title, $content, $type = 'info', $subscriptionData = []) {
        // Type configs
        $typeConfig = [
            'warning' => ['color' => '#f59e0b', 'icon' => '⏰', 'badge' => 'Sắp hết hạn'],
            'error' => ['color' => '#ef4444', 'icon' => '❌', 'badge' => 'Đã hết hạn'],
            'promotion' => ['color' => '#8b5cf6', 'icon' => '🎁', 'badge' => 'Ưu đãi đặc biệt'],
            'success' => ['color' => '#10b981', 'icon' => '✅', 'badge' => 'Gia hạn thành công']
        ];
        
        $config = $typeConfig[$type] ?? $typeConfig['warning'];
        
        // Format subscription data
        $planName = $subscriptionData['plan_name'] ?? 'N/A';
        $endDate = isset($subscriptionData['end_date']) ? date('d/m/Y', strtotime($subscriptionData['end_date'])) : 'N/A';
        $credits = isset($subscriptionData['credits_remaining']) ? number_format($subscriptionData['credits_remaining'], 0, ',', '.') : '0';
        
        return '
        <!DOCTYPE html>
        <html lang="vi">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>' . htmlspecialchars($title) . '</title>
            <style>
                body {
                    margin: 0;
                    padding: 0;
                    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Arial, sans-serif;
                    background-color: #f3f4f6;
                    color: #1f2937;
                }
                .wrapper {
                    width: 100%;
                    padding: 40px 0;
                    background-color: #f3f4f6;
                }
                .container {
                    max-width: 600px;
                    margin: 0 auto;
                    background-color: #ffffff;
                    border-radius: 12px;
                    overflow: hidden;
                    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.07);
                }
                .header {
                    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                    padding: 40px 30px;
                    text-align: center;
                }
                .header h1 {
                    margin: 0;
                    color: #ffffff;
                    font-size: 28px;
                    font-weight: 700;
                }
                .header p {
                    margin: 10px 0 0;
                    color: rgba(255, 255, 255, 0.9);
                    font-size: 16px;
                }
                .alert-badge {
                    display: inline-block;
                    background: ' . $config['color'] . ';
                    color: white;
                    padding: 10px 20px;
                    border-radius: 25px;
                    font-size: 14px;
                    font-weight: 600;
                    margin: 20px 0;
                    text-transform: uppercase;
                    letter-spacing: 0.5px;
                }
                .content {
                    padding: 40px 30px;
                }
                .message-box {
                    background: #f9fafb;
                    border-left: 4px solid ' . $config['color'] . ';
                    padding: 20px;
                    border-radius: 4px;
                    margin: 20px 0;
                    white-space: pre-line;
                    line-height: 1.8;
                }
                .subscription-info {
                    background: linear-gradient(135deg, #f3f4f6 0%, #e5e7eb 100%);
                    padding: 20px;
                    border-radius: 8px;
                    margin: 20px 0;
                }
                .subscription-info h3 {
                    margin: 0 0 15px;
                    color: #374151;
                    font-size: 16px;
                    font-weight: 600;
                }
                .info-grid {
                    display: grid;
                    grid-template-columns: 1fr 1fr;
                    gap: 10px;
                }
                .info-item {
                    padding: 8px 0;
                }
                .info-label {
                    color: #6b7280;
                    font-size: 13px;
                    margin-bottom: 2px;
                }
                .info-value {
                    color: #1f2937;
                    font-size: 15px;
                    font-weight: 500;
                }
                .cta-button {
                    display: inline-block;
                    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                    color: white;
                    text-decoration: none;
                    padding: 14px 32px;
                    border-radius: 8px;
                    font-weight: 600;
                    font-size: 16px;
                    margin: 20px 0;
                    text-align: center;
                }
                .footer {
                    background: #f9fafb;
                    padding: 30px;
                    text-align: center;
                    border-top: 1px solid #e5e7eb;
                }
                .footer-links {
                    margin: 20px 0;
                }
                .footer-links a {
                    color: #667eea;
                    text-decoration: none;
                    margin: 0 10px;
                    font-size: 14px;
                }
                .copyright {
                    color: #9ca3af;
                    font-size: 13px;
                    margin-top: 20px;
                }
                .highlight {
                    background: ' . $config['color'] . '15;
                    padding: 2px 6px;
                    border-radius: 4px;
                    color: ' . $config['color'] . ';
                    font-weight: 600;
                }
            </style>
        </head>
        <body>
            <div class="wrapper">
                <div class="container">
                    <div class="header">
                        <h1>🚀 AIboost.vn</h1>
                        <p>Bạn làm chủ chiến lược, để AIBoost lo việc sáng tạo</p>
                    </div>
                    
                    <div class="content">
                        <center>
                            <div class="alert-badge">
                                ' . $config['icon'] . ' ' . $config['badge'] . '
                            </div>
                        </center>
                        
                        <div class="message-box">
                            ' . htmlspecialchars($content) . '
                        </div>
                        
                        ' . (!empty($subscriptionData) ? '
                        <div class="subscription-info">
                            <h3>📊 Thông tin gói cước của bạn</h3>
                            <div class="info-grid">
                                <div class="info-item">
                                    <div class="info-label">Gói cước</div>
                                    <div class="info-value">' . $planName . '</div>
                                </div>
                                <div class="info-item">
                                    <div class="info-label">Credits còn lại</div>
                                    <div class="info-value">' . $credits . '</div>
                                </div>
                                <div class="info-item">
                                    <div class="info-label">Ngày hết hạn</div>
                                    <div class="info-value">' . $endDate . '</div>
                                </div>
                                <div class="info-item">
                                    <div class="info-label">Trạng thái</div>
                                    <div class="info-value">
                                        <span class="highlight">' . ucfirst($subscriptionData['status'] ?? 'N/A') . '</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        ' : '') . '
                        
                        <center>
                            <a href="https://aiboost.vn/pricing" class="cta-button">
                                Gia hạn ngay →
                            </a>
                            
                            <p style="color: #6b7280; font-size: 14px; margin-top: 10px;">
                                Hoặc truy cập: <a href="https://aiboost.vn/pricing" style="color: #667eea;">aiboost.vn/pricing</a>
                            </p>
                        </center>
                    </div>
                    
                    <div class="footer">
                        <div class="footer-links">
                            <a href="mailto:aiboostvn@gmail.com">📧 aiboostvn@gmail.com</a>
                            <a href="https://aiboost.vn">🌐 aiboost.vn</a>
                            <a href="tel:0325595995">📞 Hotline: 0325.59.59.95</a>
                        </div>
                        <div class="copyright">
                            © 2025 AIboost.vn - All rights reserved<br>
                            <small style="color: #cbd5e1;">
                                Bạn nhận được email này vì đang sử dụng dịch vụ của AIboost.vn
                            </small>
                        </div>
                    </div>
                </div>
            </div>
        </body>
        </html>';
    }
    
    private function logEmailToFile($to, $subject, $body) {
        $logDir = __DIR__ . '/../logs/emails/';
        if (!file_exists($logDir)) {
            mkdir($logDir, 0777, true);
        }
        
        $logFile = $logDir . date('Y-m-d') . '.log';
        $logEntry = sprintf(
            "[%s] TO: %s | SUBJECT: %s\n",
            date('Y-m-d H:i:s'),
            $to,
            $subject
        );
        
        file_put_contents($logFile, $logEntry, FILE_APPEND);
        
        // Also save HTML
        $htmlFile = $logDir . date('Y-m-d') . '.html';
        $htmlEntry = "
        <div style='border: 2px solid #667eea; margin: 20px; padding: 20px;'>
            <h3>Email Log - " . date('H:i:s') . "</h3>
            <p><strong>To:</strong> $to</p>
            <p><strong>Subject:</strong> $subject</p>
            <hr>
            $body
        </div>\n";
        
        file_put_contents($htmlFile, $htmlEntry, FILE_APPEND);
    }
    
    /**
     * Log email to database
     */
    private function logEmail($to, $subject, $status) {
        try {
            $this->db->query(
                "INSERT INTO email_logs (user_id, email, subject, status, sent_at) 
                 VALUES ('system', ?, ?, ?, NOW())",
                [$to, $subject, $status]
            );
        } catch (\Exception $e) {
            // Ignore logging errors
            error_log("Email log error: " . $e->getMessage());
        }
    }
}