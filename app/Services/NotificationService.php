<?php

namespace App\Services;

use App\Models\Setting;
use App\Models\WholesalerApiConfig;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Address;

class NotificationService
{
    protected ?array $smtpConfig = null;
    protected ?Mailer $mailer = null;

    public function __construct()
    {
        $this->loadSmtpConfig();
    }

    /**
     * Load SMTP configuration from settings
     */
    protected function loadSmtpConfig(): void
    {
        $this->smtpConfig = Setting::get('smtp_config');
    }

    /**
     * Check if SMTP is configured and enabled
     */
    public function isEnabled(): bool
    {
        return $this->smtpConfig 
            && !empty($this->smtpConfig['host'])
            && !empty($this->smtpConfig['enabled']);
    }

    /**
     * Check if SMTP is configured (regardless of enabled flag)
     */
    public function isConfigured(): bool
    {
        return $this->smtpConfig 
            && !empty($this->smtpConfig['host'])
            && !empty($this->smtpConfig['password']);
    }

    /**
     * Get mailer instance
     */
    protected function getMailer(bool $forceCreate = false): ?Mailer
    {
        if (!$forceCreate && !$this->isEnabled()) {
            return null;
        }
        
        if (!$this->isConfigured()) {
            return null;
        }

        if ($this->mailer) {
            return $this->mailer;
        }

        try {
            $password = '';
            if (!empty($this->smtpConfig['password'])) {
                try {
                    $password = decrypt($this->smtpConfig['password']);
                } catch (\Exception $e) {
                    $password = $this->smtpConfig['password'];
                }
            }

            $useTls = $this->smtpConfig['encryption'] === 'ssl';
            
            $transport = new EsmtpTransport(
                $this->smtpConfig['host'],
                (int) $this->smtpConfig['port'],
                $useTls
            );

            if (!empty($this->smtpConfig['username'])) {
                $transport->setUsername($this->smtpConfig['username']);
            }
            if (!empty($password)) {
                $transport->setPassword($password);
            }

            $this->mailer = new Mailer($transport);
            return $this->mailer;
        } catch (\Exception $e) {
            Log::error('NotificationService: Failed to create mailer', [
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Send notification email
     */
    public function send(array $to, string $subject, string $htmlContent, bool $forceEnabled = false): bool
    {
        $mailer = $this->getMailer($forceEnabled);
        if (!$mailer) {
            Log::warning('NotificationService: Mailer not available');
            return false;
        }

        try {
            $email = (new Email())
                ->from(new Address(
                    $this->smtpConfig['from_address'],
                    $this->smtpConfig['from_name']
                ))
                ->subject($subject)
                ->html($htmlContent);

            foreach ($to as $recipient) {
                if (filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
                    $email->addTo($recipient);
                }
            }

            $mailer->send($email);
            
            Log::info('NotificationService: Email sent', [
                'to' => $to,
                'subject' => $subject,
            ]);
            
            return true;
        } catch (\Exception $e) {
            Log::error('NotificationService: Failed to send email', [
                'to' => $to,
                'subject' => $subject,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Notify integration admins based on notification type
     */
    public function notifyIntegration(int $integrationId, string $type, array $data = []): bool
    {
        $config = WholesalerApiConfig::with('wholesaler')->find($integrationId);
        
        if (!$config) {
            Log::warning('NotificationService: Integration not found', ['id' => $integrationId]);
            return false;
        }

        // Check if notifications are enabled
        if (!$config->notifications_enabled) {
            Log::info('NotificationService: Notifications disabled for integration', ['id' => $integrationId]);
            return false;
        }

        // Check if this notification type is enabled
        $enabledTypes = $config->notification_types ?? [];
        if (!in_array($type, $enabledTypes)) {
            Log::info('NotificationService: Notification type not enabled', [
                'id' => $integrationId,
                'type' => $type,
            ]);
            return false;
        }

        // Get recipient emails
        $emails = $config->notification_emails ?? [];
        if (empty($emails)) {
            Log::warning('NotificationService: No recipient emails configured', ['id' => $integrationId]);
            return false;
        }

        // Generate email content based on type
        $wholesalerName = $config->wholesaler?->name ?? 'Unknown';
        $content = $this->generateEmailContent($type, $wholesalerName, $data);

        return $this->send($emails, $content['subject'], $content['html']);
    }

    /**
     * Generate email content based on notification type
     */
    protected function generateEmailContent(string $type, string $wholesalerName, array $data): array
    {
        $baseStyle = '
            <style>
                body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { padding: 20px; border-radius: 8px 8px 0 0; }
                .content { background: #f9fafb; padding: 20px; border-radius: 0 0 8px 8px; }
                .alert-critical { background: #dc2626; color: white; }
                .alert-warning { background: #f59e0b; color: white; }
                .alert-info { background: #3b82f6; color: white; }
                .alert-success { background: #10b981; color: white; }
                .detail-box { background: white; padding: 15px; border-radius: 6px; margin: 15px 0; }
                .footer { margin-top: 20px; padding-top: 20px; border-top: 1px solid #e5e7eb; color: #6b7280; font-size: 12px; }
            </style>
        ';

        $timestamp = now()->format('d/m/Y H:i:s');
        
        switch ($type) {
            case 'sync_error':
                return [
                    'subject' => "🔴 [Sync Error] {$wholesalerName} - Sync ผิดพลาด",
                    'html' => $baseStyle . '
                        <div class="container">
                            <div class="header alert-critical">
                                <h2 style="margin:0;">🔴 Sync ผิดพลาด</h2>
                            </div>
                            <div class="content">
                                <p><strong>Wholesaler:</strong> ' . $wholesalerName . '</p>
                                <div class="detail-box">
                                    <p><strong>ข้อผิดพลาด:</strong></p>
                                    <p style="color: #dc2626;">' . ($data['error'] ?? 'Unknown error') . '</p>
                                </div>
                                ' . (isset($data['details']) ? '<div class="detail-box"><p><strong>รายละเอียด:</strong></p><pre style="white-space: pre-wrap;">' . json_encode($data['details'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . '</pre></div>' : '') . '
                                <div class="footer">
                                    <p>เวลา: ' . $timestamp . '</p>
                                    <p>ระบบ NextTrip Notification</p>
                                </div>
                            </div>
                        </div>
                    ',
                ];

            case 'api_error':
                return [
                    'subject' => "🟠 [API Error] {$wholesalerName} - เชื่อมต่อ API มีปัญหา",
                    'html' => $baseStyle . '
                        <div class="container">
                            <div class="header alert-warning">
                                <h2 style="margin:0;">🟠 เชื่อมต่อ API มีปัญหา</h2>
                            </div>
                            <div class="content">
                                <p><strong>Wholesaler:</strong> ' . $wholesalerName . '</p>
                                <div class="detail-box">
                                    <p><strong>Status Code:</strong> ' . ($data['status_code'] ?? 'N/A') . '</p>
                                    <p><strong>URL:</strong> ' . ($data['url'] ?? 'N/A') . '</p>
                                    <p><strong>Error:</strong> ' . ($data['error'] ?? 'Connection failed') . '</p>
                                </div>
                                <div class="footer">
                                    <p>เวลา: ' . $timestamp . '</p>
                                    <p>ระบบ NextTrip Notification</p>
                                </div>
                            </div>
                        </div>
                    ',
                ];

            case 'booking_error':
                return [
                    'subject' => "🔴 [Booking Error] {$wholesalerName} - ยืนยันการจองไม่สำเร็จ",
                    'html' => $baseStyle . '
                        <div class="container">
                            <div class="header alert-critical">
                                <h2 style="margin:0;">🔴 ยืนยันการจองไม่สำเร็จ</h2>
                            </div>
                            <div class="content">
                                <p><strong>Wholesaler:</strong> ' . $wholesalerName . '</p>
                                <div class="detail-box">
                                    <p><strong>Booking Ref:</strong> ' . ($data['booking_ref'] ?? 'N/A') . '</p>
                                    <p><strong>Tour:</strong> ' . ($data['tour_name'] ?? 'N/A') . '</p>
                                    <p><strong>Error:</strong> ' . ($data['error'] ?? 'Booking confirmation failed') . '</p>
                                </div>
                                <div class="footer">
                                    <p>เวลา: ' . $timestamp . '</p>
                                    <p>ระบบ NextTrip Notification</p>
                                </div>
                            </div>
                        </div>
                    ',
                ];

            case 'daily_summary':
                return [
                    'subject' => "📊 [Daily Summary] {$wholesalerName} - สรุป Sync ประจำวัน",
                    'html' => $baseStyle . '
                        <div class="container">
                            <div class="header alert-info">
                                <h2 style="margin:0;">📊 สรุป Sync ประจำวัน</h2>
                            </div>
                            <div class="content">
                                <p><strong>Wholesaler:</strong> ' . $wholesalerName . '</p>
                                <div class="detail-box">
                                    <p><strong>ทัวร์ทั้งหมด:</strong> ' . ($data['total_tours'] ?? 0) . '</p>
                                    <p><strong>Sync สำเร็จ:</strong> ' . ($data['success_count'] ?? 0) . '</p>
                                    <p><strong>Sync ผิดพลาด:</strong> ' . ($data['error_count'] ?? 0) . '</p>
                                    <p><strong>ทัวร์ใหม่:</strong> ' . ($data['new_tours'] ?? 0) . '</p>
                                    <p><strong>อัปเดต:</strong> ' . ($data['updated_tours'] ?? 0) . '</p>
                                </div>
                                <div class="footer">
                                    <p>วันที่: ' . now()->format('d/m/Y') . '</p>
                                    <p>ระบบ NextTrip Notification</p>
                                </div>
                            </div>
                        </div>
                    ',
                ];

            case 'test':
                return [
                    'subject' => "✅ [Test] {$wholesalerName} - ทดสอบการแจ้งเตือน",
                    'html' => $baseStyle . '
                        <div class="container">
                            <div class="header alert-success">
                                <h2 style="margin:0;">✅ ทดสอบการแจ้งเตือนสำเร็จ</h2>
                            </div>
                            <div class="content">
                                <p><strong>Wholesaler:</strong> ' . $wholesalerName . '</p>
                                <div class="detail-box">
                                    <p>🎉 ระบบแจ้งเตือนทำงานปกติ!</p>
                                    <p>อีเมลนี้ถูกส่งเพื่อทดสอบการตั้งค่าการแจ้งเตือน</p>
                                </div>
                                <p><strong>ประเภทการแจ้งเตือนที่เปิดใช้งาน:</strong></p>
                                <ul>
                                    ' . $this->formatEnabledTypes($data['enabled_types'] ?? []) . '
                                </ul>
                                <div class="footer">
                                    <p>เวลา: ' . $timestamp . '</p>
                                    <p>ระบบ NextTrip Notification</p>
                                </div>
                            </div>
                        </div>
                    ',
                ];

            default:
                return [
                    'subject' => "[NextTrip] {$wholesalerName} - Notification",
                    'html' => $baseStyle . '
                        <div class="container">
                            <div class="header alert-info">
                                <h2 style="margin:0;">📢 การแจ้งเตือน</h2>
                            </div>
                            <div class="content">
                                <p><strong>Wholesaler:</strong> ' . $wholesalerName . '</p>
                                <div class="detail-box">
                                    <pre>' . json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . '</pre>
                                </div>
                                <div class="footer">
                                    <p>เวลา: ' . $timestamp . '</p>
                                    <p>ระบบ NextTrip Notification</p>
                                </div>
                            </div>
                        </div>
                    ',
                ];
        }
    }

    /**
     * Format enabled notification types for email
     */
    protected function formatEnabledTypes(array $types): string
    {
        $labels = [
            'sync_error' => 'Sync ผิดพลาด',
            'api_error' => 'เชื่อมต่อ API มีปัญหา',
            'booking_error' => 'ยืนยันการจองไม่สำเร็จ',
            'daily_summary' => 'สรุป Sync ประจำวัน',
        ];

        $html = '';
        foreach ($types as $type) {
            $label = $labels[$type] ?? $type;
            $html .= "<li>{$label}</li>";
        }
        
        return $html ?: '<li>ไม่มี</li>';
    }

    /**
     * Send test notification for an integration
     */
    public function sendTestNotification(int $integrationId): array
    {
        $config = WholesalerApiConfig::with('wholesaler')->find($integrationId);
        
        if (!$config) {
            return ['success' => false, 'message' => 'ไม่พบ Integration'];
        }

        if (!$this->isConfigured()) {
            return ['success' => false, 'message' => 'กรุณาตั้งค่า SMTP ก่อนที่ Settings > SMTP'];
        }

        $emails = $config->notification_emails ?? [];
        if (empty($emails)) {
            return ['success' => false, 'message' => 'ยังไม่ได้ตั้งค่าอีเมลผู้รับการแจ้งเตือน'];
        }

        // Filter out empty emails
        $emails = array_filter($emails, fn($e) => !empty(trim($e)));
        if (empty($emails)) {
            return ['success' => false, 'message' => 'ยังไม่ได้ตั้งค่าอีเมลผู้รับการแจ้งเตือน'];
        }

        $wholesalerName = $config->wholesaler?->name ?? 'Unknown';
        $content = $this->generateEmailContent('test', $wholesalerName, [
            'enabled_types' => $config->notification_types ?? [],
        ]);

        // Force send even if SMTP is disabled (for testing)
        $success = $this->send($emails, $content['subject'], $content['html'], true);

        if ($success) {
            return [
                'success' => true,
                'message' => 'ส่งอีเมลทดสอบไปยัง ' . implode(', ', $emails) . ' สำเร็จ',
            ];
        }

        return ['success' => false, 'message' => 'ไม่สามารถส่งอีเมลทดสอบได้ กรุณาตรวจสอบการตั้งค่า SMTP'];
    }
}
