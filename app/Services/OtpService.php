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
            $this->baseUrl = $otpConfig['endpoint'] ?? 'https://api-v2.thaibulksms.com';
            $this->sender = $otpConfig['sender'] ?? 'SMS.';
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
            $this->baseUrl = 'https://api-v2.thaibulksms.com';
            $this->apiKey = config('services.thaibulksms.api_key') ?? '';
            $this->apiSecret = config('services.thaibulksms.api_secret') ?? '';
            $this->sender = 'SMS.';
            $this->enabled = true;
            $this->debugMode = false;
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
                ]);

            if (!$response->successful()) {
                Log::error('ThaiBulkSMS SMS request failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return [
                    'success' => false,
                    'error' => 'api_error',
                    'message' => 'ไม่สามารถส่ง OTP ได้ กรุณาลองใหม่',
                ];
            }

            $data = $response->json();
            
            // Check for bad phone numbers
            if (!empty($data['bad_phone_number_list'])) {
                return [
                    'success' => false,
                    'error' => 'invalid_phone',
                    'message' => 'หมายเลขโทรศัพท์ไม่ถูกต้อง',
                ];
            }

            // Get message_id from response
            $messageId = $data['phone_number_list'][0]['message_id'] ?? null;

            if (!$messageId) {
                return [
                    'success' => false,
                    'error' => 'no_message_id',
                    'message' => 'ไม่ได้รับการยืนยันจากระบบ',
                ];
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
                'phone' => $msisdn,
            ]);

            return [
                'success' => false,
                'error' => 'exception',
                'message' => 'เกิดข้อผิดพลาด กรุณาลองใหม่',
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
}
