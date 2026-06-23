<?php

namespace App\Services;

use App\Models\OtpRequest;
use App\Models\Setting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OtpService
{
    private string $baseUrl;
    private string $apiKey;
    private string $apiSecret;
    private string $sender;
    private bool $enabled;
    private bool $debugMode;
    private int $defaultTtl = 300; // 5 minutes
    private int $defaultDigits = 6;

    /**
     * Normalize ThaiBulkSMS endpoint to API base URL (without /sms suffix)
     */
    private function normalizeBaseEndpoint(?string $endpoint): string
    {
        $value = trim((string) $endpoint);

        if ($value === '') {
            return 'https://api-v2.thaibulksms.com';
        }

        $value = rtrim($value, '/');

        if (str_ends_with(strtolower($value), '/sms')) {
            $value = substr($value, 0, -4);
        }

        return $value !== '' ? $value : 'https://api-v2.thaibulksms.com';
    }

    public function __construct()
    {
        $this->loadConfig();
    }

    /**
     * Load OTP configuration from database or fallback to .env
     */
    private function loadConfig(): void
    {
        $otpConfig = Setting::get('otp_config');

        if ($otpConfig && !empty($otpConfig['api_key'])) {
            // Use database config
            $this->baseUrl = $this->normalizeBaseEndpoint($otpConfig['endpoint'] ?? null);
            $this->sender = trim((string) ($otpConfig['sender'] ?? 'SMS.'));
            $this->enabled = $otpConfig['enabled'] ?? true;
            $this->debugMode = $otpConfig['debug_mode'] ?? false;

            // Decrypt credentials
            try {
                $this->apiKey = decrypt($otpConfig['api_key']);
            } catch (\Exception $e) {
                $this->apiKey = $otpConfig['api_key'];
            }

            try {
                $this->apiSecret = decrypt($otpConfig['api_secret'] ?? '');
            } catch (\Exception $e) {
                $this->apiSecret = $otpConfig['api_secret'] ?? '';
            }
        } else {
            // Fallback to .env config
            $this->baseUrl = $this->normalizeBaseEndpoint(env('THAIBULKSMS_ENDPOINT'));
            $this->apiKey = config('services.thaibulksms.api_key') ?? '';
            $this->apiSecret = config('services.thaibulksms.api_secret') ?? '';
            $this->sender = trim((string) env('THAIBULKSMS_SENDER', 'SMS.'));
            $this->enabled = true;
            $this->debugMode = false;
        }

        if ($this->sender === '') {
            $this->sender = 'SMS.';
        }
    }

    /**
     * Generate random OTP code
     */
    private function generateOtpCode(int $digits = 6): string
    {
        $min = pow(10, $digits - 1);
        $max = pow(10, $digits) - 1;
        return (string) random_int($min, $max);
    }

    /**
     * Request OTP for phone number
     */
    public function requestOtp(
        string $phone,
        string $purpose = 'register',
        ?string $ip = null,
        ?string $userAgent = null,
        ?int $webMemberId = null
    ): array {
        // Check if OTP is enabled
        if (!$this->enabled) {
            return [
                'success' => false,
                'error' => 'disabled',
                'message' => 'ระบบ OTP ถูกปิดใช้งานชั่วคราว',
            ];
        }

        // Check if API credentials are configured
        if (empty($this->apiKey) || empty($this->apiSecret)) {
            Log::error('ThaiBulkSMS API credentials not configured');
            return [
                'success' => false,
                'error' => 'config_error',
                'message' => 'ระบบ OTP ยังไม่พร้อมใช้งาน กรุณาติดต่อผู้ดูแลระบบ',
            ];
        }

        // Normalize phone to MSISDN format
        try {
            $msisdn = $this->normalizeThaiMsisdn($phone);
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => 'invalid_phone',
                'message' => 'หมายเลขโทรศัพท์ไม่ถูกต้อง',
            ];
        }

        // Check rate limit by phone
        if (OtpRequest::isRateLimitedByPhone($msisdn)) {
            return [
                'success' => false,
                'error' => 'rate_limited',
                'message' => 'ขอ OTP บ่อยเกินไป กรุณารอสักครู่',
            ];
        }

        // Check rate limit by IP
        if ($ip && OtpRequest::isRateLimitedByIp($ip)) {
            return [
                'success' => false,
                'error' => 'rate_limited',
                'message' => 'มีการขอ OTP มากเกินไป กรุณารอสักครู่',
            ];
        }

        // Generate OTP code
        $otpCode = $this->generateOtpCode($this->defaultDigits);
        $message = $this->getOtpMessage($purpose, $otpCode);

        // Call ThaiBulkSMS SMS API
        try {
            $response = Http::withBasicAuth($this->apiKey, $this->apiSecret)
                ->timeout(30)
                ->post("{$this->baseUrl}/sms", [
                    'msisdn' => $msisdn,
                    'message' => $message,
                    'sender' => $this->sender,
                    'force' => 'corporate',
                ]);

            /** @var \Illuminate\Http\Client\Response $response */

            $data = $response->json() ?? [];

            // Always log the raw response while debugging integration issues
            Log::info('ThaiBulkSMS SMS response', [
                'status' => $response->status(),
                'body' => $data,
                'msisdn' => $msisdn,
                'sender' => $this->sender,
            ]);

            if (!$response->successful()) {
                $error = $data['error'] ?? [];
                $errorCode = (int) ($error['code'] ?? 0);

                Log::error('ThaiBulkSMS SMS request failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                    'endpoint' => "{$this->baseUrl}/sms",
                    'sender' => $this->sender,
                ]);

                if ($errorCode === 111) {
                    return [
                        'success' => false,
                        'error' => 'sender_not_found',
                        'message' => 'ชื่อผู้ส่ง (Sender) ไม่ถูกต้องหรือยังไม่อนุมัติใน ThaiBulkSMS',
                    ];
                }

                return [
                    'success' => false,
                    'error' => 'api_error',
                    'message' => 'ไม่สามารถส่ง OTP ได้ กรุณาลองใหม่',
                ];
            }

            if (!empty($data['error'])) {
                $errorCode = (int) ($data['error']['code'] ?? 0);
                if ($errorCode === 111) {
                    return [
                        'success' => false,
                        'error' => 'sender_not_found',
                        'message' => 'ชื่อผู้ส่ง (Sender) ไม่ถูกต้องหรือยังไม่อนุมัติใน ThaiBulkSMS',
                    ];
                }

                return [
                    'success' => false,
                    'error' => 'api_error',
                    'message' => 'ไม่สามารถส่ง OTP ได้ กรุณาลองใหม่',
                ];
            }
            
            // Check for bad phone numbers
            if (!empty($data['bad_phone_number_list'])) {
                return [
                    'success' => false,
                    'error' => 'invalid_phone',
                    'message' => 'หมายเลขโทรศัพท์ไม่ถูกต้อง',
                ];
            }

            // Get message_id from response (try multiple known shapes from ThaiBulkSMS v1/v2)
            $messageId = $data['phone_number_list'][0]['message_id']
                ?? $data['message_id']
                ?? $data['queue_id']
                ?? $data['phone_number_list'][0]['queue_id']
                ?? null;

            if (!$messageId) {
                // SMS may still have been queued successfully — generate a synthetic id
                // so the OTP flow can proceed (we already verified the call returned 2xx + no error).
                $messageId = 'auto-' . uniqid();
                Log::warning('ThaiBulkSMS response missing message_id, using synthetic id', [
                    'msisdn' => $msisdn,
                    'synthetic_id' => $messageId,
                    'body' => $data,
                ]);
            }

            // Create OTP request record (store hashed OTP)
            $otpRequest = OtpRequest::create([
                'phone_msisdn' => $msisdn,
                'message_id' => $messageId,
                'otp_code' => bcrypt($otpCode),
                'ttl' => $this->defaultTtl,
                'expires_at' => now()->addSeconds($this->defaultTtl),
                'purpose' => $purpose,
                'ip_address' => $ip,
                'user_agent' => $userAgent,
                'web_member_id' => $webMemberId,
            ]);

            $result = [
                'success' => true,
                'message' => 'ส่ง OTP ไปยังหมายเลข ' . $this->maskPhone($msisdn) . ' แล้ว',
                'otp_request_id' => $otpRequest->id,
                'expires_in' => $this->defaultTtl,
                'remaining_credit' => $data['remaining_credit'] ?? null,
            ];

            // Include OTP in response for debug mode
            if ($this->debugMode) {
                $result['debug_otp'] = $otpCode;
            }

            return $result;

        } catch (\Exception $e) {
            Log::error('OTP request exception', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => collect($e->getTrace())->take(5)->toArray(),
                'phone' => $msisdn,
            ]);

            return [
                'success' => false,
                'error' => 'exception',
                'message' => config('app.debug')
                    ? ('เกิดข้อผิดพลาด: ' . $e->getMessage() . ' (' . basename($e->getFile()) . ':' . $e->getLine() . ')')
                    : 'เกิดข้อผิดพลาด กรุณาลองใหม่',
                'debug_exception' => config('app.debug') ? [
                    'class' => get_class($e),
                    'message' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ] : null,
            ];
        }
    }

    /**
     * Request OTP via email (uses SMTP from Setting)
     */
    public function requestEmailOtp(
        string $email,
        string $purpose = 'booking',
        ?string $ip = null,
        ?string $userAgent = null,
        ?int $webMemberId = null
    ): array {
        if (!$this->enabled) {
            return [
                'success' => false,
                'error' => 'disabled',
                'message' => 'ระบบ OTP ถูกปิดใช้งานชั่วคราว',
            ];
        }

        $email = strtolower(trim($email));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return [
                'success' => false,
                'error' => 'invalid_email',
                'message' => 'รูปแบบอีเมลไม่ถูกต้อง',
            ];
        }

        if (OtpRequest::isRateLimitedByEmail($email)) {
            return [
                'success' => false,
                'error' => 'rate_limited',
                'message' => 'ขอ OTP บ่อยเกินไป กรุณารอสักครู่',
            ];
        }

        if ($ip && OtpRequest::isRateLimitedByIp($ip)) {
            return [
                'success' => false,
                'error' => 'rate_limited',
                'message' => 'มีการขอ OTP มากเกินไป กรุณารอสักครู่',
            ];
        }

        $smtpConfig = Setting::get('smtp_config');
        if (!$smtpConfig || empty($smtpConfig['host']) || empty($smtpConfig['enabled'])) {
            return [
                'success' => false,
                'error' => 'smtp_not_configured',
                'message' => 'ระบบส่งอีเมลยังไม่พร้อมใช้งาน กรุณาเลือกยืนยันผ่านเบอร์โทรแทน',
            ];
        }

        $otpCode = $this->generateOtpCode($this->defaultDigits);
        $subject = $this->getEmailSubject($purpose);
        $bodyHtml = $this->getEmailBody($purpose, $otpCode);

        try {
            $password = '';
            if (!empty($smtpConfig['password'])) {
                try {
                    $password = decrypt($smtpConfig['password']);
                } catch (\Exception $e) {
                    $password = $smtpConfig['password'];
                }
            }

            $useTls = ($smtpConfig['encryption'] ?? '') === 'ssl';

            $transport = new \Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport(
                $smtpConfig['host'],
                (int) $smtpConfig['port'],
                $useTls
            );

            if (!empty($smtpConfig['username'])) {
                $transport->setUsername($smtpConfig['username']);
            }
            if (!empty($password)) {
                $transport->setPassword($password);
            }

            $mailer = new \Symfony\Component\Mailer\Mailer($transport);

            $message = (new \Symfony\Component\Mime\Email())
                ->from(new \Symfony\Component\Mime\Address(
                    $smtpConfig['from_address'] ?? 'noreply@nexttrip.asia',
                    $smtpConfig['from_name'] ?? 'NextTrip'
                ))
                ->to($email)
                ->subject($subject)
                ->html($bodyHtml);

            $mailer->send($message);

            $otpRequest = OtpRequest::create([
                'email' => $email,
                'channel' => 'email',
                'message_id' => 'email-' . uniqid(),
                'otp_code' => bcrypt($otpCode),
                'ttl' => $this->defaultTtl,
                'expires_at' => now()->addSeconds($this->defaultTtl),
                'purpose' => $purpose,
                'ip_address' => $ip,
                'user_agent' => $userAgent,
                'web_member_id' => $webMemberId,
            ]);

            $result = [
                'success' => true,
                'message' => 'ส่ง OTP ไปยังอีเมล ' . $this->maskEmail($email) . ' แล้ว',
                'otp_request_id' => $otpRequest->id,
                'expires_in' => $this->defaultTtl,
            ];

            if ($this->debugMode) {
                $result['debug_otp'] = $otpCode;
            }

            return $result;
        } catch (\Exception $e) {
            Log::error('Email OTP send failed', [
                'email' => $email,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => 'send_failed',
                'message' => 'ไม่สามารถส่งอีเมลได้ กรุณาลองใหม่หรือเลือกยืนยันผ่านเบอร์โทร',
            ];
        }
    }

    /**
     * Verify OTP (local verification)
     */
    public function verifyOtp(int $otpRequestId, string $otp): array
    {
        $otpRequest = OtpRequest::find($otpRequestId);

        if (!$otpRequest) {
            return [
                'success' => false,
                'error' => 'not_found',
                'message' => 'ไม่พบข้อมูล OTP',
            ];
        }

        if ($otpRequest->verified) {
            return [
                'success' => false,
                'error' => 'already_verified',
                'message' => 'OTP นี้ถูกใช้งานแล้ว',
            ];
        }

        if ($otpRequest->isExpired()) {
            return [
                'success' => false,
                'error' => 'expired',
                'message' => 'OTP หมดอายุแล้ว กรุณาขอใหม่',
            ];
        }

        if ($otpRequest->isMaxAttemptsReached()) {
            return [
                'success' => false,
                'error' => 'max_attempts',
                'message' => 'กรอก OTP ผิดเกินจำนวนครั้งที่กำหนด',
            ];
        }

        // Verify OTP locally using Hash::check
        if (password_verify($otp, $otpRequest->otp_code)) {
            $otpRequest->markAsVerified();

            return [
                'success' => true,
                'message' => 'ยืนยัน OTP สำเร็จ',
                'phone_msisdn' => $otpRequest->phone_msisdn,
                'purpose' => $otpRequest->purpose,
            ];
        }

        // Wrong OTP
        $otpRequest->incrementAttempts();

        $remainingAttempts = $otpRequest->max_attempts - $otpRequest->attempts;

        return [
            'success' => false,
            'error' => 'invalid_otp',
            'message' => "รหัส OTP ไม่ถูกต้อง (เหลือ {$remainingAttempts} ครั้ง)",
            'remaining_attempts' => $remainingAttempts,
        ];
    }

    /**
     * Get OTP message based on purpose
     */
    private function getOtpMessage(string $purpose, string $otpCode): string
    {
        $template = match ($purpose) {
            'register' => 'รหัส OTP สำหรับสมัครสมาชิก NextTrip คือ {otp} (หมดอายุใน 5 นาที)',
            'login' => 'รหัส OTP สำหรับเข้าสู่ระบบ NextTrip คือ {otp} (หมดอายุใน 5 นาที)',
            'reset_password' => 'รหัส OTP สำหรับรีเซ็ตรหัสผ่าน NextTrip คือ {otp} (หมดอายุใน 5 นาที)',
            'verify_phone' => 'รหัส OTP สำหรับยืนยันเบอร์โทร NextTrip คือ {otp} (หมดอายุใน 5 นาที)',
            'booking' => 'รหัส OTP สำหรับจองทัวร์ NextTrip คือ {otp} (หมดอายุใน 5 นาที)',
            default => 'รหัส OTP ของคุณคือ {otp} (หมดอายุใน 5 นาที)',
        };

        return str_replace('{otp}', $otpCode, $template);
    }

    /**
     * Normalize phone to MSISDN format (66xxxxxxxxx)
     */
    private function normalizeThaiMsisdn(string $input): string
    {
        $s = preg_replace('/[^\d]/', '', trim($input));

        // Handle 0066 prefix
        if (str_starts_with($s, '0066')) {
            $s = '66' . substr($s, 4);
        }

        // Handle 0 prefix (Thai local format)
        if (preg_match('/^0\d{9}$/', $s)) {
            return '66' . substr($s, 1);
        }

        // Already in MSISDN format
        if (preg_match('/^66\d{9}$/', $s)) {
            return $s;
        }

        throw new \InvalidArgumentException('Invalid Thai phone number format');
    }

    /**
     * Mask phone number for display (66812345678 -> 668****5678)
     */
    private function maskPhone(string $phone): string
    {
        if (strlen($phone) < 10) {
            return $phone;
        }
        
        return substr($phone, 0, 3) . '****' . substr($phone, -4);
    }

    /**
     * Mask email for display (john.doe@example.com -> j***e@example.com)
     */
    private function maskEmail(string $email): string
    {
        $parts = explode('@', $email);
        if (count($parts) !== 2) return $email;
        $local = $parts[0];
        $len = strlen($local);
        if ($len <= 2) return $email;
        return substr($local, 0, 1) . str_repeat('*', max(1, $len - 2)) . substr($local, -1) . '@' . $parts[1];
    }

    /**
     * Get email subject by purpose
     */
    private function getEmailSubject(string $purpose): string
    {
        return match ($purpose) {
            'register' => 'รหัส OTP สมัครสมาชิก NextTrip',
            'login' => 'รหัส OTP เข้าสู่ระบบ NextTrip',
            'reset_password' => 'รหัส OTP รีเซ็ตรหัสผ่าน NextTrip',
            'booking' => 'รหัส OTP สำหรับจองทัวร์ NextTrip',
            default => 'รหัส OTP ของคุณ',
        };
    }

    /**
     * Get email HTML body
     */
    private function getEmailBody(string $purpose, string $otpCode): string
    {
        $label = match ($purpose) {
            'booking' => 'การจองทัวร์',
            'register' => 'การสมัครสมาชิก',
            'login' => 'การเข้าสู่ระบบ',
            'reset_password' => 'การรีเซ็ตรหัสผ่าน',
            default => 'การยืนยันตัวตน',
        };

        return <<<HTML
<div style="font-family:Arial,sans-serif;max-width:480px;margin:0 auto;padding:24px;background:#fff;border:1px solid #eee;border-radius:8px">
    <h2 style="color:#1f2937;margin:0 0 8px">NextTrip</h2>
    <p style="color:#374151;font-size:14px">รหัส OTP สำหรับ{$label} ของคุณคือ:</p>
    <div style="font-size:32px;letter-spacing:8px;font-weight:bold;text-align:center;background:#fff7ed;color:#c2410c;padding:16px;border-radius:8px;margin:16px 0">
        {$otpCode}
    </div>
    <p style="color:#6b7280;font-size:13px">รหัสนี้จะหมดอายุภายใน 5 นาที กรุณาอย่าเปิดเผยรหัสนี้แก่ผู้อื่น</p>
    <p style="color:#9ca3af;font-size:12px;margin-top:24px">หากคุณไม่ได้เป็นผู้ขอ OTP นี้ กรุณาเพิกเฉยต่ออีเมลฉบับนี้</p>
</div>
HTML;
    }
}
